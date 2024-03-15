<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\ProjectsMonitor\Configuration\Config;
use GuiBranco\ProjectsMonitor\Library\Database;

class Logger
{
    public function saveError($applicationId)
    {
        $config = new Config();
        $data = $config->getRequestData();
        $conn = (new Database())->getConn();

        $sql = "INSERT INTO errors (`application_id`, `class`, `function`, `file`, `line`, `object`, `type`, `args`, `message`, `stack_trace`) ";
        $sql .= "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $appId = $applicationId;

        $class = isset($data["class"]) ? $data["class"] : "none";
        $function = isset($data["function"]) ? $data["function"] : "none";
        $file = isset($data["file"]) ? $data["file"] : "none";
        $line = isset($data["line"]) ? $data["line"] : "none";
        $object = isset($data["object"]) ? $data["object"] : "none";
        $type = isset($data["type"]) ? $data["type"] : "none";
        $args = isset($data["args"]) ? $data["args"] : "none";
        $message = isset($data["message"]) ? $data["message"] : "none";
        $stackTrace = isset($data["stack_trace"]) ? $data["stack_trace"] : "none";

        $stmt->bind_param("isssisssss", $appId, $class, $function, $file, $line, $object, $type, $args, $message, $stackTrace);

        return $stmt->execute();
    }

    public function saveMessage($applicationId)
    {
        $config = new Config();
        $data = $config->getRequestData();
        $conn = (new Database())->getConn();

        $sql = "INSERT INTO errors (`application_id`, `class`, `function`, `file`, `line`, `object`, `type`, `args`, `message`, `details`) ";
        $sql .= "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $appId = $applicationId;

        $class = isset($data["class"]) ? $data["class"] : "none";
        $function = isset($data["function"]) ? $data["function"] : "none";
        $file = isset($data["file"]) ? $data["file"] : "none";
        $line = isset($data["line"]) ? $data["line"] : "none";
        $object = isset($data["object"]) ? $data["object"] : "none";
        $type = isset($data["type"]) ? $data["type"] : "none";
        $args = isset($data["args"]) ? $data["args"] : "none";
        $message = isset($data["message"]) ? $data["message"] : "none";
        $details = isset($data["details"]) ? $data["details"] : "none";

        $stmt->bind_param("isssisssss", $appId, $class, $function, $file, $line, $object, $type, $args, $message, $details);

        return $stmt->execute();
    }

}
