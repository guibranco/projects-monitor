<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use GuiBranco\ProjectsMonitor\Library\Cloudflare;
use GuiBranco\ProjectsMonitor\Library\RequestException;
use GuiBranco\ProjectsMonitor\Library\SecretsFileNotFoundException;

/**
 * Tests the Cloudflare tile: every zone of the account, across all pages,
 * sorted by name, with paused winning over the zone status, and each zone's
 * traffic from the GraphQL Analytics API.
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
     * @return object
     */
    private function getZone(string $name, string $status, bool $paused, ?string $activatedOn): object
    {
        $account     = new stdClass();
        $account->id = self::ACCOUNT_ID;

        $plan       = new stdClass();
        $plan->name = "Free Website";

        $zone = new stdClass();
        $zone->id           = md5($name);
        $zone->name         = $name;
        $zone->status       = $status;
        $zone->paused       = $paused;
        $zone->activated_on = $activatedOn;
        $zone->account      = $account;
        $zone->plan         = $plan;

        return $zone;

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
        $resultInfo = new stdClass();
        $resultInfo->page        = $page;
        $resultInfo->per_page    = 50;
        $resultInfo->total_pages = $totalPages;

        $answer = new stdClass();
        $answer->success     = true;
        $answer->errors      = [];
        $answer->result      = $zones;
        $answer->result_info = $resultInfo;

        return new Response(true, json_encode($answer), "", 200, "https://api.cloudflare.com/client/v4/zones", []);

    }//end getPage()


    /**
     * One day of a zone's traffic, as httpRequests1dGroups returns it.
     *
     * @param integer $requests       The requests.
     * @param integer $cachedRequests The cached requests.
     * @param integer $bytes          The bandwidth.
     * @param integer $pageViews      The page views.
     * @param integer $uniques        The unique visitors.
     *
     * @return array
     */
    private function getDay(int $requests, int $cachedRequests, int $bytes, int $pageViews, int $uniques): array
    {
        return [
            "sum"  => [
                "requests"       => $requests,
                "cachedRequests" => $cachedRequests,
                "bytes"          => $bytes,
                "pageViews"      => $pageViews,
            ],
            "uniq" => ["uniques" => $uniques],
        ];

    }//end getDay()


    /**
     * An answer of the GraphQL Analytics API.
     *
     * @param array $zones The days of traffic, keyed by zone name.
     *
     * @return Response
     */
    private function getAnalytics(array $zones): Response
    {
        $result = [];
        foreach ($zones as $name => $days) {
            $result[] = ["zoneTag" => md5($name), "httpRequests1dGroups" => $days];
        }

        $answer = ["data" => ["viewer" => ["zones" => $result]], "errors" => null];

        return new Response(true, json_encode($answer), "", 200, "https://api.cloudflare.com/client/v4/graphql", []);

    }//end getAnalytics()


    /**
     * The tile with its constructor skipped and the HTTP client mocked.
     *
     * @param Response|null $analytics    The answer of the GraphQL API, or null for a failure.
     * @param Response      ...$responses The answers of the zones endpoint, in order.
     *
     * @return Cloudflare
     */
    private function getTile(?Response $analytics, Response ...$responses): Cloudflare
    {
        $builder = $this->getMockBuilder(Cloudflare::class);
        $builder->disableOriginalConstructor();
        $builder->onlyMethods([]);

        $tile = $builder->getMock();

        $request = $this->createMock(Request::class);
        $request->method('get')->willReturnOnConsecutiveCalls(...$responses);

        $analytics ??= new Response(true, "{\"data\": null, \"errors\": [{\"message\": \"not authorized\"}]}", "", 200, "https://api.cloudflare.com/client/v4/graphql", []);
        $request->method('post')->willReturn($analytics);

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
        $firstZones   = [];
        $firstZones[] = $this->getZone("zeta.dev", "active", false, "2025-01-15T10:00:00Z");
        $firstZones[] = $this->getZone("alpha.com", "pending", false, null);

        $first  = $this->getPage($firstZones, 1, 2);
        $second = $this->getPage([$this->getZone("middle.bot", "active", true, "2024-06-01T00:00:00Z")], 2, 2);

        $zones = $this->getTile(null, $first, $second)->getZones();

        $this->assertCount(4, $zones);
        $this->assertSame(["Domain", "Status", "Plan", "Activated", "Requests (7d)", "Cached", "Bandwidth", "Visitors", "Page views"], $zones[0]);

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

        $tile = $this->getTile(null, $forbidden, $rejected, $failed);

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
     * The traffic of every day is summed per zone and shown in a short form;
     * a zone without analytics shows a dash in every traffic column.
     *
     * @return void
     */
    public function testShowsTheTrafficOfEachZone(): void
    {
        $zones   = [];
        $zones[] = $this->getZone("alpha.com", "active", false, "2025-01-15T10:00:00Z");
        $zones[] = $this->getZone("beta.dev", "pending", false, null);

        $traffic = [
            "alpha.com" => [
                $this->getDay(1000, 400, 1048576, 300, 120),
                $this->getDay(1500, 600, 524288, 200, 80),
            ],
        ];

        $rows = $this->getTile($this->getAnalytics($traffic), $this->getPage($zones, 1, 1))->getZones();

        $this->assertSame(["2.5K", "40%", "1.5 MB", "200", "500"], array_slice($rows[1], 4));
        $this->assertSame(["-", "-", "-", "-", "-"], array_slice($rows[2], 4));

    }//end testShowsTheTrafficOfEachZone()


    /**
     * When the analytics cannot be read the zones are still listed, with a
     * dash in every traffic column.
     *
     * @return void
     */
    public function testListsTheZonesWhenTheAnalyticsFail(): void
    {
        $page = $this->getPage([$this->getZone("alpha.com", "active", false, null)], 1, 1);
        $down = new Response(false, null, "Could not resolve host", -1, "https://api.cloudflare.com/client/v4/graphql", null);

        $rows = $this->getTile($down, $page)->getZones();

        $this->assertCount(2, $rows);
        $this->assertSame("active", $rows[1][1]);
        $this->assertSame(["-", "-", "-", "-", "-"], array_slice($rows[1], 4));

    }//end testListsTheZonesWhenTheAnalyticsFail()


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
