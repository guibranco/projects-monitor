<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\GitHubApiProxy;
use GuiBranco\ProjectsMonitor\Library\GitHubApiProxyRange;
use GuiBranco\ProjectsMonitor\Library\ErrorReporter;

// Same convention as api/v1/github.php: a failing integration is reported
// (LogStream when enabled + messages table) and
// reported in the payload (the loader drops non-200 answers, and 401/403
// would show the session-expired modal), so the section can say the proxy
// is unavailable instead of staying on "Loading…".
// ?range=1h|24h|7d|30d|90d; anything else falls back to 24h.
$range = new GitHubApiProxyRange(isset($_GET["range"]) === true ? (string) $_GET["range"] : null);

try {
    $data = (new GitHubApiProxy())->getDashboard($range);
} catch (\Throwable $e) {
    $context = [
                "reason"    => $e->getMessage(),
                "exception" => $e,
               ];
    (new ErrorReporter())->error("GitHub API proxy stats failed to load: {reason}", $context, "github-api-proxy");
    $data = ["error" => "GitHub API proxy unavailable"];
}

echo json_encode($data);
