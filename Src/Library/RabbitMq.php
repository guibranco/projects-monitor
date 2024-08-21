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

    private function getColorByThreshold($quantity, $red, $orange, $yellow) {
        if ($quantity > $red) {
            return "red";
        }
        
        if($quantity > $orange) {
            return "orange";
        }

        if ($quantity >= $yellow) {
            return "yellow";
        }

        return "green";
    }

    public function getQueueLength()
    {
        $data = array();
        $data["total"] = 0;
        $data["queues"][] = array("Server", "Queue", "Consumers");
        foreach ($this->getServers() as $server) {
            $headers = array("Authorization: Basic " . base64_encode($server["user"] . ":" . $server["password"]));
            $url = "https://" . $server["host"] . "/api/queues/" . $server["vhost"] . "/";
            $response = $this->request->get($url, $headers);
            if ($response->statusCode !== 200) {
                continue;
            }
            $node = json_decode($response->body, true);
            foreach ($node as $queue) {
                $name = $queue["name"];
                $messages = $queue["messages"];
                $consumers = $queue["consumers"];
                $state = $queue["state"];

                if ($quantity === 0 && str_ends_with($name, "-retry")) {
                    continue;
                }

                $colorMessages = getColorByThreshold($messages, 100, 50, 1);
                $imgMessages = "<img alt='queue length' src='https://img.shields.io/badge/" . $messages . "-" . str_replace("-", "--", $name) . "-" . $colorMessages . "?style=for-the-badge&labelColor=black' />";
                $colorConsumers = getColorByThrshold($consumers, 10, 5, 1);
                $imgConsumers = "<img alt='queue length' src='https://img.shields.io/badge/" . $consumers . "-" . str_replace("-", "--", $state) . "-" . $colorConsumers . "?style=for-the-badge&labelColor=black' />";
                $item = array($server["host"], $imgMessages, $imgConsumers);
                $data["queues"][] = $item;
                $data["total"] += $messages;
            }
        }
        ksort($data);
        return $data;
    }
}
