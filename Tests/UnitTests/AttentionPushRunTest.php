<?php

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionNotifier;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionPushRun;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionStore;
use GuiBranco\ProjectsMonitor\Library\OneSignal;
use GuiBranco\ProjectsMonitor\Library\RequestException;

/**
 * Tests for AttentionPushRun, the notification store methods and OneSignal::send().
 */
class AttentionPushRunTest extends TestCase
{


    /**
     * An attention API response.
     *
     * @param array $items   The items (with their state).
     * @param array $sources The sources.
     *
     * @return array
     */
    private function getResponse(array $items, array $sources=[]): array
    {
        return array_merge(["items" => $items], ["sources" => $sources]);

    }//end getResponse()


    /**
     * An active item.
     *
     * @param string $fingerprint Its fingerprint.
     * @param string $severity    Its severity.
     *
     * @return array
     */
    private function getItem(string $fingerprint, string $severity): array
    {
        $item = (new AttentionItem("healthchecks", "ops", $severity, "Check ".$fingerprint, "2026-11-18T10:00:00Z", "ops.php", $fingerprint))->toArray();

        $item["state"] = "active";

        return $item;

    }//end getItem()


    /**
     * A OneSignal client whose secrets file is a temporary one with an App ID and a key.
     *
     * @param Request $request The HTTP client.
     *
     * @return OneSignal
     */
    private function getOneSignal(Request $request): OneSignal
    {
        $file = tempnam(sys_get_temp_dir(), "onesignal");
        file_put_contents($file, "<?php\n\$oneSignalAppId = 'app';\n\$oneSignalApiKey = 'key';\n\$oneSignalSegment = 'Everyone';\n");
        try {
            return new OneSignal($request, $file);
        } finally {
            unlink($file);
        }

    }//end getOneSignal()


    /**
     * New critical items are pushed, then remembered; items no longer raised are forgotten.
     *
     * @return void
     */
    public function testPushesAndRemembers(): void
    {
        $store = $this->createMock(AttentionStore::class);
        $store->method("getNotified")->willReturn(array_merge(["hc:old" => "2026-11-18T10:00:00Z"]));
        $store->expects($this->once())->method("markNotified")->with("hc:new", "2026-11-18T10:00:00Z");
        $store->expects($this->once())->method("pruneNotified")->with(["hc:new", "hc:old", "hc:warn"]);

        $oneSignal = $this->createMock(OneSignal::class);
        $oneSignal->expects($this->once())->method("send")->with("Critical · Healthchecks", "Check hc:new", "ops.php", $this->isString());

        $items  = [
                   $this->getItem("hc:new", AttentionItem::CRITICAL),
                   $this->getItem("hc:old", AttentionItem::CRITICAL),
                   $this->getItem("hc:warn", AttentionItem::WARNING),
                  ];
        $result = (new AttentionPushRun($store, $oneSignal, new AttentionNotifier(3)))->run($this->getResponse($items));

        $this->assertSame(1, $result["selected"]);
        $this->assertSame(1, $result["sent"]);
        $this->assertSame(0, $result["failed"]);
        $this->assertSame([], $result["errors"]);

    }//end testPushesAndRemembers()


    /**
     * A failed push remembers nothing; an unreadable source prunes nothing.
     *
     * @return void
     */
    public function testFailureAndUnreadableSource(): void
    {
        $store = $this->createMock(AttentionStore::class);
        $store->method("getNotified")->willReturn([]);
        $store->expects($this->never())->method("markNotified");
        $store->expects($this->never())->method("pruneNotified");

        $oneSignal = $this->createStub(OneSignal::class);
        $oneSignal->method("send")->willThrowException(new RequestException("HTTP 500"));

        $sources = [array_merge(["source" => "healthchecks"], ["status" => "error"])];
        $result  = (new AttentionPushRun($store, $oneSignal, new AttentionNotifier(3)))->run($this->getResponse([$this->getItem("hc:a", AttentionItem::CRITICAL)], $sources));

        $this->assertSame(1, $result["failed"]);
        $this->assertSame(["HTTP 500"], $result["errors"]);

    }//end testFailureAndUnreadableSource()


    /**
     * The notification store reads, writes and prunes by fingerprint.
     *
     * @return void
     */
    public function testNotificationStore(): void
    {
        $rows   = [
                   array_merge(["fingerprint" => "hc:a"], ["since" => "2026-11-18T10:00:00Z"]),
                   array_merge(["fingerprint" => "hc:b"], ["since" => ""]),
                  ];
        $result = $this->createStub(mysqli_result::class);
        $result->method("fetch_assoc")->willReturnOnConsecutiveCalls(...array_merge($rows, [null], $rows, [null]));

        $statement = $this->createMock(mysqli_stmt::class);
        $statement->method("bind_param")->willReturn(true);
        $statement->expects($this->exactly(2))->method("execute")->willReturn(true);

        $connection = $this->createMock(mysqli::class);
        $connection->method("query")->willReturn($result);
        $connection->expects($this->exactly(2))->method("prepare")->willReturn($statement);

        $store = new AttentionStore($connection);

        $this->assertSame(array_merge(["hc:a" => "2026-11-18T10:00:00Z"], ["hc:b" => ""]), $store->getNotified());
        $this->assertTrue($store->markNotified("hc:c", null));
        $store->pruneNotified(["hc:a"]);

    }//end testNotificationStore()


    /**
     * send() posts the payload with the key, and throws when OneSignal refuses it or it is not configured.
     *
     * @return void
     */
    public function testOneSignalSend(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->exactly(2))->method("post")
            ->with(OneSignal::API_URL, $this->containsEqual("Authorization: Key key"), $this->stringContains("\"included_segments\":[\"Everyone\"]"))
            ->willReturnOnConsecutiveCalls(
                new Response(true, "{\"id\":\"x\"}", "", 200, OneSignal::API_URL, []),
                new Response(false, "{\"errors\":[\"bad\"]}", "", 400, OneSignal::API_URL, [])
            );

        $oneSignal = $this->getOneSignal($request);
        $oneSignal->send("Heading", "Content", "index.php", "key-1");

        $this->expectException(RequestException::class);
        $oneSignal->send("Heading", "Content", "index.php", "key-2");

    }//end testOneSignalSend()


    /**
     * Without secrets send() refuses to run.
     *
     * @return void
     */
    public function testOneSignalNotConfigured(): void
    {
        $this->expectException(RequestException::class);
        (new OneSignal($this->createStub(Request::class), "/nonexistent/oneSignal.secrets.php"))->send("H", "C", "", "k");

    }//end testOneSignalNotConfigured()


}//end class
