<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\ShieldsIo;

/**
 * The Vercel tile: every project of the account with the state Vercel
 * serves it in, read from the API and from its *.vercel.app URL
 * (VercelProbe). A custom domain is probed too, only to tell when its DNS
 * sends visitors to another host (DOMAIN_ELSEWHERE) instead of Vercel.
 */
class Vercel
{
    private const VERCEL_API_URL = "https://api.vercel.com/v10/projects?limit=100";

    private const STATE_PAUSED = "PAUSED";

    private const STATE_UNREACHABLE = "UNREACHABLE";

    private const STATE_UNKNOWN = "UNKNOWN";

    private const STATE_DOMAIN_ELSEWHERE = "DOMAIN_ELSEWHERE";

    private const VERCEL_SUFFIX = ".vercel.app";

    private const KEY_VERCEL = "vercel:";

    private const KEY_DOMAIN = "domain:";

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
     * The URL Vercel itself answers for a deployment: its first *.vercel.app
     * alias, or its own hostname. Custom domains are left out because their
     * DNS may send visitors to another host while the project stays on
     * Vercel.
     *
     * @param object|null $deployment A deployment as the API lists it.
     *
     * @return string|null
     */
    private function getVercelLink($deployment): ?string
    {
        foreach (($deployment->alias ?? []) as $alias) {
            if (str_ends_with($alias, self::VERCEL_SUFFIX) === true) {
                return "https://{$alias}";
            }
        }

        if (empty($deployment->url) === true) {
            return null;
        }

        return "https://{$deployment->url}";

    }//end getVercelLink()


    /**
     * The URL of the first custom domain (any alias not on vercel.app) of a
     * deployment, or null when it has none.
     *
     * @param object|null $deployment A deployment as the API lists it.
     *
     * @return string|null
     */
    private function getDomainLink($deployment): ?string
    {
        foreach (($deployment->alias ?? []) as $alias) {
            if (str_ends_with($alias, self::VERCEL_SUFFIX) === false) {
                return "https://{$alias}";
            }
        }

        return null;

    }//end getDomainLink()


    /**
     * Whether the custom domain answered from another host: the request
     * worked but the answer came without Vercel's x-vercel-id header (the
     * domain's DNS points elsewhere, for example to a mirror on Render).
     *
     * @param array|null $probe The answer of the custom domain.
     *
     * @return boolean
     */
    private function isServedElsewhere(?array $probe): bool
    {
        if ($probe === null || $probe["statusCode"] < 0) {
            return false;
        }

        $servedByVercel = ($probe["servedByVercel"] ?? true);

        return $servedByVercel === false;

    }//end isServedElsewhere()


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
     * A problem on the *.vercel.app URL wins over a custom domain served
     * elsewhere: that one is DOMAIN_ELSEWHERE only while Vercel itself still
     * serves the project fine.
     *
     * @param object      $project     A project as the API lists it.
     * @param object|null $deployment  The deployment that serves it.
     * @param array|null  $probe       The answer of its *.vercel.app URL.
     * @param array|null  $domainProbe The answer of its custom domain.
     *
     * @return string
     */
    private function getState($project, $deployment, ?array $probe, ?array $domainProbe): string
    {
        $state = $this->getProblem($probe);
        if (($project->paused ?? false) === true || $state === "DEPLOYMENT_PAUSED") {
            $state = self::STATE_PAUSED;
        }

        if ($state === null) {
            $state = ($deployment->readyState ?? self::STATE_UNKNOWN);
        }

        if ($state === "READY" && $this->isServedElsewhere($domainProbe) === true) {
            $state = self::STATE_DOMAIN_ELSEWHERE;
        }

        return $state;

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
            self::STATE_DOMAIN_ELSEWHERE => "↪️",
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
            self::STATE_DOMAIN_ELSEWHERE => "orange",
            default => "red",
        };

    }//end mapColor()


    /**
     * The row of a project: its badge, linked to the custom domain (or to
     * the *.vercel.app URL when there is none or it is served elsewhere),
     * its state and the date of the serving deployment.
     *
     * @param object    $project A project as the API lists it.
     * @param array     $probes  Answers keyed by KEY_VERCEL/KEY_DOMAIN and project name.
     * @param ShieldsIo $shields The badge builder.
     *
     * @return array
     */
    private function getRow($project, array $probes, ShieldsIo $shields): array
    {
        $deployment  = $this->getServingDeployment($project);
        $probe       = ($probes[self::KEY_VERCEL.$project->name] ?? null);
        $domainProbe = ($probes[self::KEY_DOMAIN.$project->name] ?? null);
        $state       = $this->getState($project, $deployment, $probe, $domainProbe);
        $status     = $this->mapStatus($state);
        $color      = $this->mapColor($state);

        $badgeUrl  = $shields->generateBadgeUrl($status, $project->name, $color, "for-the-badge", "white", null);
        $badgeLink = "<img src='{$badgeUrl}' alt='{$project->name}' />";
        $link      = $this->getVercelLink($deployment);
        $domain    = $this->getDomainLink($deployment);
        if ($domain !== null && $state !== self::STATE_DOMAIN_ELSEWHERE) {
            $link = $domain;
        }

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
            $deployment = $this->getServingDeployment($project);

            $link = $this->getVercelLink($deployment);
            if ($link !== null) {
                $links[self::KEY_VERCEL.$project->name] = $link;
            }

            $domain = $this->getDomainLink($deployment);
            if ($domain !== null) {
                $links[self::KEY_DOMAIN.$project->name] = $domain;
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
            $result[] = $this->getRow($project, $probes, $shields);
        }

        return $result;

    }//end getProjects()


}//end class
