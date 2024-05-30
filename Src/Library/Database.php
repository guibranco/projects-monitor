<?php

namespace GuiBranco\ProjectsMonitor\Library;

class Database
{
    private $host = "localhost";
    private $user = "";
    private $password = "";
    private $database = "";

    private $connection = null;

    public function getConnection()
    {
        return $this->connection;
    }

    public function __construct()
    {
        global $mySqlHost, $mySqlUser, $mySqlPassword, $mySqlDatabase;

        if (!file_exists(__DIR__ . "/../secrets/mySql.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: mySql.secrets.php");
        }

        require_once __DIR__ . "/../secrets/mySql.secrets.php";

        $this->host = $mySqlHost;
        $this->user = $mySqlUser;
        $this->password = $mySqlPassword;
        $this->database = $mySqlDatabase;

        if (empty($this->host) || empty($this->user) || empty($this->password) || empty($this->database)) {
            throw new SecretsFileNotFoundException("Invalid mySql.secrets.php");
        }

        $this->connect();
    }

    public function connect()
    {
        $this->connection = new \mysqli($this->host, $this->user, $this->password, $this->database);
        if ($this->connection->connect_error) {
            throw new RequestException("Connection failed: " . $this->connection->connect_error);
        }

        $this->connection->set_charset("utf8mb4");
    }
}
