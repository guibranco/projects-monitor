<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionItem;
use GuiBranco\ProjectsMonitor\Library\Attention\AttentionNotifier;
use GuiBranco\ProjectsMonitor\Library\OneSignal;

/**
 * Tests for AttentionNotifier and the OneSignal payload.
 */
class AttentionNotifierTest extends TestCase
{


    /**
     * An item as the attention API returns it.
     *
     * @param string      $fingerprint Its fingerprint.
     * @param string      $severity    Its severity.
     * @param string|null $since       Since when.
     * @param string      $state       active, snoozed or acknowledged.
     *
     * @return array
     */
    private function getItem(string $fingerprint, string $severity, ?string $since, string $state="active"): array
    {
        $item = (new AttentionItem("healthchecks", "ops", $severity, "Check ".$fingerprint." down", $since, "ops.php#healthchecks", $fingerprint))->toArray();

        $item["state"] = $state;

        return $item;

    }//end getItem()


    /**
     * Only active critical items not pushed for their current since value are selected.
     *
     * @return void
     */
    public function testSelectsOnlyNewActiveCriticalItems(): void
    {
        $items = [
                  $this->getItem("hc:new", AttentionItem::CRITICAL, "2026-11-18T10:00:00Z"),
                  $this->getItem("hc:warn", AttentionItem::WARNING, "2026-11-18T10:00:00Z"),
                  $this->getItem("hc:acked", AttentionItem::CRITICAL, "2026-11-18T10:00:00Z", "acknowledged"),
                  $this->getItem("hc:pushed", AttentionItem::CRITICAL, "2026-11-18T10:00:00Z"),
                  $this->getItem("hc:raised-again", AttentionItem::CRITICAL, "2026-11-18T11:00:00Z"),
                  $this->getItem("hc:no-since", AttentionItem::CRITICAL, null),
                  $this->getItem("hc:no-since-pushed", AttentionItem::CRITICAL, null),
                 ];
        $notified = [
                     "hc:pushed"          => "2026-11-18T10:00:00Z",
                     "hc:raised-again"    => "2026-11-18T09:00:00Z",
                     "hc:no-since-pushed" => "",
                    ];

        $selected = (new AttentionNotifier(3))->select($items, $notified);

        $this->assertSame(["hc:new", "hc:raised-again", "hc:no-since"], array_column($selected, "fingerprint"));

    }//end testSelectsOnlyNewActiveCriticalItems()


    /**
     * Up to the limit each item gets its push; past it, one summary push.
     *
     * @return void
     */
    public function testPushesOrSummary(): void
    {
        $notifier = new AttentionNotifier(2);
        $two      = [
                     $this->getItem("hc:a", AttentionItem::CRITICAL, "2026-11-18T10:00:00Z"),
                     $this->getItem("hc:b", AttentionItem::CRITICAL, null),
                    ];

        $pushes = $notifier->buildPushes($two);
        $this->assertCount(2, $pushes);
        $this->assertSame("Critical · Healthchecks", $pushes[0]["heading"]);
        $this->assertSame("Check hc:a down", $pushes[0]["content"]);
        $this->assertSame("ops.php#healthchecks", $pushes[0]["link"]);
        $this->assertNotSame($pushes[0]["key"], $pushes[1]["key"]);

        $three   = array_merge($two, [$this->getItem("hc:c", AttentionItem::CRITICAL, null)]);
        $summary = $notifier->buildPushes($three);
        $this->assertCount(1, $summary);
        $this->assertSame("3 critical items need attention", $summary[0]["heading"]);
        $this->assertSame("index.php", $summary[0]["link"]);
        $this->assertSame([], $notifier->buildPushes([]));

    }//end testPushesOrSummary()


    /**
     * The idempotency key is a stable UUID per raise.
     *
     * @return void
     */
    public function testIdempotencyKeyIsAStableUuid(): void
    {
        $notifier = new AttentionNotifier(3);
        $item     = $this->getItem("hc:a", AttentionItem::CRITICAL, "2026-11-18T10:00:00Z");

        $first  = $notifier->buildPushes([$item])[0]["key"];
        $second = $notifier->buildPushes([$item])[0]["key"];
        $other  = $notifier->buildPushes([$this->getItem("hc:a", AttentionItem::CRITICAL, "2026-11-18T11:00:00Z")])[0]["key"];

        $this->assertSame($first, $second);
        $this->assertNotSame($first, $other);
        $this->assertMatchesRegularExpression("/^[0-9a-f]{8}-[0-9a-f]{4}-5[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/", $first);

    }//end testIdempotencyKeyIsAStableUuid()


    /**
     * Without a secrets file OneSignal is not configured; with one, the payload targets the segment and links to the dashboard.
     *
     * @return void
     */
    public function testOneSignalPayload(): void
    {
        $this->assertFalse((new OneSignal(null, "/nonexistent/oneSignal.secrets.php"))->isConfigured());

        $file = tempnam(sys_get_temp_dir(), "onesignal");
        file_put_contents($file, "<?php\n\$oneSignalAppId = 'app-1';\n\$oneSignalApiKey = 'key-1';\n\$oneSignalSiteUrl = 'https://monitor.example.com/';\n");
        try {
            $oneSignal = new OneSignal(null, $file);
            $payload   = $oneSignal->buildPayload("Critical · Healthchecks", "Check down", "ops.php#healthchecks", "key");
        } finally {
            unlink($file);
        }

        $this->assertTrue($oneSignal->isConfigured());
        $this->assertSame("app-1", $payload["app_id"]);
        $this->assertSame([OneSignal::DEFAULT_SEGMENT], $payload["included_segments"]);
        $this->assertSame(["en" => "Check down"], $payload["contents"]);
        $this->assertSame("https://monitor.example.com/ops.php#healthchecks", $payload["url"]);
        $this->assertSame("key", $payload["idempotency_key"]);

    }//end testOneSignalPayload()


}//end class
