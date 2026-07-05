<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\ShieldsIo;

class Vercel
{
    private const VERCEL_API_URL = "https://api.vercel.com/v9/projects?limit=100";

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

    private function fetchProjects()
    {
        $response = $this->request->get(self::VERCEL_API_URL, $this->headers);

        if ($response->getStatusCode() != 200) {
            $error = $response->getStatusCode() == -1 ? $response->getMessage() : $response->getBody();
            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");
        }

        return json_decode($response->getBody());
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
            $state = $deployment->readyState ?? "UNKNOWN";
            $status = $this->mapStatus($state);
            $color = $this->mapColor($state);

            $link = "https://{$project->name}.vercel.app";
            $badgeUrl = $shields->generateBadgeUrl($status, $project->name, $color, "for-the-badge", "white", null);
            $badgeLink = "<a href='{$link}' title='{$state}' target='_blank' rel='noopener noreferrer'>"
                . "<img src='{$badgeUrl}' alt='{$project->name}' /></a>";

            $lastDeployment = isset($deployment->createdAt)
                ? date("H:i:s d/m/Y", intdiv($deployment->createdAt, 1000))
                : "-";

            $result[] = [$badgeLink, $state, $lastDeployment];
        }

        return $result;
    }
}
