<?php

require_once 'session_validator.php';
require_once '../../vendor/autoload.php';

header('Content-Type: application/json');

use GuiBranco\ProjectsMonitor\Library\CPanel;
use GuiBranco\ProjectsMonitor\Library\ErrorLog;
use GuiBranco\ProjectsMonitor\Library\ErrorsInbox;
use GuiBranco\ProjectsMonitor\Library\Logger;
use GuiBranco\ProjectsMonitor\Library\LogStream;

// The Errors page's single list. The error_log files are listed with their
// entries, from the same cPanel session, so the page needs no cPanel call.
$response = (new ErrorsInbox())->collect(
    fn () => (new Logger())->getGroupedMessageRows(),
    fn () => (new ErrorLog())->getErrors(),
    function () {
        $cPanel = new CPanel();
        return [
                "files"   => $cPanel->getErrorLogFiles(),
                "entries" => $cPanel->getErrorLogEntries(),
               ];
    }
);

foreach ($response["sources"] as $source => $entry) {
    if ($entry["error"] !== null) {
        (new LogStream())->warning("Errors inbox could not read {source}: {reason}", ["source" => $source, "reason" => $entry["error"]], "errors-inbox");
    }
}

echo json_encode($response);
