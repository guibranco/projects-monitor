<?php

namespace GuiBranco\ProjectsMonitor\Library;

use Exception;

/**
 * Thrown by GitHubBillingConfig when github-billing.json is missing, malformed,
 * or fails schema/business-rule validation — always fatal, never caught silently.
 */
class GitHubBillingConfigException extends Exception
{
}
