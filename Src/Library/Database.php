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

    /**
     * Connects to the database, retrying once after 5 seconds when the first
     * attempt fails. A connection that succeeds is used at once: the wait only
     * happens after a failure.
     *
     * @param boolean $isRetry Whether this is the retry.
     *
     * @return void
     *
     * @throws RequestException When the retry fails too.
     */
    public function connect($isRetry = false)
    {
        $error = $this->openConnection();
        if ($error !== null && $isRetry === false) {
            $this->pause(5);
            $this->connect(true);
            return;
        }

        if ($error !== null) {
            throw new RequestException(sprintf("Connection failed: %s", $error));
        }

        $this->connection->set_charset("utf8mb4");
        $this->connection->query(sprintf("SET time_zone = '%s'", date("P")));

    }//end connect()


    /**
     * Opens the connection.
     *
     * @return string|null Why it failed, null when it is open.
     */
    protected function openConnection(): ?string
    {
        try {
            $this->connection = new \mysqli($this->host, $this->user, $this->password, $this->database);
        } catch (\mysqli_sql_exception $exception) {
            $this->connection = null;
            return $exception->getMessage();
        }

        if ($this->connection->connect_error !== null) {
            return $this->connection->connect_error;
        }

        return null;

    }//end openConnection()


    /**
     * Waits before the retry.
     *
     * @param integer $seconds How long.
     *
     * @return void
     */
    protected function pause(int $seconds): void
    {
        sleep($seconds);

    }//end pause()


}
