<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;

class CPanel
{
    private $baseUrl;

    private $apiToken;

    private $username;

    private $request;

    private const REGEX_PATTERN =
        "/\[(?<date>\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2} [A-Za-z\/_]+?)\]" .
        "\s(?<error>.+?)(?<multilineError>\n(.|\n)+?) in (?<file>.+?\.php)(?:\son line\s|:)(?<line>\d+)" .
        "(\nStack trace:\n(?<stackTrace>#\d+ .+?\n)*\s+thrown in .+?\.php on line \d+)?/";
    
    public function __construct()
    {
        global $cPanelApiToken, $cPanelBaseUrl, $cPanelUsername;

        if (!file_exists(__DIR__ . "/../secrets/cPanel.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: cPanel.secrets.php");
        }

        require_once __DIR__ . "/../secrets/cPanel.secrets.php";

        $this->baseUrl = $cPanelBaseUrl;
        $this->apiToken = $cPanelApiToken;
        $this->username = $cPanelUsername;
        $this->request = new Request();
    }

    private function getRequest($module, $action, $parameters)
    {
        $url = $this->baseUrl . "/" . $module . "/" . $action . "?" . http_build_query($parameters);
        $headers = [
            "Authorization: cpanel {$this->username}:{$this->apiToken}",
            "Accept: application/json",
            "User-Agent: ProjectsMonitor/1.0 (+https://github.com/guibranco/projects-monitor)"
        ];

        $response = $this->request->get($url, $headers);

        if ($response->statusCode != 200) {
            throw new RequestException("Code: {$response->statusCode} - Error: {$response->body}");
        }

        return json_decode($response->body);
    }

    private function searchFiles($regex, $dir)
    {
        $parameters = array(
            "cpanel_jsonapi_module" => "Fileman",
            "cpanel_jsonapi_func" => "search",
            "cpanel_jsonapi_apiversion" => "2",
            "regex" => $regex,
            "dir" => $dir
        );
        $result = $this->getRequest("json-api", "cpanel", $parameters);
        return $result->cpanelresult->data;
    }

    private function loadStats($fullPath)
    {
        $pathInfo = pathinfo($fullPath);
        $parameters = array(
            "cpanel_jsonapi_module" => "Fileman",
            "cpanel_jsonapi_func" => "statfiles",
            "cpanel_jsonapi_apiversion" => "2",
            "dir" => $pathInfo["dirname"],
            "files" => $pathInfo["basename"]
        );
        $response = $this->getRequest("json-api", "cpanel", $parameters);
        $stats = $response->cpanelresult->data;
        $ctime = date("H:i:s d/m/Y", $stats[0]->ctime);
        $mtime = date("H:i:s d/m/Y", $stats[0]->mtime);
        return array(
            "fullPath" => $fullPath,
            "dirname" => $pathInfo["dirname"],
            "basename" => $pathInfo["basename"],
            "size" => $stats[0]->size,
            "ctime" => $ctime,
            "mtime" => $mtime,
            "type" => $stats[0]->type,
            "humansize" => $stats[0]->humansize
        );
    }

    private function loadContent($fullPath)
    {
        $pathInfo = pathinfo($fullPath);
        $parameters = array(
            "cpanel_jsonapi_module" => "Fileman",
            "cpanel_jsonapi_func" => "viewfile",
            "cpanel_jsonapi_apiversion" => "2",
            "dir" => $pathInfo["dirname"],
            "file" => $pathInfo["basename"]
        );
        $response = $this->getRequest("json-api", "cpanel", $parameters);
        $content = $response->cpanelresult->data;
        return array(
            "fullPath" => $fullPath,
            "dirname" => $pathInfo["dirname"],
            "basename" => $pathInfo["basename"],
            "contents" => $content[0]->contents
        );
    }

    public function getErrorLogFiles()
    {
        $result = array();
        $items = $this->searchFiles("error_log", "/");

        foreach ($items as $item) {
            $stats = $this->loadStats($item->file);
            $result[] = array(str_replace("/home/{$this->username}/", "", $stats["dirname"]), $stats["humansize"], $stats["mtime"], $stats["ctime"]);
        }

        sort($result, SORT_ASC);

        array_unshift($result, array("Directory", "Size", "Creation time", "Modification time"));

        return $result;
    }

    public function getErrorLogMessages()
    {
        $result = array();
        $items = $this->searchFiles("error_log", "/");

        foreach ($items as $item) {
            $content = $this->loadContent($item->file);
            preg_match_all(CPanel::REGEX_PATTERN, $content["contents"], $matches);
            foreach ($matches["error"] as $index => $match) {
                $dir = str_replace("/home/{$this->username}/", "", $content["dirname"]);
                $file = str_replace("/home/{$this->username}/", "", $matches["file"][$index]);
                $lineItem = array($matches["date"][$index], $dir, $match, $file, $matches["line"][$index]);
                $result[] = $lineItem;
            }
        }

        sort($result, SORT_ASC);

        array_unshift($result, array("Date", "Error Log", "Error", "File", "Line"));

        return $result;
    }
}