<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\Library\LogStream;

class GStracciniBot
{
    private const JOB_NAMES = [
        "branches",
        "comments",
        "issues",
        "pullRequests",
        "pushes",
        "repositories",
    ];

    private $apiUrl;

    private $headers;

    private $request;

    public function __construct()
    {
        $config = new Configuration();
        $config->init();

        global $gstracciniBotApiKey, $gstracciniBotApiUrl;

        if (!file_exists(__DIR__ . "/../secrets/gstracciniBot.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: gstracciniBot.secrets.php");
        }

        require_once __DIR__ . "/../secrets/gstracciniBot.secrets.php";

        $this->apiUrl = $gstracciniBotApiUrl;
        $this->headers = [
            "X-Api-Key: {$gstracciniBotApiKey}",
            "Accept: application/json",
            constant("USER_AGENT"),
        ];
        $this->request = new Request();
    }

    /**
     * Returns the fixed set of background jobs exposed by the bot as a table
     * with an embedded "Run" action button per row (same pattern as the
     * Webhooks workers table).
     */
    public function getJobs(): array
    {
        $header = ["Job", "Actions"];
        $rows = [$header];

        foreach (self::JOB_NAMES as $job) {
            $safeJob = htmlspecialchars($job, ENT_QUOTES);
            $runBtn = "<button class=\"btn btn-primary btn-sm\" data-action=\"run-job\" data-name=\"{$safeJob}\" aria-label=\"Run {$safeJob} job now\">"
                . "<i class=\"bi bi-play-fill\"></i> Run</button>";
            $rows[] = [$safeJob, $runBtn];
        }

        return ["jobs" => $rows, "total" => count(self::JOB_NAMES)];
    }

    public function runJob($job): mixed
    {
        if (!in_array($job, self::JOB_NAMES, true)) {
            throw new \InvalidArgumentException("Invalid job name provided");
        }

        $startedAt = microtime(true);
        $response = $this->request->post("{$this->apiUrl}jobs/{$job}/", $this->headers);
        (new LogStream())->logResponse("GStraccini bot", "POST", $response, $startedAt, "gstraccini-bot");

        if ($response->getStatusCode() !== 202) {
            $error = $response->getStatusCode() == -1 ? $response->getMessage() : $response->getBody();
            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");
        }

        return json_decode($response->getBody(), true);
    }
}
