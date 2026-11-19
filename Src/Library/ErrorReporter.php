<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Reports a failure to every sink Projects Monitor has for its own errors:
 *
 * - LogStream, when it is configured (LogStream itself is a no-op otherwise);
 * - the `messages` table, as a message of this application (Logger).
 *
 * The message is a LogStream message template: `{name}` placeholders refer to
 * keys of the context. LogStream renders them server side; for the messages
 * table they are rendered here. A Throwable under the `exception` key is
 * expanded by both sinks.
 *
 * ```php
 * (new ErrorReporter())->error("Releases failed to load: {reason}", ["reason" => $e->getMessage(), "exception" => $e], "webhooks");
 * ```
 *
 * Reporting never throws: a sink that fails (database down, LogStream
 * unreachable) is written to the PHP error log and the other sink still runs.
 */
class ErrorReporter
{
    /**
     * Longest message stored in the messages table; the full values stay in the details.
     */
    private const MAX_MESSAGE_LENGTH = 1000;

    /**
     * The LogStream wrapper.
     *
     * @var LogStream
     */
    private $logStream;

    /**
     * Creates the Logger on first use (it opens a database connection).
     *
     * @var callable
     */
    private $loggerFactory;

    /**
     * Formats messages and details.
     *
     * @var ErrorText
     */
    private $errorText;

    /**
     * Whether a report is in progress, so a failing sink cannot recurse.
     *
     * @var boolean
     */
    private static $reporting = false;


    /**
     * Creates a reporter that sends to LogStream and to the messages table.
     */
    public function __construct()
    {
        $this->logStream     = new LogStream();
        $this->loggerFactory = function (): Logger {
            return new Logger();
        };
        $this->errorText     = new ErrorText();

    }//end __construct()


    /**
     * Replaces the LogStream wrapper.
     *
     * @param LogStream $logStream The LogStream wrapper.
     *
     * @return void
     */
    public function setLogStream(LogStream $logStream): void
    {
        $this->logStream = $logStream;

    }//end setLogStream()


    /**
     * Replaces how the Logger of the messages table is created.
     *
     * @param callable $loggerFactory Returns the Logger.
     *
     * @return void
     */
    public function setLoggerFactory(callable $loggerFactory): void
    {
        $this->loggerFactory = $loggerFactory;

    }//end setLoggerFactory()


    /**
     * Reports a warning.
     *
     * @param string $message  The message template.
     * @param array  $context  The values of the placeholders and any other data.
     * @param string $category The category.
     *
     * @return void
     */
    public function warning(string $message, array $context, string $category): void
    {
        $this->report("warning", $message, $context, $category);

    }//end warning()


    /**
     * Reports an error.
     *
     * @param string $message  The message template.
     * @param array  $context  The values of the placeholders and any other data.
     * @param string $category The category.
     *
     * @return void
     */
    public function error(string $message, array $context, string $category): void
    {
        $this->report("error", $message, $context, $category);

    }//end error()


    /**
     * Reports a critical failure.
     *
     * @param string $message  The message template.
     * @param array  $context  The values of the placeholders and any other data.
     * @param string $category The category.
     *
     * @return void
     */
    public function critical(string $message, array $context, string $category): void
    {
        $this->report("critical", $message, $context, $category);

    }//end critical()


    /**
     * Reports to LogStream and to the messages table.
     *
     * @param string $level    The LogStream level.
     * @param string $message  The message template.
     * @param array  $context  The values of the placeholders and any other data.
     * @param string $category The category.
     *
     * @return void
     */
    public function report(string $level, string $message, array $context, string $category): void
    {
        if (self::$reporting === true) {
            error_log(sprintf("ErrorReporter: nested report skipped: %s", $this->errorText->render($message, $context)));
            return;
        }

        self::$reporting = true;

        try {
            $this->sendToLogStream($level, $message, $context, $category);
            $this->sendToMessages($level, $message, $context, $category);
        } finally {
            self::$reporting = false;
        }

    }//end report()


    /**
     * The details stored with the message: the template, the level, the
     * category, the context (Throwables summarised) and the LogStream trace id.
     *
     * @param string $level    The level.
     * @param string $message  The message template.
     * @param array  $context  The context.
     * @param string $category The category.
     *
     * @return array The details.
     */
    public function buildDetails(string $level, string $message, array $context, string $category): array
    {
        $details                     = [];
        $details["level"]            = $level;
        $details["category"]         = $category;
        $details["message_template"] = $message;
        $details["context"]          = $this->errorText->normalize($context, 0);

        try {
            $details["trace_id"] = $this->logStream->getTraceId();
        } catch (\Throwable $exception) {
            $details["trace_id"] = null;
        }

        return $details;

    }//end buildDetails()


    /**
     * Sends the entry to LogStream; a failure goes to the PHP error log.
     *
     * @param string $level    The level.
     * @param string $message  The message template.
     * @param array  $context  The context.
     * @param string $category The category.
     *
     * @return void
     */
    private function sendToLogStream(string $level, string $message, array $context, string $category): void
    {
        try {
            $this->logStream->log($level, $message, $context, $category);
        } catch (\Throwable $exception) {
            error_log(sprintf("ErrorReporter: LogStream failed: %s", $exception->getMessage()));
        }

    }//end sendToLogStream()


    /**
     * Stores the entry in the messages table; a failure goes to the PHP error log.
     *
     * @param string $level    The level.
     * @param string $message  The message template.
     * @param array  $context  The context.
     * @param string $category The category.
     *
     * @return void
     */
    private function sendToMessages(string $level, string $message, array $context, string $category): void
    {
        $exception = null;
        if (isset($context["exception"]) === true && $context["exception"] instanceof \Throwable) {
            $exception = $context["exception"];
        }

        try {
            $rendered = $this->errorText->truncate($this->errorText->render($message, $context), self::MAX_MESSAGE_LENGTH);
            $details  = $this->buildDetails($level, $message, $context, $category);
            $logger   = call_user_func($this->loggerFactory);
            $logger->logError($rendered, $details, $exception, $this->findOrigin($context));
        } catch (\Throwable $failure) {
            error_log(sprintf("ErrorReporter: messages table failed: %s", $failure->getMessage()));
        }

    }//end sendToMessages()


    /**
     * Where a PHP error happened, from the `file` and `line` of the context
     * (set by ErrorHandler for warnings and fatal errors).
     *
     * @param array $context The context.
     *
     * @return array|null The file and line, or null when the context has none.
     */
    private function findOrigin(array $context): ?array
    {
        if (isset($context["file"], $context["line"]) === false || is_string($context["file"]) === false) {
            return null;
        }

        $origin         = [];
        $origin["file"] = $context["file"];
        $origin["line"] = (int) $context["line"];

        return $origin;

    }//end findOrigin()


}//end class
