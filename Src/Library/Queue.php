<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;

class Queue
{
    private $connectionStrings = array();

    private $request;

    public function __construct()
    {
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
        $data["queues"][] = array("Server", "Queue", "Messages");
        foreach ($this->getServers() as $server) {
            $headers = array("Authorization: Basic " . base64_encode($server["user"] . ":" . $server["password"]));
            $url = "https://" . $server["host"] . "/api/queues/" . $server["vhost"] . "/";
            $response = $this->request->get($url, $headers);
            if ($response->statusCode !== 200) {
                break;
            }
            $node = json_decode($response->body, true);
            foreach ($node as $queue) {
                $item = array($server["host"], $queue["name"], $queue["messages"]);
                $data["queues"][] = $item;
                $data["total"] += $queue["messages"];
            }
        }
        ksort($data);
        return $data;
    }
}
