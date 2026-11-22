<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\ShieldsIo;

/**
 * The Cloudflare Pages tile: every Pages project of the account with the
 * state of its latest production deployment, read from the Cloudflare API.
 *
 * Shares cloudflare.secrets.php with the Cloudflare tile: the token needs
 * Account → Cloudflare Pages → Read on top of the zone permissions.
 */
class CloudflarePages
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

    /**
     * The HTTP client of the API.
     *
     * @var Request
     */
    private $request;

    /**
     * The headers of every API request.
     *
     * @var array
     */
    private $headers;

    /**
     * The account whose projects are listed.
     *
     * @var string
     */
    private $accountId;


    /**
     * Reads the account ID and the API token from the secrets file.
     *
     * @throws SecretsFileNotFoundException When the secrets file is missing.
     */
    public function __construct()
    {
        $config = new Configuration();
        $config->init();

        global $cloudflareAccountId, $cloudflareApiToken;

        $secrets = __DIR__."/../secrets/cloudflare.secrets.php";
        if (file_exists($secrets) === false) {
            throw new SecretsFileNotFoundException("File not found: cloudflare.secrets.php");
        }

        include_once $secrets;

        $this->accountId = $cloudflareAccountId;
        $this->request   = new Request();
        $this->headers   = [];
        $this->headers[] = "Authorization: Bearer {$cloudflareApiToken}";
        $this->headers[] = "Accept: application/json";
        $this->headers[] = constant("USER_AGENT");

    }//end __construct()


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

        $url      = sprintf(self::CLOUDFLARE_API_URL, rawurlencode($this->accountId));
        $url      = sprintf("%s?%s", $url, http_build_query($parameters));
        $response = $this->request->get($url, $this->headers);

        if ($response->getStatusCode() !== 200) {
            $error = $response->getBody();
            if ($response->getStatusCode() === -1) {
                $error = $response->getMessage();
            }

            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");
        }

        $body = json_decode($response->getBody());
        if (($body->success ?? false) !== true) {
            $errors = json_encode(($body->errors ?? []));
            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$errors}");
        }

        return $body;

    }//end fetchProjects()


    /**
     * Every Pages project of the account, across all pages.
     *
     * @return array
     */
    private function getAllProjects(): array
    {
        $projects = [];
        $page     = 1;

        do {
            $response   = $this->fetchProjects($page);
            $result     = ($response->result ?? []);
            $projects   = array_merge($projects, $result);
            $totalPages = (int) ($response->result_info->total_pages ?? 1);
            $page++;
        } while (empty($result) === false && $page <= $totalPages && $page <= self::MAX_PAGES);

        return $projects;

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
     * The status part of a state: the text after the stage name.
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

        return substr($state, ($position + 1));

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

        $header   = [];
        $header[] = "Project";
        $header[] = "Status";
        $header[] = "Branch";
        $header[] = "Last Deployment";
        $result   = [$header];

        foreach ($projects as $project) {
            $result[] = $this->getRow($project, $shields);
        }

        return $result;

    }//end getProjects()


}//end class
