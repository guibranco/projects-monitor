<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\ShieldsIo;

/**
 * The Vercel tile: every project of the account with the state its visitors
 * get, read from the API and from the production URL itself (VercelProbe).
 */
class Vercel
{
    private const VERCEL_API_URL = "https://api.vercel.com/v10/projects?limit=100";

    private const STATE_PAUSED = "PAUSED";

    private const STATE_UNREACHABLE = "UNREACHABLE";

    private const STATE_UNKNOWN = "UNKNOWN";

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
     * The probe of the production URLs.
     *
     * @var VercelProbe|null
     */
    private $probe;


    /**
     * Reads the API token from the secrets file.
     *
     * @throws SecretsFileNotFoundException When the secrets file is missing.
     */
    public function __construct()
    {
        $config = new Configuration();
        $config->init();

        global $vercelToken;

        $secrets = __DIR__."/../secrets/vercel.secrets.php";
        if (file_exists($secrets) === false) {
            throw new SecretsFileNotFoundException("File not found: vercel.secrets.php");
        }

        include_once $secrets;

        $this->request   = new Request();
        $this->headers   = [];
        $this->headers[] = "Authorization: Bearer {$vercelToken}";
        $this->headers[] = "Accept: application/json";
        $this->headers[] = constant("USER_AGENT");

    }//end __construct()


    /**
     * The projects of the account, as the API lists them.
     *
     * @return object The decoded response.
     *
     * @throws RequestException When the API does not answer 200.
     */
    protected function fetchProjects()
    {
        $response = $this->request->get(self::VERCEL_API_URL, $this->headers);

        if ($response->getStatusCode() !== 200) {
            $error = $response->getBody();
            if ($response->getStatusCode() === -1) {
                $error = $response->getMessage();
            }

            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");
        }

        return json_decode($response->getBody());

    }//end fetchProjects()


    /**
     * The probe of the production URLs, built on first use.
     *
     * @return VercelProbe
     */
    protected function getProbe(): VercelProbe
    {
        if ($this->probe === null) {
            $this->probe = new VercelProbe(VercelProbe::CACHE_FILE);
        }

        return $this->probe;

    }//end getProbe()


    /**
     * The deployment that serves the project: the current production
     * deployment, falling back to the latest deployment of any target (a
     * preview included) when the project was never promoted to production.
     *
     * @param object $project A project as the API lists it.
     *
     * @return object|null
     */
    private function getServingDeployment($project)
    {
        return ($project->targets->production ?? $project->latestDeployments[0] ?? null);

    }//end getServingDeployment()


    /**
     * The URL of a deployment: its first alias, or its own hostname.
     *
     * @param object|null $deployment A deployment as the API lists it.
     *
     * @return string|null
     */
    private function getLink($deployment): ?string
    {
        $domain = ($deployment->alias[0] ?? $deployment->url ?? null);
        if ($domain === null) {
            return null;
        }

        return "https://{$domain}";

    }//end getLink()


    /**
     * What a visitor runs into, or null when the probe saw nothing wrong:
     * the platform's x-vercel-error code (DEPLOYMENT_DISABLED when the
     * plan's usage limits are exceeded, DEPLOYMENT_PAUSED,
     * DEPLOYMENT_NOT_FOUND, NOT_FOUND, FUNCTION_INVOCATION_FAILED...),
     * UNREACHABLE when the request itself failed, or the status code of a
     * 5xx answered by the project.
     *
     * @param array|null $probe The answer of the project's URL.
     *
     * @return string|null
     */
    private function getProblem(?array $probe): ?string
    {
        if ($probe === null) {
            return null;
        }

        if ($probe["statusCode"] < 0) {
            return self::STATE_UNREACHABLE;
        }

        if (empty($probe["vercelError"]) === false) {
            return $probe["vercelError"];
        }

        if ($probe["statusCode"] >= 500) {
            return sprintf("HTTP %d", $probe["statusCode"]);
        }

        return null;

    }//end getProblem()


