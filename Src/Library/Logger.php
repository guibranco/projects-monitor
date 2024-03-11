<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\ProjectsMonitor\Configuration\Config;
use GuiBranco\ProjectsMonitor\Library\Database;

class Logger
{
    public function saveLog($applicationId)
    {
        $config = new Config();
        $data = $config->getRequestData();
        $conn = (new Database())->getConn();

        $sql = "INSERT INTO errors (`application_id`, `class`, `method`, `file`, `line`, `message`, `stack_trace`) ";
        $sql .= "VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $appId = $applicationId;

        $class = isset($data["class"]) ? $data["class"] : "none";
        $method = isset($data["method"]) ? $data["method"] : "none";
        $file = isset($data["file"]) ? $data["file"] : "none";
        $line = isset($data["line"]) ? $data["line"] : "none";
        $message = isset($data["message"]) ? $data["message"] : "none";
        $stackTrace = isset($data["stack_trace"]) ? $data["stack_trace"] : "none";

        $stmt->bind_param("isssiss", $appId, $class, $method, $file, $line, $message, $stackTrace);

        if (!$stmt->execute()) {
            return false;
        }
        return true;
    }
}
