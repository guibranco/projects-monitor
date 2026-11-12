<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\Response;
use GuiBranco\Pancake\ShieldsIo;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\LogStream;

class Webhooks
{
    /**
     * Workers that can be triggered via POST /workers/{name}/run (run mode
     * "http"). queue-relay is daemon-only and deliberately absent.
     */
    public const RUNNABLE_WORKER_NAMES = ["service", "cleanup", "database-service", "maintenance", "hooks", "idle-pull-requests"];

    private $apiUrl;

    private $headers;

    private $request;

    public function __construct()
    {
        global $webhooksApiToken, $webhooksApiUrl;

        $config = new Configuration();
        $config->init();

        if (!file_exists(__DIR__ . "/../secrets/webhooks.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: webhooks.secrets.php");
        }

        require_once __DIR__ . "/../secrets/webhooks.secrets.php";

        $this->apiUrl = $webhooksApiUrl;
        $this->headers = [
            "Authorization: token {$webhooksApiToken}",
            "Accept: application/json",
            "Cache-Control: no-cache",
            constant("USER_AGENT"),
            "X-timezone: {$config->getTimeZone()->getTimeZone()}",
            "X-timezone-offset: {$config->getTimeZone()->getOffset()}"
        ];
        $this->request = new Request();
    }

    private function doRequest($endpoint, $method, $expectedStatusCode, $data = null)
    {
        $response = null;
        $method = strtolower($method);
        $startedAt = microtime(true);
        switch ($method) {
            case "get":
                $response = $this->request->get("{$this->apiUrl}{$endpoint}", $this->headers);
                break;
            case "post":
                $response = $this->request->post("{$this->apiUrl}{$endpoint}", $this->headers, json_encode($data));
                break;
            case "put":
                $response = $this->request->put("{$this->apiUrl}{$endpoint}", $this->headers, json_encode($data));
                break;
            case "delete":
                $response = $this->request->delete("{$this->apiUrl}{$endpoint}", $this->headers);
                break;
            default:
                throw new RequestException("Method not mapped: {$method}");
        }

        (new LogStream())->logResponse("Webhooks API", $method, $response, $startedAt, "webhooks");
        if ($response->getStatusCode() === $expectedStatusCode) {
            return json_decode($response->getBody(), true);
        }

        $error = $response->getStatusCode() == -1 ? $response->getMessage() : $response->getBody();
        throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");
    }

    public function getDashboard($feedOptionsFilter)
    {
        $allowedFilters = ['all', 'mine'];
        if (!in_array($feedOptionsFilter, $allowedFilters)) {
            throw new \InvalidArgumentException('Invalid filter value provided');
        }
        $endpoint = sprintf("github?feedOptionsFilter=%s", urlencode($feedOptionsFilter));
        $response = $this->doRequest($endpoint, "get", 200);

        return $response;
    }

    public function getWebhook($sequence): mixed
    {
        return $this->doRequest("github/{$sequence}", "get", 200);
    }

    public function requestRerun($sequence): mixed
    {
        return $this->doRequest("github/workflow", "post", 201, ["sequence" => $sequence]);
    }

    public function requestUpdate($sequence): mixed
    {
        return $this->doRequest("github/workflow", "put", 202, ["sequence" => $sequence]);
    }

    public function requestDelete($sequence): mixed
    {
        return $this->doRequest("github/workflow/{$sequence}", "delete", 202);
    }

    public function getStatistics(): mixed
    {
        return $this->doRequest("processing-state", "get", 200);
    }

    public function getPullRequestsProcessing(): mixed
    {
        return $this->doRequest("pull-requests/processing", "get", 200);
    }

    public function getPullRequestsIdle(): mixed
    {
        return $this->doRequest("pull-requests/idle", "get", 200);
    }

    public function getBranchesProcessing(): mixed
    {
        return $this->doRequest("branches/processing", "get", 200);
    }

    public function getCommentsProcessing(): mixed
    {
        return $this->doRequest("comments/processing", "get", 200);
    }

    public function getInstallationsProcessing(): mixed
    {
        return $this->doRequest("installations/processing", "get", 200);
    }

    public function getIssuesProcessing(): mixed
    {
        return $this->doRequest("issues/processing", "get", 200);
    }

    public function getPushesProcessing(): mixed
    {
        return $this->doRequest("pushes/processing", "get", 200);
    }

    public function getRepositoriesProcessing(): mixed
    {
        return $this->doRequest("repositories/processing", "get", 200);
    }

    public function getUsersProcessing(): mixed
    {
        return $this->doRequest("users/processing", "get", 200);
    }

    public function getWorkers(): mixed
    {
        $workers = $this->doRequest("workers", "get", 200);
        return $this->formatWorkersTable($workers);
    }

    public function runWorker($name): mixed
    {
        if (!in_array($name, self::RUNNABLE_WORKER_NAMES, true)) {
            throw new \InvalidArgumentException("Invalid worker name provided");
        }

        return $this->doRequest("workers/{$name}/run", "post", 202);
    }

    /**
     * Returns the latest release per repository this service has received
     * webhooks for, correlated with deploy/release workflow run outcomes,
     * from the webhooks API's `github_release_status_view`-backed endpoint.
     */
    public function getReleases(): mixed
    {
        $releases = $this->doRequest("releases/", "get", 200);
        return $this->formatReleasesTable($releases);
    }