    /**
     * The state shown for a project. The paused flag and the probe both win
     * over the deployment state, which stays READY while the platform
     * answers 503 DEPLOYMENT_PAUSED or 402 DEPLOYMENT_DISABLED for the
     * project.
     *
     * @param object      $project    A project as the API lists it.
     * @param object|null $deployment The deployment that serves it.
     * @param array|null  $probe      The answer of its URL.
     *
     * @return string
     */
    private function getState($project, $deployment, ?array $probe): string
    {
        if (($project->paused ?? false) === true) {
            return self::STATE_PAUSED;
        }

        $problem = $this->getProblem($probe);
        if ($problem === "DEPLOYMENT_PAUSED") {
            return self::STATE_PAUSED;
        }

        if ($problem !== null) {
            return $problem;
        }

        return ($deployment->readyState ?? self::STATE_UNKNOWN);

    }//end getState()


    /**
     * The icon of a state. Deployment states and UNKNOWN have their own;
     * anything else is a problem seen by the probe.
     *
     * @param string $state The state shown for a project.
     *
     * @return string
     */
    private function mapStatus(string $state): string
    {
        return match ($state) {
            "READY" => "✅",
            "ERROR" => "❌",
            "BUILDING" => "🔨",
            "QUEUED" => "⏳",
            "CANCELED" => "⏸",
            "INITIALIZING" => "🆕",
            "BLOCKED" => "🚫",
            self::STATE_UNKNOWN => "❓",
            self::STATE_PAUSED, "DEPLOYMENT_DISABLED" => "⛔",
            default => "🔥",
        };

    }//end mapStatus()


    /**
     * The badge colour of a state: red for every failure and for everything
     * the probe reported.
     *
     * @param string $state The state shown for a project.
     *
     * @return string
     */
    private function mapColor(string $state): string
    {
        return match ($state) {
            "READY" => "green",
            "BUILDING" => "yellow",
            "QUEUED", "INITIALIZING" => "blue",
            "CANCELED" => "gray",
            self::STATE_UNKNOWN => "lightgray",
            default => "red",
        };

    }//end mapColor()


    /**
     * The row of a project: its badge, linked to the production URL when
     * there is one, its state and the date of the serving deployment.
     *
     * @param object     $project A project as the API lists it.
     * @param array|null $probe   The answer of its URL.
     * @param ShieldsIo  $shields The badge builder.
     *
     * @return array
     */
    private function getRow($project, ?array $probe, ShieldsIo $shields): array
    {
        $deployment = $this->getServingDeployment($project);
        $state      = $this->getState($project, $deployment, $probe);
        $status     = $this->mapStatus($state);
        $color      = $this->mapColor($state);

        $badgeUrl  = $shields->generateBadgeUrl($status, $project->name, $color, "for-the-badge", "white", null);
        $badgeLink = "<img src='{$badgeUrl}' alt='{$project->name}' />";
        $link      = $this->getLink($deployment);
        if ($link !== null) {
            $badgeLink = "<a href='{$link}' title='{$state}' target='_blank' rel='noopener noreferrer'>{$badgeLink}</a>";
        }

        $lastDeployment = "-";
        if (isset($deployment->createdAt) === true) {
            $lastDeployment = date("H:i:s d/m/Y", intdiv($deployment->createdAt, 1000));
        }

        $row   = [];
        $row[] = $badgeLink;
        $row[] = $state;
        $row[] = $lastDeployment;

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
        $response = $this->fetchProjects();
        $projects = ($response->projects ?? []);

        usort($projects, fn ($first, $second) => strcasecmp($first->name, $second->name));

        $links = [];
        foreach ($projects as $project) {
            $link = $this->getLink($this->getServingDeployment($project));
            if ($link !== null) {
                $links[$project->name] = $link;
            }
        }

        $probes  = $this->getProbe()->probe($links);
        $shields = new ShieldsIo();

        $header   = [];
        $header[] = "Project";
        $header[] = "Status";
        $header[] = "Last Deployment";
        $result   = [$header];

        foreach ($projects as $project) {
            $result[] = $this->getRow($project, ($probes[$project->name] ?? null), $shields);
        }

        return $result;

    }//end getProjects()


}//end class
