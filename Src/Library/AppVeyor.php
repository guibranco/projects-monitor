<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\ShieldsIo;

class AppVeyor
{
    private const APPVEYOR_API_URL = "https://ci.appveyor.com/api/";

    private $request;

    private $headers;

    public function __construct()
    {
        $config = new Configuration();
        $config->init();

        global $appVeyorApiKey;

        if (!file_exists(__DIR__ . "/../secrets/appVeyor.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: appVeyor.secrets.php");
        }

        require_once __DIR__ . "/../secrets/appVeyor.secrets.php";

        $this->request = new Request();
        $this->headers = ["Authorization: Bearer {$appVeyorApiKey}", "Content-Type: application/json", constant("USER_AGENT")];
    }

    private function getProjects()
    {
        $response = $this->request->get(self::APPVEYOR_API_URL . "projects", $this->headers);

        if ($response->statusCode != 200) {
            $error = $response->statusCode == -1 ? $response->error : $response->body;
            throw new RequestException("Code: {$response->statusCode} - Error: {$error}");
        }

        return json_decode($response->body);
    }

    private function mapStatus($status)
    {
        return match ($status) {
            "queued" => "⏳",
            "starting" => "🚦",
            "running" => "🏃",
            "success" => "✅",
            "failed" => "❌",
            default => $status
        };
    }

    private function mapColor($status)
    {
        return match ($status) {
            "queued" => "blue",
            "starting" => "orange",
            "running" => "yellow",
            "success" => "green",
            "failed" => "red",
            default => $status
        };
    }

    public function getBuilds()
    {
        $projects = $this->getProjects();
        $result = array();

        $result[] = array("Project", "Branch/Version", "Updated");
        $shields = new ShieldsIo();

        foreach ($projects as $project) {
            foreach ($project->builds as $build) {
                $status = $this->mapStatus($build->status);
                $color = $this->mapColor($build->status);

                $link = "https://ci.appveyor.com/project/{$project->accountName}/{$project->slug}/builds/{$build->buildId}";
                $badgeName = $shields->generateBadgeUrl($status, $project->name, $color, "for-the-badge", "white", null);
                $badgeNameImg = "<a href='{$link}' title='{$status}' target='_blank' rel='noopener noreferrer'><img src='{$badgeName}' alt='{$status}' /></a>";

                $badgeVersion = $shields->generateBadgeUrl($build->branch, $build->version, "blue", "for-the-badge", "white", null);
                $badgeVersionImg = "<a href='{$link}' title='{$build->version} - {$build->branch}' target='_blank' rel='noopener noreferrer'><img src='{$badgeVersion}' alt='{$build->version}' /></a>";

                $dateValue = isset($build->updated) ? $build->updated : $build->created;
                $date = date("Y-m-d H:i:s", strtotime($dateValue));
                $result[] = array($badgeNameImg, $badgeVersionImg, $date);
            }
        }

        return $result;
    }
}
