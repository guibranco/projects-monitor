<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\RequestException as PancakeRequestException;
use GuiBranco\Pancake\Response;
use GuiBranco\Pancake\ShieldsIo;

class Vercel
{
    private const VERCEL_API_URL = "https://api.vercel.com/v10/projects?limit=100";

    private const PROBE_CACHE_FILE = __DIR__ . "/../cache/vercel_probe.json";

    /**
     * How long a probe result is reused before the production URL is requested again.
     *
     * The dashboard refreshes this tile every 60 seconds; every probe is a request
     * (and, for function-backed projects, an invocation) counted against the Vercel
     * quota, so each project is visited at most 144 times a day with this value.
     */
    private const PROBE_CACHE_TTL_SECONDS = 600;

    /** Pancake's Request runs at most 10 requests per batch. */
    private const PROBE_BATCH_SIZE = 10;

    private const STATE_PAUSED = "PAUSED";

    private const STATE_UNREACHABLE = "UNREACHABLE";

    private const STATE_UNKNOWN = "UNKNOWN";

    private $request;

    private $headers;

    public function __construct()
    {
        $config = new Configuration();
        $config->init();

        global $vercelToken;

        if (!file_exists(__DIR__ . "/../secrets/vercel.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: vercel.secrets.php");
        }

        require_once __DIR__ . "/../secrets/vercel.secrets.php";

        $this->request = new Request();
        $this->headers = ["Authorization: Bearer {$vercelToken}", "Accept: application/json", constant("USER_AGENT")];
    }

    protected function fetchProjects()
    {
        $response = $this->request->get(self::VERCEL_API_URL, $this->headers);

        if ($response->getStatusCode() != 200) {
            $error = $response->getStatusCode() == -1 ? $response->getMessage() : $response->getBody();
            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");
        }

        return json_decode($response->getBody());
    }

    /**
     * The deployment that serves the project: the current production deployment,
     * falling back to the latest deployment of any target (a preview included)
     * when the project has never been promoted to production.
     */
    private function servingDeployment($project)
    {
        return $project->targets->production ?? $project->latestDeployments[0] ?? null;
    }

    private function deploymentLink($deployment): ?string
    {
        $domain = $deployment->alias[0] ?? $deployment->url ?? null;

        return $domain !== null ? "https://{$domain}" : null;
    }

    /**
     * Requests the given URLs the way a visitor would, in parallel batches, and
     * describes what came back. Only the User-Agent is sent: these URLs are the
     * projects themselves, not the API, so the token must not travel with them.
     *
     * @param string[] $urls
     * @return array Keyed by URL: ["checkedAt" => int, "statusCode" => int, "vercelError" => string|null].
     */
    protected function executeProbe(array $urls): array
    {
        $results = [];

        foreach (array_chunk($urls, self::PROBE_BATCH_SIZE) as $chunk) {
            $request = new Request();

            foreach ($chunk as $url) {
                $request->addRequest($url, $url, [constant("USER_AGENT")]);
            }

            foreach ($request->executeBatch() as $url => $response) {
                $results[$url] = $this->describeProbe($response);
            }
        }

        return $results;
    }

    private function describeProbe(Response $response): array
    {
        return [
            "checkedAt" => time(),
            "statusCode" => $response->getStatusCode(),
            "vercelError" => $this->header($response, "x-vercel-error"),
        ];
    }

    private function header(Response $response, string $name): ?string
    {
        foreach ($response->getHeaders() ?? [] as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return trim((string) $value);
            }
        }

