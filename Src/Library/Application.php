<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\ProjectsMonitor\Configuration\Config;
use GuiBranco\ProjectsMonitor\Library\Database;

class Application
{
    private $config = null;
    private $database = null;
    private $application = null;

    public function getApplicationId(){
        return $this->application["id"];
    }

    public function __construct()
    {
        $this->config = new Config();
        $this->database = new Database();
    }

    public function validate()
    {
        $headers = $this->config->getRequestHeaders();

        if (!isset($headers["X-API-KEY"]) || !isset($headers["X-API-SECRET"])) {
            http_response_code(401);
            return false;
        }

        $appKey = $headers["X-API-KEY"];
        $appSecret = $headers["X-API-SECRET"];

        $conn = $this->database->getConn();

        $sql = "SELECT * FROM applications WHERE app_key = :app_key AND app_secret = :app_secret";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(":app_key", $appKey);
        $stmt->bindParam(":app_secret", $appSecret);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            http_response_code(403);
            return false;
        }

        $this->application = $stmt->fetch();

        $stmt->close();

        print_r($this->application);
    }
}