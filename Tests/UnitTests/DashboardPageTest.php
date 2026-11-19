<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\DashboardPage;

/**
 * Tests for DashboardPage: the page tabs and the escaping of the title.
 */
class DashboardPageTest extends TestCase
{


    /**
     * Every page has a tab, the active one is marked, and each tab names the
     * attention domains it covers.
     *
     * @return void
     */
    public function testRenderTabs(): void
    {
        $html = (new DashboardPage("ops", "Ops"))->renderTabs();

        $this->assertSame(7, substr_count($html, "class=\"nav-item\""));
        $this->assertSame(1, substr_count($html, "aria-current=\"page\""));
        $this->assertStringContainsString("page-tab active\" aria-current=\"page\" href=\"ops.php\" data-domains=\"ops domains\"", $html);
        $this->assertStringContainsString("href=\"github.php\" data-domains=\"ci github\"", $html);
        $this->assertStringContainsString("href=\"workflow-runs.php\" data-domains=\"ci\"", $html);

    }//end testRenderTabs()


    /**
     * An unknown page falls back to the overview, and the title is escaped.
     *
     * @return void
     */
    public function testUnknownPageAndEscaping(): void
    {
        $page = new DashboardPage("nope", "<b>Errors</b>");

        $this->assertSame("overview", $page->getActivePage());
        $this->assertSame("&lt;b&gt;Errors&lt;/b&gt;", $page->getTitle());
        $this->assertStringContainsString("page-tab active\" aria-current=\"page\" href=\"index.php\"", $page->renderTabs());

    }//end testUnknownPageAndEscaping()


    /**
     * Every page in PAGES has a PHP file.
     *
     * @return void
     */
    public function testEveryPageExists(): void
    {
        foreach (DashboardPage::PAGES as $definition) {
            $file = explode("|", $definition)[0];
            $this->assertFileExists(sprintf("%s/../../Src/%s", __DIR__, $file));
        }

    }//end testEveryPageExists()


}//end class
