<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\ShieldsIo;

class Vercel
{
    private const VERCEL_API_URL = "https://api.vercel.com/v10/projects?limit=100";

    private const STATE_PAUSED = "PAUSED";

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
     * Resolves the state shown for a project.
     *
     * A paused project (paused by hand, by spend management or by Vercel once the
     * plan's usage limits are exceeded) keeps its latest deployment in the READY
     * state, while every request to it gets a 503 DEPLOYMENT_PAUSED. The paused
     * flag therefore takes precedence over the deployment state.
     */
    private function resolveState($project, $deployment)
    {
        if (($project->paused ?? false) === true) {
            return self::STATE_PAUSED;
        }

        return $deployment->readyState ?? self::STATE_UNKNOWN;
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

        $shields = new ShieldsIo();
        $result = [["Project", "Status", "Last Deployment"]];

        foreach ($projects as $project) {
            $deployment = $project->latestDeployments[0] ?? null;
            $state = $this->resolveState($project, $deployment);
            $status = $this->mapStatus($state);
            $color = $this->mapColor($state);

            $domain = $deployment->alias[0] ?? $deployment->url ?? null;
            $link = $domain !== null ? "https://{$domain}" : null;
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
