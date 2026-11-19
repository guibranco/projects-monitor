<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Last-resort reporting for whatever a script does not handle itself:
 *
 * - uncaught exceptions: reported as critical, then a JSON 500 (web) or exit 1 (CLI);
 * - PHP warnings (e.g. "Undefined array key"): reported once per location and request;
 * - fatal errors that end the script: reported from a shutdown function.
 *
 * Every report goes through ErrorReporter (LogStream when enabled + messages table).
 * PHP's own error log keeps receiving everything, so the cPanel error_log
 * pipeline (ProcessErrorLogs → errors table) is unchanged.
 */
class ErrorHandler
{
    /**
     * Error levels reported from the error handler.
     */
    public const REPORTED_LEVELS = (E_WARNING | E_USER_WARNING | E_USER_ERROR | E_RECOVERABLE_ERROR);

    /**
     * Error levels that end the script, reported at shutdown.
     */
    public const FATAL_LEVELS = (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR);

    /**
     * Whether the handlers were registered.
     *
     * @var boolean
     */
    private static $registered = false;

    /**
     * Locations already reported in this request, so a loop does not flood the sinks.
     *
     * @var array<string, true>
     */
    private static $reported = [];

    /**
     * The reporter.
     *
     * @var ErrorReporter
     */
    private $reporter;

    /**
     * The category of the reports, e.g. "api" or "worker".
     *
     * @var string
     */
    private $category;


    /**
     * The running script, e.g. /api/v1/releases.php.
     *
     * @var string
     */
    private $script;


    /**
     * @param string             $category The category of the reports.
     * @param string             $script   The running script (SCRIPT_NAME or argv[0]).
     * @param ErrorReporter|null $reporter The reporter.
     */
    public function __construct(string $category="api", string $script="unknown", ?ErrorReporter $reporter=null)
    {
        $this->category = $category;
        $this->script   = $script;
        $this->reporter = $reporter ?? new ErrorReporter();

    }//end __construct()


    /**
     * Registers the exception, error and shutdown handlers (once per process).
     *
     * @return void
     */
    public function register(): void
    {
        if (self::$registered === true) {
            return;
        }

        self::$registered = true;
        set_exception_handler([$this, "handleException"]);
        set_error_handler([$this, "handleError"], self::REPORTED_LEVELS);
        register_shutdown_function([$this, "handleShutdown"]);

    }//end register()


    /**
     * Reports an uncaught exception and ends the request with a JSON 500.
     *
     * @param \Throwable $exception The exception.
     *
     * @return void
     */
    public function handleException(\Throwable $exception): void
    {
        error_log(sprintf("PHP Fatal error:  Uncaught %s", (string) $exception));

        $this->reporter->critical(
            "Unhandled {exception_class} in {script}: {reason}",
            [
             "exception_class" => get_class($exception),
             "script"          => $this->getScript(),
             "reason"          => self::summarize($exception->getMessage()),
             "exception"       => $exception,
            ],
            $this->category
        );

        if (PHP_SAPI === "cli") {
            fwrite(STDERR, sprintf("%s: %s\n", get_class($exception), self::summarize($exception->getMessage())));
            exit(1);
        }

        if (headers_sent() === false) {
            http_response_code(500);
            header("Content-Type: application/json; charset=UTF-8");
        }

        echo json_encode(["error" => "Internal error", "trace_id" => (new LogStream())->getTraceId()]);

    }//end handleException()


    /**
     * Reports a PHP warning, then lets PHP handle it as usual (error log).
     *
     * @param int    $level   The error level.
     * @param string $message The error message.
     * @param string $file    The file.
     * @param int    $line    The line.
     *
     * @return bool Always false, so PHP's standard handler still runs.
     */
    public function handleError(int $level, string $message, string $file="", int $line=0): bool
    {
        if ((error_reporting() & $level) === 0) {
            // Silenced with @.
            return false;
        }

        $key = sprintf("%d|%s|%d|%s", $level, $file, $line, $message);
        if (isset(self::$reported[$key]) === true) {
            return false;
        }

        self::$reported[$key] = true;

        $this->reporter->warning(
            "PHP {level_name} in {file}:{line}: {reason}",
            [
             "level_name" => self::getLevelName($level),
             "file"       => $file,
             "line"       => $line,
             "reason"     => $message,
             "script"     => $this->getScript(),
            ],
            $this->category
        );

        return false;

    }//end handleError()


    /**
     * Reports a fatal error that ended the script.
     *
     * @return void
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null || ($error["type"] & self::FATAL_LEVELS) === 0) {
            return;
        }

        // Uncaught exceptions are reported by handleException.
        if (str_starts_with($error["message"], "Uncaught ") === true) {
            return;
        }

        $this->reporter->critical(
            "PHP {level_name} in {file}:{line}: {reason}",
            [
             "level_name" => self::getLevelName($error["type"]),
             "file"       => $error["file"],
             "line"       => $error["line"],
             "reason"     => self::summarize($error["message"]),
             "script"     => $this->getScript(),
            ],
            $this->category
        );

    }//end handleShutdown()


    /**
     * Turns an error text into one readable line: an HTML error page
     * (e.g. a proxy's 503 page) becomes its text, whitespace is collapsed.
     *
     * @param string $message The message.
     *
     * @return string The summary.
     */
    public static function summarize(string $message): string
    {
        if (preg_match('/<(html|body|!doctype)\b/i', $message) === 1) {
            $message = preg_replace('/<(style|script|head)\b[^>]*>.*?<\/\1>/is', " ", $message) ?? $message;
            $message = preg_replace('/<[^>]*>/', " ", $message) ?? $message;
            $message = html_entity_decode($message, (ENT_QUOTES | ENT_HTML5), "UTF-8");
        }

        return trim((preg_replace('/\s+/u', " ", $message) ?? $message));

    }//end summarize()


    /**
     * The name of a PHP error level.
     *
     * @param int $level The level.
     *
     * @return string The name, e.g. "Warning".
     */
    public static function getLevelName(int $level): string
    {
        return match ($level) {
            E_WARNING, E_USER_WARNING => "Warning",
            E_NOTICE, E_USER_NOTICE => "Notice",
            E_DEPRECATED, E_USER_DEPRECATED => "Deprecated",
            E_RECOVERABLE_ERROR => "Recoverable error",
            E_PARSE => "Parse error",
            E_CORE_ERROR, E_COMPILE_ERROR, E_ERROR, E_USER_ERROR => "Fatal error",
            default => sprintf("Error %d", $level),
        };

    }//end getLevelName()


    /**
     * The running script, for the reports.
     *
     * @return string The script name.
     */
    private function getScript(): string
    {
        return $this->script;

    }//end getScript()


}//end class
