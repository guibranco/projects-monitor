<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\ShieldsIo;

class CPanel
{
    private $baseUrl;

    private $apiToken;

    private $username;

    private $request;

    /* The above code is defining a private constant in PHP with the name `REGEX_PATTERN` and assigning
    it a multi-line string value enclosed in triple hash symbols (` */
    private const REGEX_PATTERN =
        "/\[(?<date>\d{2}-[A-Za-z]{3}-\d{4}\s\d{2}:\d{2}:\d{2}\s[A-Za-z\/_]+?)\]\s(?<error>.+?)" .
        "(?:(?<multilineError>\n(?:.|\n)+?)\sin\s(?<file>.+?\.php)(?:\son\sline\s|:)(?<line>\d+))?" .
        "(?<stackTrace>\nStack\strace:\n(?<stackTraceDetails>(?:#\d+\s.+?\n)*)\s+thrown\sin\s.+?\.php\son\sline\s\d+)?$/m";

    /**
     * The constructor initializes configuration settings and loads cPanel API credentials from a
     * secrets file.
     */
    public function __construct()
    {
        $config = new Configuration();
        $config->init();

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

    /**
     * The function getRequest sends a HTTP GET request with specified module, action, and parameters,
     * handling errors and returning the JSON response.
     * 
     * @param string module The `module` parameter in the `getRequest` function represents the module
     * or endpoint of the API that you want to make a request to. It is typically a string that
     * specifies the specific functionality or resource you are interacting with. For example, it could
     * be "users", "products", "orders",
     * @param string action The `action` parameter in the `getRequest` function represents the specific
     * action or operation that you want to perform within the specified module. It could be a CRUD
     * operation like create, read, update, or delete, or any other action that the module supports.
     * This parameter helps in constructing the URL for
     * @param array parameters The `getRequest` function you provided is a private function that sends
     * a GET request to a specified URL with the given parameters. The function constructs the URL
     * using the base URL, module, action, and parameters provided. It then sets the necessary headers
     * including Authorization, Accept, and User-Agent. The function
     * 
     * @return mixed The function `getRequest` is returning the decoded JSON response body from a GET
     * request made to a specific URL with the provided headers. If the response status code is not
     * 200, it will throw a `RequestException` with details about the error.
     */
    private function getRequest(string $module, string $action, array|null $parameters): mixed
    {
        $url = $this->baseUrl . "/" . $module . "/" . $action . "?" . http_build_query($parameters);
        $headers = [
            "Authorization: cpanel {$this->username}:{$this->apiToken}",
            "Accept: application/json",
            constant("USER_AGENT")
        ];

        $response = $this->request->get($url, $headers);

        if ($response->statusCode != 200) {
            $error = $response->statusCode == -1 ? $response->error : $response->body;
            throw new RequestException("Code: {$response->statusCode} - Error: {$error}");
        }

        return json_decode($response->body);
    }

    /**
     * The function `searchFiles` uses cPanel API to search for files matching a regex pattern in a
     * specified directory.
     * 
     * @param regex The `regex` parameter in the `searchFiles` function is used to specify a regular
     * expression pattern that will be used to search for files in the specified directory (``).
     * The function sends a request to the cPanel API using the provided regex pattern and directory
     * path to search for files that match
     * @param dir The `dir` parameter in the `searchFiles` function represents the directory path where
     * you want to search for files. It specifies the directory within which the search operation will
     * be performed. You need to provide the directory path as a string when calling the `searchFiles`
     * function.
     * 
     * @return The function `searchFiles` is returning the data obtained from a cPanel API call to
     * search for files matching a specified regex pattern in a specified directory. The function
     * constructs the necessary parameters for the API call, sends the request using the `getRequest`
     * method, and then returns the data retrieved from the API call.
     */
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

    /**
     * The function `loadStats` retrieves file statistics using cPanel's JSON API and returns an array
     * containing information such as file size, creation time, modification time, and file type.
     * 
     * @param fullPath The `loadStats` function is used to load statistics (stats) for a file specified
     * by the `fullPath` parameter. The function extracts the directory path and file name from the
     * `fullPath`, then makes a request to the cPanel API to get the stats for that file.
     * 
     * @return An array containing information about the file specified by the `` parameter is
     * being returned. The array includes the following keys and corresponding values:
     * - "fullPath": The full path of the file
     * - "dirname": The directory name of the file
     * - "basename": The base name of the file
     * - "size": The size of the file
     * - "ctime": The creation time
     */
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
        if (!isset($stats[0])) {
            throw new RequestException("Unable to get stats for file: " . $fullPath);
        }
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

    /**
     * The function `loadContent` retrieves the contents of a file using cPanel's Fileman module and
     * returns relevant information about the file.
     * 
     * @param fullPath The `loadContent` function you provided is a private function that loads the
     * content of a file specified by the `fullPath` parameter. The function extracts the directory and
     * file name from the full path, constructs parameters for a cPanel API request to view the file,
     * sends the request, and returns
     * 
     * @return An array is being returned with the following keys and values:
     * - "fullPath" => the full path of the file
     * - "dirname" => the directory name of the file
     * - "basename" => the base name of the file
     * - "contents" => the contents of the file obtained from the response
     */
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

        if (empty($content) || !isset($content[0]->contents)) {
            return null;
        }

        return array(
            "fullPath" => $fullPath,
            "dirname" => $pathInfo["dirname"],
            "basename" => $pathInfo["basename"],
            "contents" => $content[0]->contents
        );
    }

