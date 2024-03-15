<?php

require_once '../../vendor/autoload.php';

use GuiBranco\ProjectsMonitor\Library\Database;

$conn = (new Database())->getConn();

$sqlFeed = "SELECT e.id, e.application_id, a.name, e.class, e.function, e.file, e.line, ";
$sqlFeed .= "e.object, e.type, e.args, e.message, e.details, e.stack_trace, e.created_at ";
$sqlFeed .= "FROM errors as e INNER JOIN applications as a ON e.application_id = a.id ";
$sqlFeed .= "ORDER BY e.created_at DESC LIMIT 0, 100;";
$stmtFeed = $mysqli->prepare($sqlFeed);
$stmtFeed->execute();
$stmtFeed->bind_result($id, $applicationId, $applicationName, $class, $function, $file, $line, $object, $type, $args, $message, $detais, $stackTrace, $createdAt);
$data["feed"] = array();
$data["feed"][] = array("Id", "ApplicationId", "ApplicationName", "Class", "Function", "File", "Line", "Object", "Type", "Args", "Message", "Details", "StackTrace", "CreatedAt");
while ($stmtFeed->fetch()) {
    $data["feed"][] = array($id, $applicationId, $applicationName, $class, $function, $file, $line, $object, $type, $args, $message, $detais, $stackTrace, $createdAt);
}
$stmtFeed->close();
$mysqli->close();
echo json_encode($data);
