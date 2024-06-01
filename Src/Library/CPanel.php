<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\Request;

class CPanel
{
    private $baseUrl;

    private $apiToken;

    private $username;

    private $request;

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

    public function getAllErrors()
    {
        $result = array();
        $items = $this->searchFiles("error_log", "/");

        foreach ($items as $item) {
            $pathInfo = pathinfo($item->file);

            $parameters = array(
                "cpanel_jsonapi_module" => "Fileman",
                "cpanel_jsonapi_func" => "statfiles",
                "cpanel_jsonapi_apiversion" => "2",
                "dir" => $pathInfo["dirname"],
                "files" => $pathInfo["basename"]
            );
            $response = $this->getRequest("json-api", "cpanel", $parameters);
            $stats = $response->cpanelresult->data;
            $creationDate = date("H:i:s d/m/Y", $stats[0]->ctime);
            $modifiedDate = date("H:i:s d/m/Y", $stats[0]->mtime);

            $result[] = array(str_replace("/home/{$this->username}/", "", $pathInfo["dirname"]), $stats[0]->humansize, $creationDate, $modifiedDate);
        }

        sort($result, SORT_ASC);

        array_unshift($result, array("Directory", "Size", "Creation time", "Modification time"));

        return $result;

    }
}
