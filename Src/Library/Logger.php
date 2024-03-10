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
        $class = $data["class"];
        $method = $data["method"];
        $file = $data["file"];
        $line = $data["line"];
        $message = $data["message"];
        $stackTrace = $data["stack_trace"];

        $stmt->bind_param("isssiss", $appId, $class, $method, $file, $line, $message, $stackTrace);

        if(!$stmt->execute()) {
            return false;
        }
        return true;
    }
}
