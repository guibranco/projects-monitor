<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\WorkflowRunsPresenter;

/**
 * Tests the Workflow Runs Management table rendering: badge vocabulary
 * matching the dashboard's Workflow Runs table, the workflow path link
 * and the Retry / Delete action buttons.
 */
class WorkflowRunsPresenterTest extends TestCase
{


    /**
     * Builds one run exactly as the webhooks API's GET /workflow-runs
     * returns it.
     *
     * @return array
     */
    protected function getWebhooksRun(): array
    {
        $run = [];

        $run['sequence']   = 4711;
        $run['created_at'] = '2026-10-20 09:00:00';
        $run['updated_at'] = '2026-10-20 09:05:00';
        $run['owner']      = 'guibranco';
        $run['repo']       = 'projects-monitor';
        $run['private']    = false;
        $run['action']     = 'completed';

        $run['workflow_run_id'] = 123456789;
        $run['name']            = 'Build';
        $run['display_title']   = 'feat: add badges';
        $run['path']            = '.github/workflows/build.yml';
        $run['run_number']      = 42;
        $run['event']           = 'pull_request';
        $run['status']          = 'completed';
        $run['conclusion']      = 'failure';
        $run['actor_login']     = 'dependabot[bot]';
        $run['run_attempt']     = 2;

        return $run;

    }//end getWebhooksRun()


    /**
     * Renders one run through the presenter and returns its table row.
     *
     * @param array $run The run to render.
     *
     * @return array
     */
    protected function getRow(array $run): array
    {
        $presenter = new WorkflowRunsPresenter();
        $table     = $presenter->toTable([$run]);

        return $table[1];

    }//end getRow()


    /**
     * The header row comes first and the row carries one cell per column.
     *
     * @return void
     */
    public function testTableShape(): void
    {
        $presenter = new WorkflowRunsPresenter();
        $table     = $presenter->toTable([$this->getWebhooksRun()]);

        $this->assertCount(2, $table);
        $this->assertSame(WorkflowRunsPresenter::TABLE_HEADER, $table[0]);
        $this->assertCount(count(WorkflowRunsPresenter::TABLE_HEADER), $table[1]);
        $this->assertSame([WorkflowRunsPresenter::TABLE_HEADER], $presenter->toTable([]));

    }//end testTableShape()


    /**
     * The repository cell is a GitHub-logo badge linking to the repository,
     * with a lock marker only for private repositories.
     *
     * @return void
     */
    public function testRepositoryBadge(): void
    {
        $cell = $this->getRow($this->getWebhooksRun())[0];

        $this->assertStringContainsString('href="https://github.com/guibranco/projects-monitor"', $cell);
        $this->assertStringContainsString('logo=github', $cell);
        $this->assertStringContainsString('style=flat', $cell);
        $this->assertStringNotContainsString('bi-lock-fill', $cell);

        $private = $this->getWebhooksRun();

        $private['private'] = true;

        $this->assertStringContainsString('bi-lock-fill', $this->getRow($private)[0]);

    }//end testRepositoryBadge()


    /**
     * The workflow badge carries the zero-padded run number, the workflow
     * name with the attempt, links to the run and uses the display title as
     * its tooltip.
     *
     * @return void
     */
    public function testWorkflowBadge(): void
    {
        $cell = $this->getRow($this->getWebhooksRun())[1];

        $this->assertStringContainsString('href="https://github.com/guibranco/projects-monitor/actions/runs/123456789"', $cell);
        $this->assertStringContainsString('title="feat: add badges"', $cell);
        $this->assertStringContainsString('badge/♯00042-Build_(2)-', $cell);
        $this->assertStringContainsString('style=for-the-badge', $cell);
        $this->assertStringContainsString('labelColor=black', $cell);

    }//end testWorkflowBadge()