        return null;
    }

    protected function probeCachePath(): string
    {
        return self::PROBE_CACHE_FILE;
    }

    /**
     * Probe results still within the TTL, keyed by URL. Anything unreadable or
     * malformed counts as a miss.
     */
    private function readProbeCache(): array
    {
        $path = $this->probeCachePath();

        if (!file_exists($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        $decoded = $contents === false ? null : json_decode($contents, true);

        if (!is_array($decoded)) {
            return [];
        }

        $threshold = time() - self::PROBE_CACHE_TTL_SECONDS;

        return array_filter(
            $decoded,
            fn($entry) => is_array($entry) && ($entry["checkedAt"] ?? 0) > $threshold
        );
    }

    /**
     * Temp file plus rename, so a concurrent request never reads a half-written file.
     */
    private function writeProbeCache(array $entries): void
    {
        $path = $this->probeCachePath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tempPath = $path . "." . uniqid("", true) . ".tmp";

        if (file_put_contents($tempPath, json_encode($entries)) === false) {
            return;
        }

        rename($tempPath, $path);
    }

    /**
     * Probe result for each project link, served from the cache while it is
     * fresh and refreshed only for the links that are missing or expired. A
     * probe failure leaves those links without a result instead of failing the tile.
     *
     * @param array $links Production URL keyed by project name.
     * @return array Probe result (or null) keyed by project name.
     */
    private function probeLinks(array $links): array
    {
        $cached = $this->readProbeCache();
        $pending = array_values(array_unique(array_diff($links, array_keys($cached))));

        if (!empty($pending)) {
            try {
                $cached = array_merge($cached, $this->executeProbe($pending));
                $this->writeProbeCache($cached);
            } catch (PancakeRequestException $e) {
                LogStream::warning("Vercel probe failed", ["reason" => $e->getMessage()], "vercel");
            }
        }

        $results = [];

        foreach ($links as $name => $url) {
            $results[$name] = $cached[$url] ?? null;
        }

        return $results;
    }

    /**
     * What a visitor runs into, or null when the probe saw nothing wrong: the
     * platform's x-vercel-error code (DEPLOYMENT_DISABLED when the plan's usage
     * limits are exceeded, DEPLOYMENT_PAUSED, DEPLOYMENT_NOT_FOUND, NOT_FOUND,
     * FUNCTION_INVOCATION_FAILED...), UNREACHABLE when the request itself
     * failed, or the status code of a 5xx answered by the project.
     */
    private function probeProblem(?array $probe): ?string
    {
        if ($probe === null) {
            return null;
        }

        if ($probe["statusCode"] < 0) {
            return self::STATE_UNREACHABLE;
        }

        if (!empty($probe["vercelError"])) {
            return $probe["vercelError"];
        }

        if ($probe["statusCode"] >= 500) {
            return "HTTP {$probe["statusCode"]}";
        }

        return null;
    }

    /**
     * Resolves the state, icon and colour shown for a project.
     *
     * A project the platform stopped serving (paused by hand or by spend
     * management, 503 DEPLOYMENT_PAUSED; disabled once the plan's usage limits
     * are exceeded, 402 DEPLOYMENT_DISABLED) keeps its deployment in the READY
     * state, so the paused flag and the probe both take precedence over the
     * deployment state.
     */
    private function resolveStatus($project, $deployment, ?array $probe): array
    {
        if (($project->paused ?? false) === true) {
            return [self::STATE_PAUSED, "⛔", "red"];
        }

        $problem = $this->probeProblem($probe);

        if ($problem === "DEPLOYMENT_PAUSED") {
            return [self::STATE_PAUSED, "⛔", "red"];
        }

        if ($problem === "DEPLOYMENT_DISABLED") {
            return [$problem, "⛔", "red"];
        }

        if ($problem !== null) {
            return [$problem, "🔥", "red"];
        }

        $state = $deployment->readyState ?? self::STATE_UNKNOWN;

        return [$state, $this->mapStatus($state), $this->mapColor($state)];
    }

    private function mapStatus($state)
    {
        return match ($state) {
            "READY" => "✅",
            "ERROR" => "❌",
            "BUILDING" => "🔨",
            "QUEUED" => "⏳",
            "CANCELED" => "⏸",
            "INITIALIZING" => "🆕",
            "BLOCKED" => "🚫",
            self::STATE_PAUSED => "⛔",
            default => "❓",
        };
    }

    private function mapColor($state)
    {
        return match ($state) {
            "READY" => "green",
            "ERROR" => "red",
            "BUILDING" => "yellow",
            "QUEUED" => "blue",
            "CANCELED" => "gray",
            "INITIALIZING" => "blue",
            "BLOCKED" => "red",
            self::STATE_PAUSED => "red",
            default => "lightgray",
        };
    }

    public function getProjects()
    {
        $response = $this->fetchProjects();
        $projects = $response->projects ?? [];

        usort($projects, fn($a, $b) => strcasecmp($a->name, $b->name));

        $links = [];

        foreach ($projects as $project) {
            $link = $this->deploymentLink($this->servingDeployment($project));

            if ($link !== null) {
                $links[$project->name] = $link;
            }
        }

        $probes = $this->probeLinks($links);

        $shields = new ShieldsIo();
        $result = [["Project", "Status", "Last Deployment"]];

        foreach ($projects as $project) {
            $deployment = $this->servingDeployment($project);
            [$state, $status, $color] = $this->resolveStatus($project, $deployment, $probes[$project->name] ?? null);

            $link = $links[$project->name] ?? null;
            $badgeUrl = $shields->generateBadgeUrl($status, $project->name, $color, "for-the-badge", "white", null);
            $badgeImg = "<img src='{$badgeUrl}' alt='{$project->name}' />";
            $badgeLink = $link !== null
                ? "<a href='{$link}' title='{$state}' target='_blank' rel='noopener noreferrer'>{$badgeImg}</a>"
                : $badgeImg;

            $lastDeployment = isset($deployment->createdAt)
                ? date("H:i:s d/m/Y", intdiv($deployment->createdAt, 1000))
                : "-";

            $result[] = [$badgeLink, $state, $lastDeployment];
        }

        return $result;
    }
}
