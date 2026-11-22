<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\CloudflarePages;

/**
 * Tests the Cloudflare Pages tile: every project of the account, across all
 * pages, sorted by name, with the state of its latest production deployment.
 */
class CloudflarePagesTest extends TestCase
{
    private const ACCOUNT_ID = "account123";


    /**
     * A deployment as the projects endpoint embeds it, trimmed to the fields
     * the tile reads.
     *
     * @param string      $stage   The latest stage name.
     * @param string      $status  The latest stage status.
     * @param string|null $endedOn When the stage ended, null while running.
     * @param string|null $branch  The branch deployed.
     *
     * @return object
     */
    private function getDeployment(string $stage, string $status, ?string $endedOn, ?string $branch=null): object
    {
        $deployment                       = new stdClass();
        $deployment->created_on           = "2026-11-20T10:00:00Z";
        $deployment->latest_stage         = new stdClass();
        $deployment->latest_stage->name   = $stage;
        $deployment->latest_stage->status = $status;
        $deployment->latest_stage->ended_on = $endedOn;

        if ($branch !== null) {
            $deployment->deployment_trigger = new stdClass();
            $deployment->deployment_trigger->metadata         = new stdClass();
            $deployment->deployment_trigger->metadata->branch = $branch;
        }

        return $deployment;

    }//end getDeployment()


    /**
     * A project as GET /accounts/{id}/pages/projects lists it.
     *
     * @param string      $name       The project name.
     * @param object|null $canonical  The latest production deployment.
     * @param object|null $latest     The latest deployment of any environment.
     * @param array       $domains    The project's domains.
     *
     * @return object
     */
    private function getProject(string $name, ?object $canonical, ?object $latest=null, array $domains=[]): object
    {
        $project                    = new stdClass();
        $project->name              = $name;
        $project->subdomain         = "{$name}.pages.dev";
        $project->domains           = $domains;
        $project->production_branch = "main";
        $project->canonical_deployment = $canonical;
        $project->latest_deployment    = $latest;

        return $project;

    }//end getProject()


    /**
     * One page of the projects endpoint.
     *
     * @param array   $projects   The projects of the page.
     * @param integer $totalPages The total pages reported.
     *
     * @return object
     */
    private function getPage(array $projects, int $totalPages): object
    {
        $page          = new stdClass();
        $page->success = true;
        $page->result  = $projects;
        $page->result_info = new stdClass();
        $page->result_info->total_pages = $totalPages;

        return $page;

    }//end getPage()


    /**
     * The tile with the API call mocked to answer the given pages in order.
     *
     * @param array $pages The pages, the first one being page 1.
     *
     * @return CloudflarePages
     */
    private function getTile(array $pages): CloudflarePages
    {
        $builder = $this->getMockBuilder(CloudflarePages::class);
        $builder->disableOriginalConstructor();
        $builder->onlyMethods(['fetchProjects']);

        $tile = $builder->getMock();
        $tile->method('fetchProjects')->willReturnCallback(fn (int $page) => $pages[($page - 1)]);

        $reflection = new ReflectionProperty(CloudflarePages::class, 'accountId');
        $reflection->setValue($tile, self::ACCOUNT_ID);

        return $tile;

    }//end getTile()


    /**
     * Every page is read, the rows come sorted by name after the header, and
     * the state comes from the latest stage of the production deployment.
     *
     * @return void
     */
    public function testListsEveryProjectSortedWithItsState(): void
    {
        $pages   = [];
        $pages[] = $this->getPage(
            [
                $this->getProject("zeta", $this->getDeployment("deploy", "success", "2026-11-21T12:00:00Z", "main")),
                $this->getProject("alpha", $this->getDeployment("build", "failure", "2026-11-21T11:00:00Z")),
            ],
            2
        );
        $pages[] = $this->getPage(
            [
                $this->getProject("mid", $this->getDeployment("build", "active", null)),
                $this->getProject("never", null),
            ],
            2
        );

        $rows = $this->getTile($pages)->getProjects();

        $this->assertSame(["Project", "Status", "Branch", "Last Deployment"], $rows[0]);
        $this->assertCount(5, $rows);
        $this->assertStringContainsString("alt='alpha'", $rows[1][0]);
        $this->assertSame("BUILD_FAILURE", $rows[1][1]);
        $this->assertStringContainsString("red", $rows[1][0]);
        $this->assertSame("main", $rows[1][2]);
        $this->assertSame("BUILD_ACTIVE", $rows[2][1]);
        $this->assertSame(date("H:i:s d/m/Y", strtotime("2026-11-20T10:00:00Z")), $rows[2][3]);
        $this->assertSame("NOT_DEPLOYED", $rows[3][1]);
        $this->assertSame("-", $rows[3][3]);
        $this->assertSame("SUCCESS", $rows[4][1]);
        $this->assertStringContainsString("green", $rows[4][0]);

    }//end testListsEveryProjectSortedWithItsState()


    /**
     * A project never deployed to production falls back to its latest
     * deployment of any environment.
     *
     * @return void
     */
    public function testFallsBackToLatestDeployment(): void
    {
        $project = $this->getProject("preview", null, $this->getDeployment("queued", "idle", null));

        $rows = $this->getTile([$this->getPage([$project], 1)])->getProjects();

        $this->assertSame("QUEUED_IDLE", $rows[1][1]);

    }//end testFallsBackToLatestDeployment()


    /**
     * The badge links to the first custom domain, and to the pages.dev
     * subdomain when there is none.
     *
     * @return void
     */
    public function testLinksToCustomDomainThenSubdomain(): void
    {
        $deployment = $this->getDeployment("deploy", "success", "2026-11-21T12:00:00Z");
        $projects   = [];
        $projects[] = $this->getProject("custom", $deployment, null, ["custom.pages.dev", "www.example.com"]);
        $projects[] = $this->getProject("plain", $deployment, null, ["plain.pages.dev"]);

        $rows = $this->getTile([$this->getPage($projects, 1)])->getProjects();

        $this->assertStringContainsString("href='https://www.example.com'", $rows[1][0]);
        $this->assertStringContainsString("href='https://plain.pages.dev'", $rows[2][0]);

    }//end testLinksToCustomDomainThenSubdomain()


}//end class
