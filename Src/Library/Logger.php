<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\ProjectsMonitor\Configuration\Configuration;
use GuiBranco\ProjectsMonitor\Library\Database;

class Logger
{
    private $connection;

    public function __construct()
    {
        $this->connection = (new Database())->getConnection();
    }

    public function saveMessage($applicationId)
    {
        $data = (new Configuration())->getRequestData();

        $sql = "INSERT INTO messages (`application_id`, `class`, `function`, `file`,";
        $sql .= "`line`, `object`, `type`, `args`, `message`, `details`) ";
        $sql .= "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->connection->prepare($sql);

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

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    public function getTotal()
    {
        $sql = "SELECT COUNT(1) as total FROM messages;";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $stmt->bind_result($total);
        $stmt->fetch();
        $stmt->close();

        return $total;
    }

    public function getTotalByApplications()
    {
        $sql = "SELECT a.name, COUNT(1) as total FROM messages as m ";
        $sql .= "INNER JOIN applications as a ON m.application_id = a.id ";
        $sql .= "GROUP BY m.application_id;";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $stmt->bind_result($name, $total);
        $data = array();
        while ($stmt->fetch()) {
            $data[] = array("name" => $name, "total" => $total);
        }
        $stmt->close();

        return $data;
    }

    public function showLastMessages($quantity)
    {
        $sql = "SELECT m.id, m.application_id, a.name, m.class, m.function, m.file, m.line, ";
        $sql .= "m.object, m.type, m.args, m.message, m.details, m.stack_trace, m.created_at ";
        $sql .= "FROM errors as e INNER JOIN applications as a ON m.application_id = a.id ";
        $sql .= "ORDER BY m.created_at DESC LIMIT 0, ?;";
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param("i", $quantity);
        $stmt->execute();
        $stmt->bind_result(
            $id,
            $applicationId,
            $applicationName,
            $class,
            $function,
            $file,
            $line,
            $object,
            $type,
            $args,
            $message,
            $details,
            $stackTrace,
            $createdAt
        );
        $data = array();
        $data[] = array(
            "Id",
            "ApplicationId",
            "ApplicationName",
            "Class",
            "Function",
            "File",
            "Line",
            "Object",
            "Type",
            "Args",
            "Message",
            "Details",
            "StackTrace",
            "CreatedAt"
        );
        while ($stmt->fetch()) {
            $data[] = array(
                $id,
                $applicationId,
                $applicationName,
                $class,
                $function,
                $file,
                $line,
                $object,
                $type,
                $args,
                $message,
                $details,
                $stackTrace,
                $createdAt
            );
        }
        $stmt->close();

        return $data;
    }

}
