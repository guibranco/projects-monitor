<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

header('Content-Type: application/json');

use GuiBranco\ProjectsMonitor\Library\CPanel;
use GuiBranco\ProjectsMonitor\Library\ErrorLog;
use GuiBranco\ProjectsMonitor\Library\ErrorsInbox;
use GuiBranco\ProjectsMonitor\Library\Logger;
use GuiBranco\ProjectsMonitor\Library\LogStream;

// The Errors page's single list. Each source is read on its own, so one that
// fails is reported in sources.<name>.error instead of hiding the others.
$unread = [];
$read   = function (string $source, callable $reader) use (&$unread): array {
    try {
        return $reader();
    } catch (Throwable $exception) {
        $unread[$source] = $exception->getMessage();
        (new LogStream())->warning("Errors inbox could not read {source}: {reason}", ["source" => $source, "reason" => $exception->getMessage()], "errors-inbox");
        return [];
    }
};

$appRows = $read(ErrorsInbox::SOURCE_APP, fn () => (new Logger())->getGroupedMessageRows());
$phpRows = $read(ErrorsInbox::SOURCE_PHP, fn () => (new ErrorLog())->getErrors());

// The error_log files are listed with their entries, from the same cPanel
// session, so the Errors page needs no separate cPanel call.
$files   = [];
$logRows = $read(
    ErrorsInbox::SOURCE_ERROR_LOG,
    function () use (&$files) {
        $cPanel = new CPanel();
        $files  = $cPanel->getErrorLogFiles();
        return $cPanel->getErrorLogEntries();
    }
);

$response = (new ErrorsInbox())->build($appRows, $phpRows, $logRows, $unread);
$response["errorLogFiles"] = $files;

echo json_encode($response);
