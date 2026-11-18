<?php

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\CPanel;
use GuiBranco\ProjectsMonitor\Library\Logger;

/**
 * Tests for the raw rows the errors list reads: CPanel::getErrorLogEntries()
 * and Logger::getGroupedMessageRows(), with the HTTP client and the database
 * connection mocked (the objects are built without their constructors, which
 * read secrets and connect).
 */
class ErrorsInboxSourcesTest extends TestCase
{


    /**
     * Sets a private property.
     *
     * @param object $object   The object.
     * @param string $property The property.
     * @param mixed  $value    The value.
     *
     * @return void
     */
    private function setProperty(object $object, string $property, mixed $value): void
    {
        (new ReflectionProperty($object, $property))->setValue($object, $value);

    }//end setProperty()


    /**
     * A cPanel API JSON body whose cpanelresult.data is the given list.
     *
     * @param array $data The data.
     *
     * @return Response
     */
    private function cPanelResponse(array $data): Response
    {
        return new Response(true, json_encode(["cpanelresult" => ["data" => $data]]), "", 200, "", []);

    }//end cPanelResponse()


    /**
     * Entries of every error_log file (not the trash) come back raw, paths relative to home.
     *
     * @return void
     */
    public function testErrorLogEntries(): void
    {
        (new Configuration())->init();

        $log     = "[18-Nov-2026 11:30:00 UTC] PHP Warning:  Undefined variable \$x in /home/user/public_html/api/a.php on line 12\n";
        $request = $this->createStub(Request::class);
        $search  = $this->cPanelResponse([["file" => "/home/user/public_html/api/error_log"], ["file" => "/home/user/.trash/error_log"]]);
        $content = $this->cPanelResponse([["contents" => $log]]);
        $request->method("get")->willReturnCallback(
            function (string $url) use ($search, $content) {
                if (str_contains($url, "func=search") === true) {
                    return $search;
                }

                return $content;
            }
        );

        $cPanel = (new ReflectionClass(CPanel::class))->newInstanceWithoutConstructor();
        $this->setProperty($cPanel, "request", $request);
        $this->setProperty($cPanel, "username", "user");
        $this->setProperty($cPanel, "baseUrl", "https://cpanel.example.com");
        $this->setProperty($cPanel, "apiToken", "token");

        $entries = $cPanel->getErrorLogEntries();

        $this->assertCount(1, $entries);
        $this->assertSame("public_html/api", $entries[0]["directory"]);
        $this->assertSame("public_html/api/a.php", $entries[0]["file"]);
        $this->assertSame("12", (string) $entries[0]["line"]);
        $this->assertSame("18-Nov-2026 11:30:00 UTC", $entries[0]["date"]);
        $this->assertStringContainsString("Undefined variable", $entries[0]["multilineError"]);

        $messages = $cPanel->getErrorLogMessages();
        $this->assertSame(["Date", "Directory", "Error", "File", "Line"], $messages[0]);
        $this->assertSame("public_html/api", $messages[1][1]);

    }//end testErrorLogEntries()


    /**
     * Grouped messages come back as associative rows.
     *
     * @return void
     */
    public function testGroupedMessageRows(): void
    {
        $row    = array_merge(["application" => "Billing", "message" => "Invoice 1 not found"], ["count" => "2", "lastSeen" => "2026-11-18 10:00:00", "sampleId" => "4"]);
        $result = $this->createStub(mysqli_result::class);
        $result->method("fetch_assoc")->willReturnOnConsecutiveCalls($row, null);

        $connection = $this->createMock(mysqli::class);
        $connection->expects($this->once())->method("query")->willReturn($result);

        $logger = (new ReflectionClass(Logger::class))->newInstanceWithoutConstructor();
        $this->setProperty($logger, "connection", $connection);

        $this->assertSame([$row], $logger->getGroupedMessageRows());

    }//end testGroupedMessageRows()


}//end class
