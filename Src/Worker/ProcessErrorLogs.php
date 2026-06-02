#!/usr/bin/env php
<?php

/**
 * ProcessErrorLogs — worker that polls cPanel for error_log files, parses them,
 * persists the entries to the `errors` DB table, and removes the processed files.
 *
 * Recommended cron entry (every 5 minutes, adjust path to match your deployment):
 *
 *   *\/5 * * * * php /home/<username>/public_html/Src/Worker/ProcessErrorLogs.php \
 *       >> /var/log/projects-monitor-error-logs.log 2>&1
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit(1);
}

define('WORKER_START', microtime(true));

require_once __DIR__ . '/../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\CPanel;
use GuiBranco\ProjectsMonitor\Library\ErrorLog;
use GuiBranco\ProjectsMonitor\Library\LogParser;
use GuiBranco\ProjectsMonitor\Library\LogStream;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function workerLog(string $message): void
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
}

function extractFirstLine(string $text): string
{
    $nl = strpos($text, "\n");
    return $nl === false ? trim($text) : trim(substr($text, 0, $nl));
}

function parseDate(string $raw): ?string
{
    try {
        return (new DateTimeImmutable($raw))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

function workerStarted(): void
{
    workerLog('=== ProcessErrorLogs worker started ===');
}

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

LogStream::initialize();

$stats = ['files' => 0, 'processed' => 0, 'inserted' => 0, 'skipped' => 0, 'errors' => 0];

try {
    $cPanel = new CPanel();
    $errorLog = new ErrorLog();
    $parser = new LogParser();
} catch (Throwable $e) {
    workerStarted();
    workerLog('FATAL: could not initialise services — ' . $e->getMessage());
    LogStream::critical("ProcessErrorLogs failed to initialise services", ["error" => $e->getMessage()], "worker");
    exit(1);
}

// ---------------------------------------------------------------------------
// Discover files
// ---------------------------------------------------------------------------

try {
    $files = $cPanel->getErrorLogFilePaths();
} catch (Throwable $e) {
    workerStarted();
    workerLog('FATAL: could not query cPanel for error_log files — ' . $e->getMessage());
    LogStream::critical("ProcessErrorLogs failed to query cPanel", ["error" => $e->getMessage()], "worker");
    exit(1);
}

$stats['files'] = count($files);

if ($stats['files'] === 0) {
    exit(0);
}

workerStarted();
workerLog("Found {$stats['files']} error_log file(s)");
LogStream::info("ProcessErrorLogs worker started", ["files_found" => $stats['files']], "worker");

// ---------------------------------------------------------------------------
// Process each file
// ---------------------------------------------------------------------------

foreach ($files as $fileInfo) {
    $fullPath = $fileInfo['fullPath'];
    $processingPath = $fullPath . '.processing';

    workerLog("→ {$fullPath}");

    // Step 1 — rename to lock the file against concurrent cron runs
    if (!$cPanel->renameFile($fullPath, $processingPath)) {
        workerLog("  WARN: rename failed, skipping");
        LogStream::warning("Could not lock error log file for processing", ["path" => $fullPath], "worker");
        $stats['errors']++;
        continue;
    }

    // Step 2 — read the locked copy
    $content = $cPanel->readFile($processingPath);
    if ($content === null || trim($content['contents']) === '') {
        workerLog("  WARN: file is empty or unreadable, deleting");
        LogStream::warning("Error log file is empty or unreadable", ["path" => $processingPath], "worker");
        $cPanel->deleteFile($processingPath);
        $stats['errors']++;
        continue;
    }

    // Step 3 — parse
    try {
        $entries = $parser->parse($content['contents']);
    } catch (Throwable $e) {
        workerLog("  ERROR: parse failed — " . $e->getMessage() . ", deleting");
        LogStream::error("Failed to parse error log file", ["path" => $processingPath, "error" => $e->getMessage()], "worker");
        $cPanel->deleteFile($processingPath);
        $stats['errors']++;
        continue;
    }

    workerLog("  Parsed " . count($entries) . " entr(ies)");

    // Step 4 — persist each entry
    foreach ($entries as $entry) {
        $mysqlDate = parseDate($entry['date']);
        if ($mysqlDate === null) {
            workerLog("  WARN: unparseable date '{$entry['date']}', skipping entry");
            $stats['skipped']++;
            continue;
        }

        $line = is_numeric($entry['line']) ? (int) $entry['line'] : 0;
        $error = extractFirstLine($entry['multilineError']);
        $stackTraceDetails = isset($entry['stackTraceDetails']) ? trim($entry['stackTraceDetails']) : null;

        $saved = $errorLog->saveError(
            $fullPath,             // original path (before rename) as the stable key
            $mysqlDate,
            $error,
            $entry['multilineError'],
            $entry['file'],
            $line,
            null,                  // stack_trace summary not captured by LogParser
            $stackTraceDetails
        );

        if ($saved) {
            $stats['inserted']++;
        } else {
            $stats['skipped']++;   // duplicate or DB failure
        }
    }

    // Step 5 — delete the processed file
    if ($cPanel->deleteFile($processingPath)) {
        workerLog("  Deleted {$processingPath}");
        $stats['processed']++;
    } else {
        workerLog("  WARN: could not delete {$processingPath}");
        $stats['errors']++;
    }
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

$elapsed = round(microtime(true) - WORKER_START, 2);
workerLog(sprintf(
    '=== Done in %ss | files: %d  processed: %d  inserted: %d  skipped: %d  errors: %d ===',
    $elapsed,
    $stats['files'],
    $stats['processed'],
    $stats['inserted'],
    $stats['skipped'],
    $stats['errors']
));
LogStream::info("ProcessErrorLogs worker completed", array_merge($stats, ["elapsed_seconds" => $elapsed]), "worker");
