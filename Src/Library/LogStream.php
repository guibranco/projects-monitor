<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\LogStream as PancakeLogStream;
use GuiBranco\Pancake\Response;

/**
 * Sends log entries to LogStream.
 *
 * Messages are message templates: `{name}` placeholders refer to keys of the
 * context (dotted names reach nested arrays) and are rendered by logstream-server,
 * which keeps the original text as `message_template`. Every entry from the same
 * line can then be found with `?message_template=` whatever the values were:
 *
 * ```php
 * (new LogStream())->warning("SSH connection to {host} failed: {reason}", ["host" => $name, "reason" => $e->getMessage(), "exception" => $e], "ssh");
 * ```
 *
 * Every entry also gets, without the caller passing it (see LogContext):
 *
 * - `request`: method, path, script, host, client IP, user agent and user (web only)
 * - `process`: script and number of arguments (CLI only)
 * - `runtime`: hostname, PID, SAPI, PHP version, peak memory and elapsed time
 * - `source`:  file, line and function of the call site
 *
 * Every entry of one request (or CLI run) shares a trace id: the incoming
 * `X-Request-Id`/`X-Trace-Id` header when valid, otherwise a generated UUID.
 * Throwables at the top level of the context are expanded.
 *
 * The client, the server variables and the user are shared by every instance:
 * bootstrap.php registers $_SERVER and session_validator.php the user, so no
 * class reads superglobals itself.
 */
class LogStream
{
    private const DEFAULT_APP_KEY = "projects-monitor";

    private const LEVELS = "|debug|info|notice|warning|error|critical|";

    private const MAX_ERROR_LENGTH = 500;

    /**
     * The Pancake client, or null when LogStream is not configured.
     *
     * @var PancakeLogStream|null
     */
    private static $client = null;

    /**
     * Whether the secrets file was already read.
     *
     * @var boolean
     */
    private static $initialized = false;

    /**
     * The trace id shared by every entry of this request or CLI run.
     *
     * @var string|null
     */
    private static $traceId = null;

    /**
     * The server variables of this request or CLI run.
     *
     * @var array
     */
    private static $server = [];

    /**
     * The logged in user, or null.
     *
     * @var mixed
     */
    private static $userId = null;


    /**
     * Registers the server variables ($_SERVER) of this request or CLI run.
     *
     * @param array $server The server variables.
     *
     * @return void
     */
    public function setServer(array $server): void
    {
        self::$server  = $server;
        self::$traceId = null;

    }//end setServer()


    /**
     * Registers the logged in user.
     *
     * @param mixed $userId The user id, or null.
     *
     * @return void
     */
    public function setUserId(mixed $userId): void
    {
        self::$userId = $userId;

    }//end setUserId()


    /**
     * Replaces the client read from the secrets file; null disables sending.
     *
     * @param PancakeLogStream|null $client The client.
     *
     * @return void
     */
    public function setClient(?PancakeLogStream $client): void
    {
        self::$client      = $client;
        self::$initialized = true;

    }//end setClient()


    /**
     * The trace id shared by every entry of this request or CLI run.
     *
     * @return string The trace id.
     */
    public function getTraceId(): string
    {
        if (self::$traceId === null) {
            self::$traceId = (new LogContext())->resolveTraceId(self::$server);
        }

        return self::$traceId;

    }//end getTraceId()


    /**
     * Logs a debug entry.
     *
     * @param string     $message  The message template.
     * @param array|null $context  The values of the placeholders and any other data.
     * @param string     $category The category.
     *
     * @return void
     */
    public function debug(string $message, ?array $context=null, string $category=""): void
    {
        $this->write("debug", $message, $context, $category);

    }//end debug()


    /**
     * Logs an info entry.
     *
     * @param string     $message  The message template.
     * @param array|null $context  The values of the placeholders and any other data.
     * @param string     $category The category.
     *
     * @return void
     */
    public function info(string $message, ?array $context=null, string $category=""): void
    {
        $this->write("info", $message, $context, $category);

    }//end info()


    /**
     * Logs a notice entry.
     *
     * @param string     $message  The message template.
     * @param array|null $context  The values of the placeholders and any other data.
     * @param string     $category The category.
     *
     * @return void
     */
    public function notice(string $message, ?array $context=null, string $category=""): void
    {
        $this->write("notice", $message, $context, $category);

    }//end notice()


    /**
     * Logs a warning entry.
     *
     * @param string     $message  The message template.
     * @param array|null $context  The values of the placeholders and any other data.
     * @param string     $category The category.
     *
     * @return void
     */
    public function warning(string $message, ?array $context=null, string $category=""): void
    {
        $this->write("warning", $message, $context, $category);

    }//end warning()


    /**
     * Logs an error entry.
     *
     * @param string     $message  The message template.
     * @param array|null $context  The values of the placeholders and any other data.
     * @param string     $category The category.
     *
     * @return void
     */
    public function error(string $message, ?array $context=null, string $category=""): void
    {
        $this->write("error", $message, $context, $category);

    }//end error()


    /**
     * Logs a critical entry.
     *
     * @param string     $message  The message template.
     * @param array|null $context  The values of the placeholders and any other data.
     * @param string     $category The category.
     *
     * @return void
     */
    public function critical(string $message, ?array $context=null, string $category=""): void
    {
        $this->write("critical", $message, $context, $category);

    }//end critical()