    /**
     * The function `getErrorLogFiles` retrieves information about error log files, excluding those in
     * a ".trash" directory, and returns an array sorted by directory, size, and creation time.
     * 
     * @return The function `getErrorLogFiles` returns an array of error log files with their
     * directory, size, and creation time. If no error log files are found, an empty array is returned.
     * The array is sorted in ascending order by directory name. The first element of the array is an
     * array containing column headers: "Directory", "Size", "Creation time".
     */
    public function getErrorLogFiles()
    {
        $result = array();
        $items = $this->searchFiles("error_log", "/");

        foreach ($items as $item) {
            if (strpos($item->file, ".trash") !== false) {
                continue;
            }

            $stats = $this->loadStats($item->file);
            $result[] = array(str_replace("/home/{$this->username}/", "", $stats["dirname"]), $stats["humansize"], $stats["mtime"]);
        }

        if (empty($result)) {
            return $result;
        }

        sort($result, SORT_ASC);
        array_unshift($result, array("Directory", "Size", "Creation time"));

        return $result;
    }

    /**
     * The function `getErrorLogMessages` retrieves error log messages from files, parses the content,
     * and returns an array of error log details sorted by date.
     * 
     * @return The function `getErrorLogMessages` returns an array of error log messages. Each message
     * includes the date, directory, error message, file, and line number where the error occurred. If
     * no error log messages are found, an empty array is returned. The array is sorted by date in
     * ascending order, and the column headers are included at the beginning of the array.
     */
    public function getErrorLogMessages()
    {
        $result = array();
        $items = $this->searchFiles("error_log", "/");

        foreach ($items as $item) {
            if (strpos($item->file, ".trash") !== false) {
                continue;
            }

            $content = $this->loadContent($item->file);

            if ($content === null) {
                continue;
            }

            preg_match_all(CPanel::REGEX_PATTERN, $content["contents"], $matches);
            foreach ($matches["error"] as $index => $match) {
                $date = date("H:i:s d/m/Y", strtotime($matches["date"][$index]));
                $dir = str_replace("/home/{$this->username}/", "", $content["dirname"]);
                $file = str_replace("/home/{$this->username}/", "", $matches["file"][$index]);
                $line = $matches["line"][$index];
                $result[] = array($date, $dir, $match, $file, $line);
            }
        }

        if (empty($result)) {
            return $result;
        }

        ksort($result, SORT_ASC);
        array_unshift($result, array("Date", "Error Log", "Error", "File", "Line"));
        return $result;
    }

    /**
     * The function `getCrons` retrieves cron jobs from cPanel, formats them, and returns an array with
     * time expressions and corresponding commands.
     * 
     * @return array|null The `getCrons` function returns an array of cron jobs with their
     * corresponding time expressions and commands. The array includes a header row with "Expression"
     * and "Command" labels, followed by each cron job entry consisting of a time expression badge and
     * the associated command.
     */
    public function getCrons(): array|null
    {
        $result = array();
        $parameters = array(
            "cpanel_jsonapi_module" => "Cron",
            "cpanel_jsonapi_func" => "listcron",
            "cpanel_jsonapi_apiversion" => "2"
        );
        $response = $this->getRequest("json-api", "cpanel", $parameters);

        if ($response === null || !isset($response->cpanelresult->data)) {
            error_log("Error getting crons from cPanel");
            return null;
        }

        $lines = $response->cpanelresult->data;
        $badge = new ShieldsIo();
        foreach ($lines as $line) {
            if (!isset($line->command) || $line->command == null) {
                continue;
            }
            $command = str_replace("/home/zerocool/", "", str_replace("/usr/local/bin/", "", $line->command));
            $time = $line->minute . " " . $line->hour . " " . $line->day . " " . $line->month . " " . $line->weekday;
            $badgeUrl = $badge->generateBadgeUrl("⏰", $time, "black", "for-the-badge", "white", null);
            $timeBadge = "<img alt='Cron expression' src='{$badgeUrl}' />";
            $result[] = array($timeBadge, $command);
        }

        sort($result, SORT_ASC);
        array_unshift($result, array("Expression", "Command"));
        return $result;
    }

    /**
     * The function `getUsageData` retrieves and formats usage data from a cPanel endpoint for specific
     * resource types.
     * 
     * @return array|null The `getUsageData` function returns an array of usage data for specific
     * resources like CPU, memory, processes, etc. The data is fetched from a cPanel endpoint,
     * processed, and then returned as an array containing information such as resource ID,
     * description, current usage, and maximum usage. If there is an error in fetching the data or if
     * the response is empty, the function returns `null
     */
    public function getUsageData(): array|null
    {
        $endpoint = 'execute/ResourceUsage/get_usages';
        $response = $this->getRequest($endpoint, '', []);

        if ($response === null || empty($response->data)) {
            error_log("Error getting usage data from cPanel");
            return null;
        }

        $result = [];
        foreach ($response->data as $item) {
            if (in_array($item->id, ['lvecpu', 'lvememphy', 'lvenproc', 'lveep', 'ftp_accounts', 'mysql_databases'])) {

                if ($item->formatter === "format_bytes") {
                    $item->usage = $item->usage / 1024 / 1024;
                    $item->maximum = $item->maximum / 1024 / 1024;
                }

                $result[] = [
                    'id' => $item->id,
                    'description' => $item->description,
                    'usage' => $item->usage,
                    'maximum' => $item->maximum
                ];
            }
        }

        return $result;
    }
}
