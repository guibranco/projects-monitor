<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * One page of the dashboard: its title, which tab is active, and the
 * navigation tabs to every page. Each tab names the attention domains it
 * covers, so the tab can show a status dot.
 */
class DashboardPage
{
    public const PAGES = [
                          "overview"  => "index.php|Overview|bi-speedometer|",
                          "pipeline"  => "pipeline.php|Pipeline|bi-diagram-3|pipeline",
                          "errors"    => "errors.php|Errors|bi-bug|errors",
                          "github"    => "github.php|GitHub work|bi-github|ci github",
                          "workflows" => "workflow-runs.php|Workflow runs|bi-gear-wide-connected|ci",
                          "ops"       => "ops.php|Ops|bi-hdd-rack|ops domains",
                          "stats"     => "stats.php|Stats|bi-graph-up|",
                         ];

    /**
     * The page's key in PAGES.
     *
     * @var string
     */
    private string $activePage;

    /**
     * The page's title.
     *
     * @var string
     */
    private string $title;


    /**
     * Creates the page.
     *
     * @param string $activePage The page's key in PAGES.
     * @param string $title      The page's title.
     */
    public function __construct(string $activePage, string $title)
    {
        $this->activePage = "overview";
        if (isset(self::PAGES[$activePage]) === true) {
            $this->activePage = $activePage;
        }

        $this->title = $title;

    }//end __construct()


    /**
     * The page's key, HTML-escaped.
     *
     * @return string
     */
    public function getActivePage(): string
    {
        return htmlspecialchars($this->activePage, ENT_QUOTES);

    }//end getActivePage()


    /**
     * The page's title, HTML-escaped.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return htmlspecialchars($this->title, ENT_QUOTES);

    }//end getTitle()


    /**
     * The navigation tabs, the active one marked.
     *
     * @return string
     */
    public function renderTabs(): string
    {
        $html = "";
        foreach (self::PAGES as $key => $definition) {
            list($href, $label, $icon, $domains) = explode("|", $definition);

            $active = "";
            if ($key === $this->activePage) {
                $active = " active\" aria-current=\"page";
            }

            $html .= sprintf(
                "<li class=\"nav-item\"><a class=\"nav-link page-tab%s\" href=\"%s\" data-domains=\"%s\" title=\"%s\"><i class=\"bi %s\"></i><span class=\"d-none d-lg-inline ms-1\">%s</span><span class=\"page-tab-dot\" aria-hidden=\"true\"></span></a></li>",
                $active,
                $href,
                $domains,
                $label,
                $icon,
                $label
            );
        }

        return $html;

    }//end renderTabs()


}//end class
