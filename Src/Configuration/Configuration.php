<?php

namespace GuiBranco\ProjectsMonitor\Configuration;

class Configuration
{
    public function __construct()
    {
        ini_set("default_charset", "UTF-8");
        ini_set("date.timezone", "America/Sao_Paulo");
        mb_internal_encoding("UTF-8");
    }

    public function getRequestHeaders()
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $headers[$name] = $value;
            }
        }

        $headers["REMOTE_ADDR"] = isset($_SERVER) && isset($_SERVER['REMOTE_ADDR'])
            ? $_SERVER['REMOTE_ADDR'] : "CRONJOB";
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
