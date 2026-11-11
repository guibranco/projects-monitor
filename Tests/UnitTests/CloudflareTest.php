<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use GuiBranco\ProjectsMonitor\Library\Cloudflare;
use GuiBranco\ProjectsMonitor\Library\RequestException;
use GuiBranco\ProjectsMonitor\Library\SecretsFileNotFoundException;

/**
 * Tests the Cloudflare tile: every zone of the account, across all pages,
 * sorted by name, with paused winning over the zone status.
 */
class CloudflareTest extends TestCase
{
    private const ACCOUNT_ID = "account123";


    /**
     * A zone as GET /client/v4/zones lists it, trimmed to the fields the tile reads.
     *
     * @param string      $name        The domain.
     * @param string      $status      The zone status.
     * @param boolean     $paused      Whether the zone is paused.
     * @param string|null $activatedOn The activation date, or null.
     *
     * @return array
     */
    private function getZone(string $name, string $status, bool $paused, ?string $activatedOn): array
    {
        return [
            "id"           => md5($name),
            "name"         => $name,
            "status"       => $status,
            "paused"       => $paused,
            "activated_on" => $activatedOn,
            "account"      => ["id" => self::ACCOUNT_ID],
            "plan"         => ["name" => "Free Website"],
        ];

    }//end getZone()


    /**
     * One page of the zones endpoint.
     *
     * @param array   $zones      The zones of the page.
     * @param integer $page       The page number.
     * @param integer $totalPages The number of pages.
     *
     * @return Response
     */
    private function getPage(array $zones, int $page, int $totalPages): Response
    {
        $body = json_encode(
            [
                "success"     => true,
                "errors"      => [],
                "result"      => $zones,
                "result_info" => [
                    "page"        => $page,
                    "per_page"    => 50,
                    "total_pages" => $totalPages,
                ],
            ]
        );

        return new Response(true, $body, "", 200, "https://api.cloudflare.com/client/v4/zones", []);

    }//end getPage()


    /**
     * The tile with its constructor skipped and the HTTP client mocked.
     *
     * @param Response ...$responses The answers of the API, in order.
     *
     * @return Cloudflare
     */
    private function getTile(Response ...$responses): Cloudflare
    {
        $builder = $this->getMockBuilder(Cloudflare::class);
        $builder->disableOriginalConstructor();
        $builder->onlyMethods([]);

        $tile = $builder->getMock();

        $request = $this->createMock(Request::class);
        $request->method('get')->willReturnOnConsecutiveCalls(...$responses);

        foreach (["request" => $request, "headers" => [], "accountId" => self::ACCOUNT_ID] as $property => $value) {
            $reflection = new ReflectionProperty(Cloudflare::class, $property);
            $reflection->setValue($tile, $value);
        }

        return $tile;

    }//end getTile()


    /**
     * Every page is read, the zones are sorted by name, and a paused zone
     * shows as paused even though its status stays active.
     *
     * @return void
     */
    public function testListsEveryZoneSortedWithItsState(): void
    {
        $first  = $this->getPage(
            [
                $this->getZone("zeta.dev", "active", false, "2025-01-15T10:00:00Z"),
                $this->getZone("alpha.com", "pending", false, null),
            ],
            1,
            2
        );
        $second = $this->getPage([$this->getZone("middle.bot", "active", true, "2024-06-01T00:00:00Z")], 2, 2);

        $zones = $this->getTile($first, $second)->getZones();

        $this->assertCount(4, $zones);
        $this->assertSame(["Domain", "Status", "Plan", "Activated"], $zones[0]);

        $this->assertStringContainsString("alpha.com", $zones[1][0]);
        $this->assertStringContainsString("https://dash.cloudflare.com/account123/alpha.com", $zones[1][0]);
        $this->assertSame("pending", $zones[1][1]);
        $this->assertSame("-", $zones[1][3]);

        $this->assertStringContainsString("middle.bot", $zones[2][0]);
        $this->assertSame("paused", $zones[2][1]);

        $this->assertStringContainsString("zeta.dev", $zones[3][0]);
        $this->assertSame("active", $zones[3][1]);
        $this->assertSame("Free Website", $zones[3][2]);
        $this->assertSame("15/01/2025", $zones[3][3]);

    }//end testListsEveryZoneSortedWithItsState()


    /**
     * Anything but a successful 200 fails the tile with the status code and
     * the body, the API errors, or the transport error in the message.
     *
     * @return void
     */
    public function testFailsWhenTheApiDoesNotAnswer(): void
    {
        $url       = "https://api.cloudflare.com/client/v4/zones";
        $forbidden = new Response(true, "{\"success\": false}", "", 403, $url, []);
        $rejected  = new Response(true, "{\"success\": false, \"errors\": [{\"code\": 9109, \"message\": \"Invalid access token\"}]}", "", 200, $url, []);
        $failed    = new Response(false, null, "Could not resolve host", -1, $url, null);

        $tile = $this->getTile($forbidden, $rejected, $failed);

        try {
            $tile->getZones();
            $this->fail("A 403 must fail the tile.");
        } catch (RequestException $denied) {
            $this->assertSame("Code: 403 - Error: {\"success\": false}", $denied->getMessage());
        }

        try {
            $tile->getZones();
            $this->fail("An unsuccessful answer must fail the tile.");
        } catch (RequestException $invalid) {
            $this->assertStringContainsString("Invalid access token", $invalid->getMessage());
        }

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("Code: -1 - Error: Could not resolve host");

        $tile->getZones();

    }//end testFailsWhenTheApiDoesNotAnswer()


    /**
     * Without the secrets file the real constructor fails closed, like every
     * other integration.
     *
     * @return void
     */
    public function testConstructorFailsClosedWithoutSecretsFile(): void
    {
        if (file_exists(__DIR__.'/../../Src/secrets/cloudflare.secrets.php') === true) {
            $this->markTestSkipped('cloudflare.secrets.php exists in this checkout.');
        }

        $this->expectException(SecretsFileNotFoundException::class);
        $this->expectExceptionMessage("File not found: cloudflare.secrets.php");

        new Cloudflare();

    }//end testConstructorFailsClosedWithoutSecretsFile()


}//end class
