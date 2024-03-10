<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\ProjectsMonitor\Configuration\Config;
use GuiBranco\ProjectsMonitor\Library\Database;

class Application
{
    private $config = null;
    private $database = null;
    private $application = null;

    public function getApplicationId()
    {
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

        $sql = "SELECT * FROM applications WHERE app_key = ? AND app_secret = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $appKey, $appSecret);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_array(MYSQLI_ASSOC);

        if($row ==null){
            http_response_code(403);
            return false;
        }

        $this->application = $row;

        $stmt->close();
    }
}