    /**
     * Logs an entry at a level chosen at runtime; an unknown level logs an error.
     *
     * @param string     $level    One of debug, info, notice, warning, error or critical.
     * @param string     $message  The message template.
     * @param array|null $context  The values of the placeholders and any other data.
     * @param string     $category The category.
     *
     * @return void
     */
    public function log(string $level, string $message, ?array $context=null, string $category=""): void
    {
        $resolved = "error";
        if (str_contains(self::LEVELS, "|{$level}|") === true) {
            $resolved = $level;
        }

        $this->write($resolved, $message, $context, $category);

    }//end log()


    /**
     * Logs the outcome of an HTTP call: debug when a GET succeeded, info when
     * another method succeeded, warning on a 4xx answer, error on a 5xx answer
     * or a transport failure.
     *
     * @param string   $service   The called service, e.g. "Webhooks API".
     * @param string   $method    The HTTP method.
     * @param Response $response  The response.
     * @param float    $startedAt When the call started (microtime(true)).
     * @param string   $category  The category.
     *
     * @return void
     */
    public function logResponse(string $service, string $method, Response $response, float $startedAt, string $category): void
    {
        $statusCode = $response->getStatusCode();

        $context                = [];
        $context["service"]     = $service;
        $context["method"]      = strtoupper($method);
        $context["endpoint"]    = parse_url($response->getUrl(), PHP_URL_PATH);
        $context["status_code"] = $statusCode;
        $context["duration_ms"] = (int) round(((microtime(true) - $startedAt) * 1000));

        $query = parse_url($response->getUrl(), PHP_URL_QUERY);
        if (is_string($query) === true) {
            $context["endpoint"] = sprintf("%s?%s", $context["endpoint"], $query);
        }

        $level = "debug";
        if ($context["method"] !== "GET") {
            $level = "info";
        }

        $message = "{service} {method} {endpoint} responded {status_code} in {duration_ms} ms";
        if ($statusCode >= 400) {
            $level            = "warning";
            $message          = "{service} {method} {endpoint} responded {status_code} in {duration_ms} ms: {error}";
            $context["error"] = (new LogContext())->truncate((string) $response->getBody(), self::MAX_ERROR_LENGTH);
        }

        if ($statusCode >= 500 || $statusCode === -1) {
            $level = "error";
        }

        if ($statusCode === -1) {
            $message          = "{service} {method} {endpoint} failed after {duration_ms} ms: {error}";
            $context["error"] = $response->getMessage();
        }

        $this->write($level, $message, $context, $category);

    }//end logResponse()


    /**
     * Sends an entry with the ambient context, unless LogStream is not configured.
     * A failure to send never breaks the request being logged.
     *
     * @param string     $level    The level.
     * @param string     $message  The message template.
     * @param array|null $context  The context given by the caller.
     * @param string     $category The category.
     *
     * @return void
     */
    private function write(string $level, string $message, ?array $context, string $category): void
    {
        $client = $this->getClient();
        if ($client === null) {
            return;
        }

        $logContext  = new LogContext();
        $skipFiles   = [__FILE__];
        $callSite    = $logContext->getCallSite(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4), $skipFiles);
        $fullContext = $logContext->build($context, self::$server, self::$userId, $callSite, PHP_SAPI);

        try {
            $client->{$level}($message, $fullContext, $category, $this->getTraceId());
        } catch (\Throwable $exception) {
            error_log(sprintf("LogStream: failed to send entry: %s", $exception->getMessage()));
        }

    }//end write()


    /**
     * The client, created from secrets/logStream.secrets.php on first use.
     *
     * @return PancakeLogStream|null The client, or null when LogStream is not configured.
     */
    private function getClient(): ?PancakeLogStream
    {
        if (self::$initialized === false) {
            self::$initialized = true;
            self::$client      = $this->createClient(__DIR__."/../secrets/logStream.secrets.php");
        }

        return self::$client;

    }//end getClient()


    /**
     * Creates the client from a secrets file.
     *
     * @param string $secretsFile The secrets file path.
     *
     * @return PancakeLogStream|null The client, or null when the file or its settings are missing.
     */
    public function createClient(string $secretsFile): ?PancakeLogStream
    {
        if (file_exists($secretsFile) === false) {
            return null;
        }

        global $logStreamServer, $logStreamApiKey, $logStreamApiToken;
        include_once $secretsFile;

        if (empty($logStreamServer) === true || empty($logStreamApiToken) === true) {
            return null;
        }

        $appKey = self::DEFAULT_APP_KEY;
        if (empty($logStreamApiKey) === false) {
            $appKey = $logStreamApiKey;
        }

        $configuration = new Configuration();

        // The app id is the deployed version, like the webhooks project does.
        return new PancakeLogStream(
            baseUrl: $logStreamServer,
            appKey: $appKey,
            appId: $configuration->getVersion(),
            authMode: PancakeLogStream::AUTH_API_KEY,
            apiKey: $appKey,
            apiToken: $logStreamApiToken,
            userAgent: $configuration->getUserAgent()
        );

    }//end createClient()


}//end class
