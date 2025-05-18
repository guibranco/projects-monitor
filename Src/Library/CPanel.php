<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\ProjectsMonitor\Library\Configuration;
use GuiBranco\ProjectsMonitor\Library\LogParser;
use GuiBranco\Pancake\Request;
use GuiBranco\Pancake\ShieldsIo;

class CPanel
{
    private $baseUrl;

    private $apiToken;

    private $username;

    private $emailAccount;

    private $request;

    /**
     * The constructor initializes configuration settings and loads cPanel API credentials from a
     * secrets file.
     */
    public function __construct()
    {
        $config = new Configuration();
        $config->init();

        global $cPanelApiToken, $cPanelBaseUrl, $cPanelUsername, $cPanelEmailAccount;

        if (!file_exists(__DIR__ . "/../secrets/cPanel.secrets.php")) {
            throw new SecretsFileNotFoundException("File not found: cPanel.secrets.php");
        }

        require_once __DIR__ . "/../secrets/cPanel.secrets.php";

        $this->baseUrl = $cPanelBaseUrl;
        $this->apiToken = $cPanelApiToken;
        $this->username = $cPanelUsername;
        $this->emailAccount = $cPanelEmailAccount;
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
        $url = $this->baseUrl . "/" . $module;
        if (!empty($action)) {
            $url .= "/" . $action;
        }

        if ($parameters !== null && count($parameters) > 0) {
            $url .= "?" . http_build_query($parameters);
        }

        $headers = [
            "Authorization: cpanel {$this->username}:{$this->apiToken}",
            "Accept: application/json",
            constant("USER_AGENT")
        ];

        $response = $this->request->get($url, $headers);

        if ($response->getStatusCode() != 200) {
            $error = $response->getStatusCode() == -1 ? $response->getMessage() : $response->getBody();
            throw new RequestException("Code: {$response->getStatusCode()} - Error: {$error}");
        }

        return json_decode($response->getBody());
    }