    private function formatReleasesTable(array $releases): array
    {
        $header = ["Repository", "Release", "Tag", "Created At", "Workflow Status", "Workflow Runs", "Failed Runs", "Actions"];
        $rows = [$header];

        foreach ($releases as $release) {
            $owner = htmlspecialchars($release["owner"] ?? "", ENT_QUOTES);
            $repo = htmlspecialchars($release["repo"] ?? "", ENT_QUOTES);
            $releaseName = htmlspecialchars($release["release_name"] ?? ($release["tag_name"] ?? ""), ENT_QUOTES);
            $tagName = htmlspecialchars($release["tag_name"] ?? "", ENT_QUOTES);
            $createdAt = htmlspecialchars($release["release_created_at"] ?? "", ENT_QUOTES);
            $htmlUrl = htmlspecialchars($release["html_url"] ?? "", ENT_QUOTES);
            $viewBtn = $htmlUrl !== ""
                ? "<a class=\"btn btn-sm btn-outline-light\" href=\"{$htmlUrl}\" target=\"_blank\" rel=\"noopener noreferrer\" aria-label=\"View release {$releaseName} on GitHub\"><i class=\"bi bi-box-arrow-up-right\"></i></a>"
                : "";

            $rows[] = [
                "{$owner}/{$repo}",
                $releaseName,
                $tagName,
                $createdAt,
                $this->releaseStatusBadge((string) ($release["workflow_status"] ?? "Pending")),
                (int) ($release["workflow_runs_count"] ?? 0),
                (int) ($release["failed_workflow_runs"] ?? 0),
                $viewBtn,
            ];
        }

        return ["releases" => $rows, "total" => count($releases)];
    }

    private function releaseStatusBadge(string $status): string
    {
        $styles = [
            "Successful" => ["✅", "brightgreen"],
            "Failed"     => ["❌", "red"],
            "Pending"    => ["⏳", "orange"],
        ];
        [$label, $color] = $styles[$status] ?? ["⚪", "lightgrey"];

        $shields = new ShieldsIo();
        $url = $shields->generateBadgeUrl($label, $status, $color, "for-the-badge", "white", null);
        return "<img src='{$url}' alt='{$status}' />";
    }

    /**
     * Returns the latest status of every tracked workflow run (not just the
     * deploy/release-keyword-matched ones the releases table shows), from the
     * webhooks API's `github_workflow_runs_view`-backed endpoint, with raw
     * fields plus embedded Retry/Delete action buttons.
     */
    public function getWorkflowRuns(): mixed
    {
        $runs = $this->doRequest("workflow-runs/", "get", 200);
        if (is_array($runs) === false) {
            $runs = [];
        }

        $presenter = new WorkflowRunsPresenter();

        return [
            "workflow_runs" => $presenter->toTable($runs),
            "total"         => count($runs),
        ];
    }

    /**
     * Asks GitHub to re-run a workflow run (rerun for cancelled runs,
     * rerun-failed-jobs otherwise). Only requests the retry; the row's
     * status/conclusion update asynchronously once the webhooks app
     * processes GitHub's follow-up events.
     */
    public function retryWorkflowRun($workflowRunId): array
    {
        $startedAt = microtime(true);
        $response = $this->request->post("{$this->apiUrl}workflow-runs/{$workflowRunId}/retry", $this->headers, "");
        return $this->toApiResult($response, "POST", $startedAt);
    }

    /**
     * Deletes the stored row for a workflow run. Only removes the row from
     * github_workflow_runs — doesn't touch GitHub.
     */
    public function deleteWorkflowRun($workflowRunId): array
    {
        $startedAt = microtime(true);
        $response = $this->request->delete("{$this->apiUrl}workflow-runs/{$workflowRunId}", $this->headers);
        return $this->toApiResult($response, "DELETE", $startedAt);
    }

    /**
     * Passes a webhooks API response's status code and decoded body straight
     * through, instead of throwing, since retry/delete have several expected
     * non-2xx outcomes (404/409/422) that the caller needs to relay as-is.
     */
    private function toApiResult(Response $response, string $method, float $startedAt): array
    {
        (new LogStream())->logResponse("Webhooks API", $method, $response, $startedAt, "webhooks");
        $statusCode = $response->getStatusCode();

        if ($statusCode === -1) {
            return ["statusCode" => 502, "body" => ["error" => $response->getMessage()]];
        }

        return ["statusCode" => $statusCode, "body" => json_decode($response->getBody(), true)];
    }

    private function formatWorkersTable(array $workers): array
    {
        $header = ["Worker", "Description", "Run Modes", "Sleep (s)", "Memory Limit", "Systemd Service", "Actions"];
        $rows = [$header];

        foreach ($workers as $worker) {
            $name = htmlspecialchars($worker["name"] ?? "", ENT_QUOTES);
            $description = htmlspecialchars($worker["description"] ?? "", ENT_QUOTES);
            $runModes = htmlspecialchars(implode(", ", $worker["run_modes"] ?? []), ENT_QUOTES);
            $sleep = htmlspecialchars((string) ($worker["daemon_sleep_seconds"] ?? "—"), ENT_QUOTES);
            $memoryLimit = htmlspecialchars($worker["memory_limit"] ?? "—", ENT_QUOTES);
            $systemdService = htmlspecialchars($worker["systemd_service"] ?? "", ENT_QUOTES);
            // Only workers with the "http" run mode can be triggered (not queue-relay).
            $runBtn = in_array("http", $worker["run_modes"] ?? [], true)
                ? "<button class=\"btn btn-primary btn-sm\" data-action=\"run-worker\" data-name=\"{$name}\" aria-label=\"Run {$name} now\">"
                    . "<i class=\"bi bi-play-fill\"></i> Run</button>"
                : "<span class=\"text-muted small\" title=\"Daemon-only worker\">—</span>";

            $rows[] = [$name, $description, $runModes, $sleep, $memoryLimit, $systemdService, $runBtn];
        }

        return ["workers" => $rows, "total" => count($workers)];
    }
}
