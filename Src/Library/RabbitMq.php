<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;
use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\Pancake\ShieldsIo;

class RabbitMq
{
    private $connectionStrings = array();

    private $request;

    public function __construct()
    {
        $config = new Configuration();
        $config->init();

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

    private function getColorByThreshold($quantity, $red, $orange, $yellow)
    {
        if ($quantity > $red) {
            return "red";
        }

        if ($quantity > $orange) {
            return "orange";
        }

        if ($quantity >= $yellow) {
            return "yellow";
        }

        return "green";
    }

    private function parseQueue($host, $queue, $shieldsIo)
    {
        $name = $queue["name"];
        $messages = $queue["messages"];
        $consumers = $queue["consumers"];

        $state = "Active";
        if (isset($queue["idle_since"])) {
            $dateDiff = intval((time() - strtotime($queue["idle_since"])) / 60);

            $hours = intval($dateDiff / 60);
            if (strlen($hours) === 1) {
                $hours = "0{$hours}";
            }

            $minutes = $dateDiff % 60;
            if (strlen($minutes) === 1) {
                $minutes = "0{$minutes}";
            }

            $state = "Idle time: {$hours}:{$minutes}";
        }

        if ($messages === 0 && str_ends_with($name, "-retry")) {
            return null;
        }

        $colorMessages = $this->getColorByThreshold($messages, 100, 50, 1);
        $badgeMessage = $shieldsIo->generateBadgeUrl($messages, $name, $colorMessages, "for-the-badge", "black", null);
        $imgMessages = "<img alt='queue length' src='{$badgeMessage}' />";
        $colorConsumers = isset($queue["idle_since"]) ? "red" : "green";
        $labelColor = $this->getColorByThreshold($consumers, 15, 5, 1);
        $badgeConsumers = $shieldsIo->generateBadgeUrl($consumers, $state, $colorConsumers, "for-the-badge", $labelColor, null);
        $imgConsumers = "<img alt='queue length' src='{$badgeConsumers}' />";
        $item = array($host, $imgMessages, $imgConsumers);

        return array("item" => $item, "messages" => $messages);
    }

    public function getQueueLength()
    {
        $shieldsIo = new ShieldsIo();
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
                $result = $this->parseQueue($server["host"], $queue, $shieldsIo);

                if ($result === null) {
                    continue;
                }

                $data["queues"][] = $result["item"];
                $data["total"] += $result["messages"];
            }
        }

        usort($data, function($a, $b) {
            return $b[1] <=> $a[1];
        });
        return $data;
    }
}
