<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\Database;

class Logger
{
    private $connection;

    public function __construct()
    {
        $this->connection = (new Database())->getConnection();
    }

    public function convertUserAgentToLink(string $userAgent): string
    {
        $regex = '/(.+)\s\(\+?((https?:\/\/[^\)]+))\)$/';

        if (!preg_match($regex, $userAgent, $matches)) {
            return htmlspecialchars($userAgent, ENT_QUOTES);
        }

        $text = trim($matches[1]);
        $url = $matches[2];
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES) . '">' . htmlspecialchars($text, ENT_QUOTES) . '</a>';
    }


    private function getInsert()
    {
        $sql = "INSERT INTO messages (`application_id`, `class`, `function`, `file`, `line`,";
        $sql .= "`object`, `type`, `args`, `message`, `details`, `correlation_id`, `user_agent`) ";
        $sql .= "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        return $sql;
    }

    public function saveMessage($applicationId)
    {
        $config = new Configuration();
        $headers = $config->getRequestHeaders();
        $data = $config->getRequestData();
        $sql = $this->getInsert();
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
        $correlationId = isset($headers["X-Correlation-Id"]) ? $headers["X-Correlation-Id"] : "none";
        $userAgent = isset($headers["User-Agent"]) ? $headers["User-Agent"] : "none";

        $stmt->bind_param(
            "isssisssssss",
            $appId,
            $class,
            $function,
            $file,
            $line,
            $object,
            $type,
            $args,
            $message,
            $details,
            $correlationId,
            $userAgent
        );

        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    public function getTotal()
    {
        $total = 0;
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
        $name = "";
        $total = 0;
        $sql = "SELECT a.name, COUNT(1) as total FROM messages as m ";
        $sql .= "INNER JOIN applications as a ON m.application_id = a.id ";
        $sql .= "GROUP BY m.application_id;";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $stmt->bind_result($name, $total);
        $data = array();
        $data[] = array("Application", "Messages");
        while ($stmt->fetch()) {
            $data[] = array($name, $total);
        }
        $stmt->close();

        return $data;
    }

    private function getFieldList()
    {
        return array(
            "Application",
            "Message",
            "Correlation Id",
            "User-Agent",
            "Created At"
        );
    }

    private function getQuery()
    {
        $sql = "SELECT a.name, m.message, m.correlation_id, m.user_agent, CONVERT_TZ(m.created_at, '-03:00', '+01:00') AS `created_at`";
        $sql .= "FROM messages as m INNER JOIN applications as a ON m.application_id = a.id ";
        $sql .= "ORDER BY m.id DESC LIMIT 0, ?;";
        return $sql;
    }

    public function getGroupedMessages()
    {
        $sql = "SELECT `name`, `message`, `user_agent`, `messages_count`, ";
        $sql .= "CONVERT_TZ(`created_at_most_recent`, '-03:00', '+01:00') AS `created_at_most_recent` ";
        $sql .= "FROM `messages_view` ORDER BY `messages_count` DESC";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        $data = array();
        $data[] = array("Application", "Message", "User-Agent", "Messages", "Most recent");
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return array();
        }
        while ($row = $result->fetch_array(MYSQLI_NUM)) {
            $rowData = array_values($row);
            $rowData[2] = $this->convertUserAgentToLink($rowData[2]);
            $data[] = $rowData;
        }
        $stmt->close();

        return $data;
    }

    public function showLastMessages($quantity)
    {
        $stmt = $this->connection->prepare($this->getQuery());
        $stmt->bind_param("i", $quantity);
        $stmt->execute();
        $data = array();
        $data[] = $this->getFieldList();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            return array();
        }
        while ($row = $result->fetch_array(MYSQLI_NUM)) {
            $data[] = array_values($row);
        }
        $stmt->close();

        return $data;
    }

    public function getMessage($messageId)
    {
        $sql = "SELECT m.id, a.name, m.class, m.function, m.file, m.line, m.object, ";
        $sql .= "m.type, m.args, m.message, m.details, m.correlation_id, m.user_agent, ";
        $sql .= "CONVERT_TZ(m.created_at, '-03:00', '+01:00') AS `created_at` ";
        $sql .= "FROM messages as m INNER JOIN applications as a ON m.application_id = a.id ";
        $sql .= "WHERE m.id = ?;";
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param("i", $messageId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_array(MYSQLI_ASSOC);
        $data = $row;
        $stmt->close();

        return $data;
    }
}
