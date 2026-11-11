<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use GuiBranco\ProjectsMonitor\Library\Render;
use GuiBranco\ProjectsMonitor\Library\RequestException;
use GuiBranco\ProjectsMonitor\Library\SecretsFileNotFoundException;

/**
 * Tests the Render tile: the state shown for each service comes from the
 * suspended flag, then from the status of its latest deploy.
 */
class RenderTest extends TestCase
{
    private const API_URL = "https://api.render.com/v1/services?limit=100";


    /**
     * A service as GET /v1/services lists it, trimmed to the fields the tile reads.
     *
     * @param string      $id        The service ID.
     * @param string      $name      The service name.
     * @param string      $suspended "suspended" or "not_suspended".
     * @param string|null $url       The public URL, or null for services without one.
     *
     * @return object
     */
    private function getService(string $id, string $name, string $suspended, ?string $url): object
    {
        $service = new stdClass();

        $service->id             = $id;
        $service->name           = $name;
        $service->suspended      = $suspended;
        $service->dashboardUrl   = "https://dashboard.render.com/web/{$id}";
        $service->serviceDetails = new stdClass();
        if ($url !== null) {
            $service->serviceDetails->url = $url;
        }

        return $service;

    }//end getService()


    /**
     * A deploy as GET /v1/services/{id}/deploys lists it.
     *
     * @param string      $status     The deploy status.
     * @param string|null $finishedAt When it finished, null while running.
     *
     * @return object
     */
    private function getDeploy(string $status, ?string $finishedAt): object
    {
        $deploy = new stdClass();

        $deploy->status    = $status;
        $deploy->createdAt = "2026-11-10T10:00:00Z";
        if ($finishedAt !== null) {
            $deploy->finishedAt = $finishedAt;
        }

        return $deploy;

    }//end getDeploy()


    /**
     * The tile with both API calls mocked.
     *
     * @param array $services The services listed.
     * @param array $deploys  Latest deploy keyed by service ID.
     *
     * @return Render
     */
    private function getRender(array $services, array $deploys): Render
    {
        $builder = $this->getMockBuilder(Render::class);
        $builder->disableOriginalConstructor();
        $builder->onlyMethods(['fetchServices', 'fetchLatestDeploys']);

        $render = $builder->getMock();
        $render->method('fetchServices')->willReturn($services);
        $render->method('fetchLatestDeploys')->willReturn($deploys);

        return $render;

    }//end getRender()


    /**
     * Sets a private property of the tile (test-only reflection).
     *
     * @param object $render   The tile to modify.
     * @param string $property The property name.
     * @param mixed  $value    The value to set.
     *
     * @return void
     */
    private function setPrivateProperty(object $render, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty(Render::class, $property);
        $reflection->setValue($render, $value);

    }//end setPrivateProperty()


    /**
     * One row per service sorted by name, after the header; the state is
     * the upper-cased deploy status, SUSPENDED for a suspended service and
     * UNKNOWN for a service never deployed.
     *
     * @return void
     */
    public function testShowsTheStateOfEveryServiceSortedByName(): void
    {
        $services   = [];
        $services[] = $this->getService("srv-3", "worker", "not_suspended", null);
        $services[] = $this->getService("srv-1", "api", "not_suspended", "https://api.onrender.com");
        $services[] = $this->getService("srv-2", "Paused-site", "suspended", "https://paused-site.onrender.com");
        $services[] = $this->getService("srv-4", "new-service", "not_suspended", null);
        $services[] = $this->getService("srv-5", "broken", "not_suspended", "https://broken.onrender.com");

        $deploys          = [];
        $deploys["srv-1"] = $this->getDeploy("live", "2026-11-10T10:05:00Z");
        $deploys["srv-2"] = $this->getDeploy("live", "2026-11-01T10:05:00Z");
        $deploys["srv-3"] = $this->getDeploy("build_in_progress", null);
        $deploys["srv-5"] = $this->getDeploy("build_failed", "2026-11-10T11:00:00Z");

        $rows = $this->getRender($services, $deploys)->getServices();

        $this->assertCount(6, $rows);
        $this->assertEquals(["Service", "Status", "Last Deployment"], $rows[0]);

        $states = array_map(fn ($row) => $row[1], array_slice($rows, 1));
        $this->assertEquals(["LIVE", "BUILD_FAILED", "UNKNOWN", "SUSPENDED", "BUILD_IN_PROGRESS"], $states);

        $this->assertStringContainsString("href='https://api.onrender.com'", $rows[1][0]);
        $this->assertStringContainsString("green", $rows[1][0]);
        $this->assertEquals(date("H:i:s d/m/Y", strtotime("2026-11-10T10:05:00Z")), $rows[1][2]);

        $this->assertStringContainsString("red", $rows[2][0]);

        $this->assertStringContainsString("href='https://dashboard.render.com/web/srv-4'", $rows[3][0]);
        $this->assertEquals("-", $rows[3][2]);

        $this->assertStringContainsString("red", $rows[4][0]);
        $this->assertStringContainsString("title='SUSPENDED'", $rows[4][0]);

        $this->assertStringContainsString("yellow", $rows[5][0]);
        $this->assertEquals(date("H:i:s d/m/Y", strtotime("2026-11-10T10:00:00Z")), $rows[5][2]);

    }//end testShowsTheStateOfEveryServiceSortedByName()


    /**
     * The services are read from every page of the API, and anything but a
     * 200 fails the tile with the status code and the body, or the
     * transport error, in the message.
     *
     * @return void
     */
    public function testFetchesTheServicesFromTheApiOrFails(): void
    {
        $builder = $this->getMockBuilder(Render::class);
        $builder->disableOriginalConstructor();
        $builder->onlyMethods(['fetchLatestDeploys']);

        $render = $builder->getMock();
        $render->method('fetchLatestDeploys')->willReturn([]);

        $listed    = new Response(true, "[{\"cursor\": \"abc\", \"service\": {\"id\": \"srv-1\", \"name\": \"api\", \"suspended\": \"not_suspended\"}}]", "", 200, self::API_URL, []);
        $forbidden = new Response(true, "{\"message\": \"unauthorized\"}", "", 401, self::API_URL, []);
        $failed    = new Response(false, null, "Could not resolve host", -1, self::API_URL, null);

        $request = $this->createMock(Request::class);
        $request->method('get')->willReturnOnConsecutiveCalls($listed, $forbidden, $failed);

        $this->setPrivateProperty($render, 'request', $request);
        $this->setPrivateProperty($render, 'headers', []);

        $rows = $render->getServices();
        $this->assertCount(2, $rows);
        $this->assertEquals("UNKNOWN", $rows[1][1]);

        try {
            $render->getServices();
            $this->fail("A 401 must fail the tile.");
        } catch (RequestException $exception) {
            $this->assertStringContainsString("Code: 401", $exception->getMessage());
            $this->assertStringContainsString("unauthorized", $exception->getMessage());
        }

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("Could not resolve host");
        $render->getServices();

    }//end testFetchesTheServicesFromTheApiOrFails()


    /**
     * Without the secrets file the tile cannot be built.
     *
     * @return void
     */
    public function testRequiresTheSecretsFile(): void
    {
        if (file_exists(__DIR__."/../../Src/secrets/render.secrets.php") === true) {
            $this->markTestSkipped("The secrets file exists in this checkout.");
        }

        $this->expectException(SecretsFileNotFoundException::class);
        new Render();

    }//end testRequiresTheSecretsFile()


}//end class
