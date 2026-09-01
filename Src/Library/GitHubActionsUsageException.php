<?php

namespace GuiBranco\ProjectsMonitor\Library;

use Exception;

/**
 * Thrown by GitHubActionsUsage on a per-account token/fetch failure — caught
 * and turned into a degraded ("unavailable") result rather than propagating.
 */
class GitHubActionsUsageException extends Exception
{
}
