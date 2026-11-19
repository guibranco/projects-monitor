<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Last-resort reporting for whatever a script does not handle itself:
 *
 * - uncaught exceptions: on the web, reported as critical then a JSON 500;
 *   on the CLI, left to PHP (standard log line, exit code 255) and reported
 *   from the shutdown function, so cron and health checks still see the failure;
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
     * Whether handleException already reported the uncaught exception.
     *
     * @var boolean
     */
    private $exceptionReported = false;


    /**
     * Creates the handler.
     *
     * @param string $category The category of the reports.
     * @param string $script   The running script (SCRIPT_NAME or the worker file).
     */
    public function __construct(string $category, string $script)
    {
        $this->category = $category;
        $this->script   = $script;
        $this->reporter = new ErrorReporter();

    }//end __construct()


    /**
     * Replaces the reporter.
     *
     * @param ErrorReporter $reporter The reporter.
     *
     * @return void
     */
    public function setReporter(ErrorReporter $reporter): void
    {
        $this->reporter = $reporter;

    }//end setReporter()


    /**
     * Registers the error and shutdown handlers, plus the exception handler
     * on the web (once per process).
     *
     * @return void
     */
    public function register(): void
    {
        if (self::$registered === true) {
            return;
        }

        self::$registered = true;
        if (PHP_SAPI !== "cli") {
            set_exception_handler([$this, "handleException"]);
        }

        set_error_handler([$this, "handleError"], self::REPORTED_LEVELS);
        register_shutdown_function([$this, "handleShutdown"]);

    }//end register()


    /**
     * Reports an uncaught exception and answers web requests with a JSON 500.
     *
     * @param \Throwable $exception The exception.
     *
     * @return void
     */
    public function handleException(\Throwable $exception): void
    {
        $reason  = (new ErrorText())->summarize($exception->getMessage());
        $context = [];
        $context["exception_class"] = get_class($exception);
        $context["script"]          = $this->script;
        $context["reason"]          = $reason;
        $context["exception"]       = $exception;
        $this->reporter->critical("Unhandled {exception_class} in {script}: {reason}", $context, $this->category);
        $this->exceptionReported = true;

        error_log(sprintf("PHP Fatal error:  Uncaught %s", (string) $exception));
        if (headers_sent() === false) {
            http_response_code(500);
            header("Content-Type: application/json; charset=UTF-8");
        }

        $response = [];
        $response["error"]    = "Internal error";
        $response["trace_id"] = (new LogStream())->getTraceId();
        echo json_encode($response);

    }//end handleException()


    /**
     * Reports a PHP warning, then lets PHP handle it as usual (error log).
     *
     * @param integer $level   The error level.
     * @param string  $message The error message.
     * @param string  $file    The file.
     * @param integer $line    The line.
     *
     * @return boolean Always false, so PHP's standard handler still runs.
     */
    public function handleError(int $level, string $message, string $file, int $line): bool
    {
        // Warnings silenced with @ are not reported.
        if ((error_reporting() & $level) === 0) {
            return false;
        }

        $key = sprintf("%d|%s|%d|%s", $level, $file, $line, $message);
        if (isset(self::$reported[$key]) === true) {
            return false;
        }

        self::$reported[$key] = true;

        $context = [];
        $context["level_name"] = $this->getLevelName($level);
        $context["file"]       = $file;
        $context["line"]       = $line;
        $context["reason"]     = $message;
        $context["script"]     = $this->script;
        $this->reporter->warning("PHP {level_name} in {file}:{line}: {reason}", $context, $this->category);

        return false;

    }//end handleError()


    /**
     * Reports a fatal error that ended the script.
     *
     * @return void
     */
    public function handleShutdown(): void
    {
        $this->reportFatal(error_get_last());

    }//end handleShutdown()


    /**
     * Reports an error_get_last() entry when it is a fatal error.
     *
     * @param array|null $error The entry: type, message, file and line.
     *
     * @return void
     */
    public function reportFatal(?array $error): void
    {
        if ($error === null || ($error["type"] & self::FATAL_LEVELS) === 0) {
            return;
        }

        // On the web, handleException already reported the uncaught exception.
        if ($this->exceptionReported === true && str_starts_with($error["message"], "Uncaught ") === true) {
            return;
        }

        $context = [];
        $context["level_name"] = $this->getLevelName($error["type"]);
        $context["file"]       = $error["file"];
        $context["line"]       = $error["line"];
        $message = explode("\nStack trace:", $error["message"], 2);
        $context["reason"]     = (new ErrorText())->summarize($message[0]);
        $context["script"]     = $this->script;
        $this->reporter->critical("PHP {level_name} in {file}:{line}: {reason}", $context, $this->category);

    }//end reportFatal()


    /**
     * The name of a PHP error level.
     *
     * @param integer $level The level.
     *
     * @return string The name, e.g. "Warning".
     */
    public function getLevelName(int $level): string
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


}//end class
