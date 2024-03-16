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

    private function getFieldList()
    {
        return array(
            "Id",
            "ApplicationId",
            "Class",
            "Function",
            "File",
            "Line",
            "Object",
            "Type",
            "Args",
            "Message",
            "Details",
            "CreatedAt"
        );
    }

    private function getQuery()
    {
        $sql = "SELECT m.id, m.application_id, a.name, m.class, m.function, m.file, m.line, ";
        $sql .= "m.object, m.type, m.args, m.message, m.details, m.created_at ";
        $sql .= "FROM messages as e INNER JOIN applications as a ON m.application_id = a.id ";
        return $sql;
    }

    public function showLastMessages($quantity)
    {
        $stmt = $this->connection->prepare($this->getQuery());
        $stmt->bind_param("i", $quantity);
        $stmt->execute();
        $data = array();
        $data[] = $this->getFieldList();
        $result = $stmt->get_result();
        while ($row = $result->fetch_array(MYSQLI_NUM)) {
            $data[] = array_values($row);
        }
        $stmt->close();

        return $data;
    }

}
