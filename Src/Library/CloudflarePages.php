<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\ShieldsIo;

/**
 * The Cloudflare Pages tile: every Pages project of the account with the
 * state of its latest production deployment, read from the Cloudflare API.
 *
 * Shares cloudflare.secrets.php with the Cloudflare tile: the token needs
 * Account → Cloudflare Pages → Read on top of the zone permissions.
 */
class CloudflarePages extends CloudflareApiClient
{
    private const CLOUDFLARE_API_URL = "https://api.cloudflare.com/client/v4/accounts/%s/pages/projects";

    private const CLOUDFLARE_DASHBOARD_URL = "https://dash.cloudflare.com/%s/pages/view/%s";

    private const NO_DATA = "-";

    /**
     * The page size asked for. Pages answers at most 10 projects per page,
     * whatever is asked, so the loop follows total_pages either way.
     */
    private const PAGE_SIZE = 10;

    /**
     * A safety net against an API that keeps reporting more pages.
     */
    private const MAX_PAGES = 20;

    private const STATE_UNKNOWN = "UNKNOWN";

    private const STATE_NOT_DEPLOYED = "NOT_DEPLOYED";

    private const STAGE_DEPLOY = "deploy";

    private const STATUS_SUCCESS = "success";

    private const MESSAGE_NO_PROJECTS = "No Pages projects in this account. Sites created as Workers are not listed here.";

    private const MESSAGE_ERROR = "⚠️ Could not read Cloudflare Pages: %s";


    /**
     * One page of the account's Pages projects, as the API lists them.
     *
     * @param integer $page The page to fetch, starting at 1.
     *
     * @return object The decoded response.
     *
     * @throws RequestException When the API does not answer 200 with success.
     */
    protected function fetchProjects(int $page)
    {
        $parameters             = [];
        $parameters["per_page"] = self::PAGE_SIZE;
        $parameters["page"]     = $page;

        $url = sprintf(self::CLOUDFLARE_API_URL, rawurlencode($this->accountId));
        $url = sprintf("%s?%s", $url, http_build_query($parameters));

        return $this->getJson($url);

    }//end fetchProjects()


    /**
     * The number of pages of the listing: total_pages when the API reports
     * it, else derived from total_count and per_page, else one more page
     * for as long as pages come back full.
     *
     * @param object  $response The decoded answer of a page.
     * @param integer $page     The page that answered.
     *
     * @return integer
     */
    private function getTotalPages($response, int $page): int
    {
        $info = ($response->result_info ?? null);
        if (isset($info->total_pages) === true) {
            return (int) $info->total_pages;
        }

        $perPage = (int) ($info->per_page ?? self::PAGE_SIZE);
        if (isset($info->total_count) === true && $perPage > 0) {
            return (int) ceil(((int) $info->total_count / $perPage));
        }

        if (count(($response->result ?? [])) >= $perPage) {
            return ($page + 1);
        }

        return $page;

    }//end getTotalPages()


    /**
     * Every Pages project of the account, across all pages. A project seen
     * twice, because the listing shifted between two pages, is kept once.
     *
     * @return array
     */
    private function getAllProjects(): array
    {
        $projects = [];
        $page     = 1;

        do {
            $response = $this->fetchProjects($page);
            $result   = ($response->result ?? []);
            foreach ($result as $project) {
                $projects[($project->id ?? $project->name)] = $project;
            }

            $totalPages = $this->getTotalPages($response, $page);
            $page++;
        } while (empty($result) === false && $page <= $totalPages && $page <= self::MAX_PAGES);

        return array_values($projects);

    }//end getAllProjects()


    /**
     * The deployment that tells the project's state: the latest production
     * deployment, falling back to the latest deployment of any environment
     * (a preview included) when the project was never deployed to
     * production.
     *
     * @param object $project A project as the API lists it.
     *
     * @return object|null
     */
    private function getDeployment($project)
    {
        return ($project->canonical_deployment ?? $project->latest_deployment ?? null);

    }//end getDeployment()


    /**
     * The state shown for a deployment, from its latest stage: SUCCESS once
     * the deploy stage succeeded, the stage and its status otherwise
     * (BUILD_FAILURE, BUILD_ACTIVE, QUEUED_IDLE, DEPLOY_CANCELED...).
     *
     * @param object|null $deployment The deployment.
     *
     * @return string
     */
    private function getState($deployment): string
    {
        if ($deployment === null) {
            return self::STATE_NOT_DEPLOYED;
        }

        $stage  = ($deployment->latest_stage->name ?? null);
        $status = ($deployment->latest_stage->status ?? null);
        if (empty($status) === true) {
            return self::STATE_UNKNOWN;
        }

        if ($status === self::STATUS_SUCCESS && $stage === self::STAGE_DEPLOY) {
            return "SUCCESS";
        }

        if (empty($stage) === true) {
            return strtoupper($status);
        }

        return strtoupper("{$stage}_{$status}");

    }//end getState()


    /**
     * The status part of a state: the text after the stage name. A stage
     * that succeeded before the deploy stage (BUILD_SUCCESS...) means the
     * deployment is still going, so it counts as ACTIVE.
     *
     * @param string $state The state shown for a project.
     *
     * @return string
     */
    private function getStatus(string $state): string
    {
        $position = strrpos($state, "_");
        if ($position === false || $state === self::STATE_NOT_DEPLOYED) {
            return $state;
        }

        $status = substr($state, ($position + 1));
        if ($status === "SUCCESS") {
            return "ACTIVE";
        }

        return $status;

    }//end getStatus()


