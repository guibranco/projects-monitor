<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\GitHubApiProxy;
use GuiBranco\ProjectsMonitor\Library\LogStream;

// Same convention as api/v1/github.php: a failing integration is logged and
// reported in the payload (the loader drops non-200 answers, and 401/403
// would show the session-expired modal), so the section can say the proxy
// is unavailable instead of staying on "Loading…".
try {
    $data = (new GitHubApiProxy())->getDashboard();
} catch (\Throwable $e) {
    $context = [
                "reason"    => $e->getMessage(),
                "exception" => $e,
               ];
    (new LogStream())->error("GitHub API proxy stats failed to load: {reason}", $context, "github-api-proxy");
    $data = ["error" => "GitHub API proxy unavailable"];
}

echo json_encode($data);