    /**
     * The function `searchFiles` uses cPanel API to search for files matching a regex pattern in a
     * specified directory.
     *
     * @param string regex The `regex` parameter in the `searchFiles` function is used to specify a
     * regular expression pattern that will be used to search for files in the specified directory
     * (``). The function sends a request to the cPanel API using the provided regex pattern and
     * directory path to search for files that match
     * @param string dir The `dir` parameter in the `searchFiles` function represents the directory
     * path where you want to search for files matching the specified regex pattern. This parameter
     * should be a string that specifies the directory path in which you want to perform the search.
     * For example, it could be something like "/home/user
     *
     * @return mixed The function `searchFiles` is returning the data obtained from a cPanel API call
     * to search for files based on the provided regex pattern and directory. The function returns the
     * data retrieved from the API call, specifically the data within the `cpanelresult` object.
     */
    private function searchFiles(string $regex, string $dir): mixed
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
     * The function `loadStats` retrieves file statistics using cPanel's JSON API and returns an
     * array of relevant information.
     *
     * @param string fullPath The `fullPath` parameter in the `loadStats` function is a string that
     * represents the full path of a file for which you want to load statistics. It is used to
     * extract the directory name and file name from the path to make an API request to get the
     * file statistics.
     *
     * @return array An array containing information about the file specified by the ``.
     * The array includes the following keys:
     * - "fullPath": The full path of the file
     * - "dirname": The directory name of the file
     * - "basename": The base name of the file
     * - "size": The size of the file
     * - "ctime": The creation time of the file formatted as "H:i
     */
    private function loadStats(string $fullPath): array
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
     * The function `loadContent` retrieves file content using cPanel JSON API and returns an array
     * with file information if successful, otherwise null.
     *
     * @param string fullPath The `fullPath` parameter in the `loadContent` function is a string that
     * represents the full path to a file. It is used to extract the directory name and the file name
     * from the path in order to make a request to retrieve the content of the file using cPanel's JSON
     * API.
     *
     * @return array|null The function `loadContent` returns an array with the following keys and
     * values:
     * - "fullPath" => the full path of the file
     * - "dirname" => the directory name of the file
     * - "basename" => the base name of the file
     * - "contents" => the contents of the file
     */
    private function loadContent(string $fullPath): array|null
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
     * This PHP function retrieves error log files, excluding those in a ".trash" directory, and
     * returns an array of directory paths, file sizes, and creation times sorted in ascending order.
     *
     * @return array An array of error log files with their directory, size, and creation time
     * information is being returned. If no error log files are found, an empty array is returned. The
     * array is sorted in ascending order based on directory names. The first element of the array
     * contains column headers for "Directory", "Size", and "Creation time".
     */
    public function getErrorLogFiles(): array
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
     * and returns an array of formatted error log entries.
     *
     * @return array An array of error log messages is being returned. Each error log message includes
     * the date, directory, error message, file, and line number where the error occurred. The array is
     * sorted in ascending order by date and includes a header row with column names: "Date", "Error
     * Log", "Error", "File", "Line". If no error log messages are found, an empty array is returned
     */
    public function getErrorLogMessages(): array
    {
        $result = array();
        $items = $this->searchFiles("error_log", "/");

        $parser = new LogParser();
        foreach ($items as $item) {
            if (strpos($item->file, ".trash") !== false) {
                continue;
            }

            $content = $this->loadContent($item->file);

            if ($content === null) {
                continue;
            }

            $errors = $parser->parse($content["contents"]);
            foreach ($errors as $error) {
                $date = date("H:i:s d/m/Y", strtotime($error['date']));
                $dir = str_replace("/home/{$this->username}/", "", $content["dirname"]);
                $file = str_replace("/home/{$this->username}/", "", $error['file']);
                $result[] = array($date, $dir, $error['multilineError'], $file, $error['line']);
            }
        }

        if (empty($result)) {
            return $result;
        }

        ksort($result, SORT_ASC);
        array_unshift($result, array("Date", "Directory", "Error", "File", "Line"));
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
            return null;
        }

        $result = [];
        foreach ($response->data as $item) {
            if (in_array($item->id, ['lvecpu', 'lvememphy', 'lvenproc'])) {

                if ($item->formatter === "format_bytes") {
                    $item->usage = number_format($item->usage / 1024 / 1024, 2, '.', '');
                    $item->maximum = number_format($item->maximum / 1024 / 1024, 2, '.', '');
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

    /**
     * Retrieves the count of messages in the inbox.
     *
     * @return int The number of messages in the inbox.
     */
    public function getInboxMessagesCount(): int
    {
        $endpoint = 'execute/Mailboxes/get_mailbox_status_list';
        $response = $this->getRequest($endpoint, '', ["account" => $this->emailAccount]);

        if ($response === null || empty($response->data)) {
            return 0;
        }

        $inboxMessagesCount = 0;
        foreach ($response->data as $item) {
            if ($item->mailbox === "INBOX") {
                $inboxMessagesCount = $item->messages;
                break;
            }
        }

        return $inboxMessagesCount;
    }

    /**
    * Deletes an error log file by filename.
    *
    * @param string $directory The directory where ther error_log file to delete is
    * @return bool True if the file was successfully deleted, false otherwise
    */
    public function deleteErrorLogFile($directory): bool
    {
        $fullFilename = "/home/{$this->username}/{$directory}/error_log";
        $parameters = array(
            "cpanel_jsonapi_module" => "Fileman",
            "cpanel_jsonapi_func" => "fileop",
            "cpanel_jsonapi_apiversion" => "2",
            "op" => "trash",
            "sourcefiles" => $fullFilename
        );

        try {
            $response = $this->getRequest("json-api", "cpanel", $parameters);
            return isset($response->cpanelresult->data[0]->result) === true && $response->cpanelresult->data[0]->result === 1;
        } catch (RequestException $e) {
            return false;
        }
    }
}
