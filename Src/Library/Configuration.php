<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\ProjectsMonitor\Library\TimeZone;

class Configuration
{
    public function __construct()
    {
        $timeZone = new TimeZone();
        ini_set("date.timezone", $timeZone->getTimeZone());
        ini_set("default_charset", "UTF-8");
        mb_internal_encoding("UTF-8");

        $this->setUserAgent();
    }

    private function setUserAgent()
    {
        $version = "1.0.0";
        $versionFile = "../../version.txt";
        if (file_exists($versionFile)) {
            $version = file_get_contents($versionFile);
        }

        define("USER_AGENT_VENDOR", "projects-monitor/{$version} (+https://github.com/guibranco/projects-monitor)");
        define("USER_AGENT", USER_AGENT_VENDOR);
    }

    public function getRequestHeaders()
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $headers[$name] = $value;
            }
        }

        $headers["REMOTE_ADDR"] = isset($_SERVER) && isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : "CRONJOB";
        $headers["HTTP_HOST"] = isset($_SERVER) && isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : "CRONJOB";
        $headers["REQUEST_URI"] = isset($_SERVER) && isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "/";
        return $headers;
    }

    public function getRequestData()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $headers = $this->getRequestHeaders();

        if (
            isset($headers["Content-Type"]) &&
            !empty($headers["Content-Type"]) &&
            $headers["Content-Type"] == "application/x-www-form-urlencoded"
        ) {
            $data = $_POST;
        }

        return $data;
    }
}
