<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\Library\Configuration;

class RabbitMq
{
    private $connectionStrings = array();

    private $request;

    public function __construct()
    {
        new Configuration();

        global $rabbitMqConnectionStrings;

        if (!file_exists(__DIR__ . "/../secrets/rabbitMq.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: rabbitMq.secrets.php");
        }

        require_once __DIR__ . "/../secrets/rabbitMq.secrets.php";

        $this->connectionStrings = $rabbitMqConnectionStrings;
        $this->request = new Request();
    }

    private function getServers()
    {
        $servers = [];
        foreach ($this->connectionStrings as $connectionString) {
            $url = parse_url($connectionString);
            $servers[] = [
                "host" => $url["host"],
                "port" => isset($url["port"]) ? $url["port"] : 5672,
                "user" => $url["user"],
                "password" => $url["pass"],
                "vhost" => ($url['path'] == '/' || !isset($url['path'])) ? '/' : substr($url['path'], 1)
            ];
        }

        return $servers;
    }

    public function getQueueLength()
    {
        $data = array();
        $data["total"] = 0;
        $data["queues"][] = array("Server", "Queue");
        foreach ($this->getServers() as $server) {
            $headers = array("Authorization: Basic " . base64_encode($server["user"] . ":" . $server["password"]));
            $url = "https://" . $server["host"] . "/api/queues/" . $server["vhost"] . "/";
            $response = $this->request->get($url, $headers);
            if ($response->statusCode !== 200) {
                break;
            }
            $node = json_decode($response->body, true);
            foreach ($node as $queue) {
                $color = "green";
                $name = $queue["name"];
                $quantity = $queue["messages"];

                if ($quantity === 0 && str_ends_with($name, "-retry")) {
                    continue;
                }

                if ($quantity > 100) {
                    $color = "red";
                } elseif ($quantity > 50) {
                    $color = "orange";
                } elseif ($quantity >= 1) {
                    $color = "yellow";
                }

                $img = "<img alt='queue length' src='https://img.shields.io/badge/" . $quantity . "-" . str_replace("-", "--", $name) . "-" . $color . "?style=for-the-badge&labelColor=black' />";
                $item = array($server["host"], $img);
                $data["queues"][] = $item;
                $data["total"] += $quantity;
            }
        }
        ksort($data);
        return $data;
    }
}