    /**
     * The icon of a state.
     *
     * @param string $state The state shown for a project.
     *
     * @return string
     */
    private function mapStatus(string $state): string
    {
        return match ($this->getStatus($state)) {
            "SUCCESS" => "✅",
            "FAILURE" => "❌",
            "ACTIVE" => "🔨",
            "IDLE" => "⏳",
            "CANCELED" => "⏸",
            "SKIPPED" => "⏭",
            self::STATE_NOT_DEPLOYED => "💤",
            default => "❓",
        };

    }//end mapStatus()


    /**
     * The badge colour of a state.
     *
     * @param string $state The state shown for a project.
     *
     * @return string
     */
    private function mapColor(string $state): string
    {
        return match ($this->getStatus($state)) {
            "SUCCESS" => "green",
            "FAILURE" => "red",
            "ACTIVE" => "yellow",
            "IDLE" => "blue",
            "CANCELED", "SKIPPED", self::STATE_NOT_DEPLOYED => "gray",
            default => "lightgray",
        };

    }//end mapColor()


    /**
     * The URL of a project: its first custom domain, falling back to its
     * *.pages.dev subdomain, then to its page on the dashboard.
     *
     * @param object $project A project as the API lists it.
     *
     * @return string
     */
    private function getLink($project): string
    {
        foreach (($project->domains ?? []) as $domain) {
            if (str_ends_with($domain, ".pages.dev") === false) {
                return "https://{$domain}";
            }
        }

        if (empty($project->subdomain) === false) {
            return "https://{$project->subdomain}";
        }

        return sprintf(self::CLOUDFLARE_DASHBOARD_URL, rawurlencode($this->accountId), rawurlencode($project->name));

    }//end getLink()


    /**
     * The date of a deployment: when its latest stage ended, or when it was
     * created while it is still running.
     *
     * @param object|null $deployment The deployment.
     *
     * @return string
     */
    private function getLastDeployment($deployment): string
    {
        $date = ($deployment->latest_stage->ended_on ?? $deployment->created_on ?? null);
        if (empty($date) === true) {
            return self::NO_DATA;
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return self::NO_DATA;
        }

        return date("H:i:s d/m/Y", $timestamp);

    }//end getLastDeployment()


    /**
     * The row of a project: its badge, linked to its URL, its state, its
     * production branch and the date of its latest production deployment.
     *
     * @param object    $project A project as the API lists it.
     * @param ShieldsIo $shields The badge builder.
     *
     * @return array
     */
    private function getRow($project, ShieldsIo $shields): array
    {
        $deployment = $this->getDeployment($project);
        $state      = $this->getState($deployment);
        $status     = $this->mapStatus($state);
        $color      = $this->mapColor($state);
        $name       = htmlspecialchars($project->name, ENT_QUOTES);
        $link       = htmlspecialchars($this->getLink($project), ENT_QUOTES);

        $badgeUrl  = $shields->generateBadgeUrl($status, $project->name, $color, "for-the-badge", "white", null);
        $image     = "<img src='{$badgeUrl}' alt='{$name}' />";
        $badgeLink = "<a href='{$link}' title='{$state}' target='_blank' rel='noopener noreferrer'>{$image}</a>";

        $branch = ($deployment->deployment_trigger->metadata->branch ?? $project->production_branch ?? self::NO_DATA);

        $row   = [];
        $row[] = $badgeLink;
        $row[] = $state;
        $row[] = htmlspecialchars($branch, ENT_QUOTES);
        $row[] = $this->getLastDeployment($deployment);

        return $row;

    }//end getRow()


    /**
     * The rows of the tile: a header, then one row per project sorted by
     * name.
     *
     * @return array
     */
    public function getProjects(): array
    {
        $projects = $this->getAllProjects();

        usort($projects, fn ($first, $second) => strcasecmp($first->name, $second->name));

        $shields = new ShieldsIo();

        $result = [$this->getHeader()];
        if (empty($projects) === true) {
            $result[] = $this->getMessageRow(self::MESSAGE_NO_PROJECTS);
            return $result;
        }

        foreach ($projects as $project) {
            $result[] = $this->getRow($project, $shields);
        }

        return $result;

    }//end getProjects()


    /**
     * The rows of the tile, or, when the API cannot be read, the header and
     * one row with the reason. The failure is reported, as an uncaught one
     * would be, so it still reaches the messages table and LogStream.
     *
     * @param ErrorReporter $reporter The reporter of the failure.
     *
     * @return array
     */
    public function getProjectsOrError(ErrorReporter $reporter): array
    {
        try {
            return $this->getProjects();
        } catch (RequestException $exception) {
            $reason = (new ErrorText())->summarize($exception->getMessage());

            $context              = [];
            $context["reason"]    = $reason;
            $context["exception"] = $exception;
            $reporter->error("Cloudflare Pages could not be read: {reason}", $context, "api");

            $message = sprintf(self::MESSAGE_ERROR, htmlspecialchars($reason, ENT_QUOTES));

            $rows   = [];
            $rows[] = $this->getHeader();
            $rows[] = $this->getMessageRow($message);

            return $rows;
        }

    }//end getProjectsOrError()


    /**
     * The header row of the tile.
     *
     * @return array
     */
    private function getHeader(): array
    {
        $header   = [];
        $header[] = "Project";
        $header[] = "Status";
        $header[] = "Branch";
        $header[] = "Last Deployment";

        return $header;

    }//end getHeader()


    /**
     * A row carrying a message in its first column.
     *
     * @param string $message The message.
     *
     * @return array
     */
    private function getMessageRow(string $message): array
    {
        $row   = [];
        $row[] = $message;
        $row[] = self::NO_DATA;
        $row[] = self::NO_DATA;
        $row[] = self::NO_DATA;

        return $row;

    }//end getMessageRow()


}//end class