    /**
     * Long workflow names are cut to 50 characters with an ellipsis.
     *
     * @return void
     */
    public function testWorkflowNameIsTruncated(): void
    {
        $run = $this->getWebhooksRun();

        $run['name'] = str_repeat('x', 60);

        $cell = $this->getRow($run)[1];

        $this->assertStringContainsString(str_repeat('x', 50).'...', $cell);
        $this->assertStringNotContainsString(str_repeat('x', 51), $cell);

    }//end testWorkflowNameIsTruncated()


    /**
     * The path cell shows the workflow file and links to the workflow's page
     * under the repository's Actions tab.
     *
     * @return void
     */
    public function testPathLinksToTheWorkflowPage(): void
    {
        $cell = $this->getRow($this->getWebhooksRun())[2];

        $this->assertStringContainsString('>.github/workflows/build.yml</a>', $cell);
        $this->assertStringContainsString('href="https://github.com/guibranco/projects-monitor/actions/workflows/build.yml"', $cell);

        $run = $this->getWebhooksRun();

        $run['path'] = '';

        $this->assertSame('—', $this->getRow($run)[2]);

    }//end testPathLinksToTheWorkflowPage()


    /**
     * Event, status and conclusion are iconed badges coloured like the
     * dashboard's Workflow Runs table.
     *
     * @return void
     */
    public function testStateBadges(): void
    {
        $cells = $this->getRow($this->getWebhooksRun());

        $this->assertStringContainsString('badge/🔀-pull__request-blue', $cells[3]);
        $this->assertStringContainsString('badge/✅-completed-green', $cells[4]);
        $this->assertStringContainsString('badge/❌-failure-red', $cells[5]);

    }//end testStateBadges()


    /**
     * A run without a conclusion shows a yellow "none" badge; a queued run
     * is red; an unknown value falls back to the generic icon.
     *
     * @return void
     */
    public function testStateBadgeFallbacks(): void
    {
        $run = $this->getWebhooksRun();

        $run['status']     = 'queued';
        $run['conclusion'] = '';
        $run['event']      = 'something_new';

        $cells = $this->getRow($run);

        $this->assertStringContainsString('badge/⏰-queued-red', $cells[4]);
        $this->assertStringContainsString('badge/⭕-none-yellow', $cells[5]);
        $this->assertStringContainsString('badge/⭕-something__new-blue', $cells[3]);

    }//end testStateBadgeFallbacks()


    /**
     * The actor is a social-style GitHub badge linking to the profile.
     *
     * @return void
     */
    public function testActorBadge(): void
    {
        $cell = $this->getRow($this->getWebhooksRun())[6];

        $this->assertStringContainsString('href="https://github.com/dependabot%5Bbot%5D"', $cell);
        $this->assertStringContainsString('style=social', $cell);
        $this->assertStringContainsString('logo=github', $cell);

    }//end testActorBadge()


    /**
     * Retry is enabled only for retryable conclusions; Delete is always on.
     *
     * @return void
     */
    public function testActionButtons(): void
    {
        $cell = $this->getRow($this->getWebhooksRun())[8];

        $this->assertStringContainsString('data-action="retry-workflow-run" data-workflow-run-id="123456789"', $cell);
        $this->assertStringNotContainsString('disabled', $cell);
        $this->assertStringContainsString('data-action="delete-workflow-run" data-workflow-run-id="123456789"', $cell);

        $run = $this->getWebhooksRun();

        $run['conclusion'] = 'success';

        $this->assertStringContainsString('disabled', $this->getRow($run)[8]);

    }//end testActionButtons()


    /**
     * Values are escaped before they reach attributes and text.
     *
     * @return void
     */
    public function testEscapesValues(): void
    {
        $run = $this->getWebhooksRun();

        $run['display_title'] = '<script>alert("x")</script>';
        $run['path']          = '.github/workflows/<b>.yml';

        $cells = $this->getRow($run);

        $this->assertStringNotContainsString('<script>', $cells[1]);
        $this->assertStringContainsString('&lt;script&gt;', $cells[1]);
        $this->assertStringContainsString('&lt;b&gt;', $cells[2]);

    }//end testEscapesValues()


}//end class
