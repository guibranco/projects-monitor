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
     * Longest rendered placeholder value.
     */
    private const MAX_VALUE_LENGTH = 300;

    /**
     * The LogStream client wrapper.
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
     * Whether a report is in progress, so a failing sink cannot recurse.
     *
     * @var boolean
     */
    private static $reporting = false;


    /**
     * @param LogStream|null $logStream     The LogStream wrapper.
     * @param callable|null  $loggerFactory Returns the Logger for the messages table.
     */
    public function __construct(?LogStream $logStream=null, ?callable $loggerFactory=null)
    {
        $this->logStream     = $logStream ?? new LogStream();
        $this->loggerFactory = $loggerFactory ?? static fn (): Logger => new Logger();

    }//end __construct()


    /**
     * Reports a warning.
     *
     * @param string $message  The message template.
     * @param array  $context  The values of the placeholders and any other data.
     * @param string $category The category.
     *
     * @return void
     */
    public function warning(string $message, array $context=[], string $category=""): void
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
    public function error(string $message, array $context=[], string $category=""): void
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
    public function critical(string $message, array $context=[], string $category=""): void
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
    public function report(string $level, string $message, array $context=[], string $category=""): void
    {
        if (self::$reporting === true) {
            error_log(sprintf("ErrorReporter: nested report skipped: %s", $this->render($message, $context)));
            return;
        }

        self::$reporting = true;

        try {
            try {
                $this->logStream->log($level, $message, $context, $category);
            } catch (\Throwable $exception) {
                error_log(sprintf("ErrorReporter: LogStream failed: %s", $exception->getMessage()));
            }

            try {
                $rendered = $this->truncate($this->render($message, $context), self::MAX_MESSAGE_LENGTH);
                $details  = $this->buildDetails($level, $message, $context, $category);
                $logger   = ($this->loggerFactory)();
                $logger->logError($rendered, $details, $this->findException($context));
            } catch (\Throwable $exception) {
                error_log(sprintf("ErrorReporter: messages table failed: %s", $exception->getMessage()));
            }
        } finally {
            self::$reporting = false;
        }

    }//end report()


    /**
     * Renders the `{name}` placeholders of a message template (dotted names reach nested arrays).
     *
     * @param string $message The message template.
     * @param array  $context The values.
     *
     * @return string The rendered message; unknown placeholders are kept as they are.
     */
    public function render(string $message, array $context): string
    {
        return preg_replace_callback(
            '/\{([A-Za-z0-9_.]+)\}/',
            function (array $matches) use ($context): string {
                $value = $context;
                foreach (explode(".", $matches[1]) as $key) {
                    if (is_array($value) === false || array_key_exists($key, $value) === false) {
                        return $matches[0];
                    }

                    $value = $value[$key];
                }

                return $this->truncate($this->stringify($value), self::MAX_VALUE_LENGTH);
            },
            $message
        );

    }//end render()


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
        $details = [
                    "level"            => $level,
                    "category"         => $category,
                    "message_template" => $message,
                    "context"          => $this->normalize($context),
                   ];

        try {
            $details["trace_id"] = $this->logStream->getTraceId();
        } catch (\Throwable $exception) {
            // The trace id is a convenience; never let it stop the report.
        }

        return $details;

    }//end buildDetails()


    /**
     * The Throwable under the `exception` key, if any.
     *
     * @param array $context The context.
     *
     * @return \Throwable|null The Throwable.
     */
    private function findException(array $context): ?\Throwable
    {
        $exception = $context["exception"] ?? null;

        return $exception instanceof \Throwable ? $exception : null;

    }//end findException()


    /**
     * Makes the context JSON friendly: Throwables become arrays, objects their class.
     *
     * @param mixed $value The value.
     * @param int   $depth The current depth.
     *
     * @return mixed The normalised value.
     */
    private function normalize(mixed $value, int $depth=0): mixed
    {
        if ($value instanceof \Throwable) {
            return $this->describeThrowable($value);
        }

        if (is_array($value) === true) {
            if ($depth >= 5) {
                return "[array]";
            }

            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalize($item, ($depth + 1));
            }

            return $normalized;
        }

        if (is_object($value) === true) {
            return $value instanceof \JsonSerializable ? $value : sprintf("[object %s]", get_class($value));
        }

        if (is_resource($value) === true) {
            return "[resource]";
        }

        return $value;

    }//end normalize()


    /**
     * Summarises a Throwable and its previous ones.
     *
     * @param \Throwable $exception The Throwable.
     *
     * @return array The summary.
     */
    private function describeThrowable(\Throwable $exception): array
    {
        $description = [
                        "class"   => get_class($exception),
                        "message" => $exception->getMessage(),
                        "code"    => $exception->getCode(),
                        "file"    => $exception->getFile(),
                        "line"    => $exception->getLine(),
                        "trace"   => $exception->getTraceAsString(),
                       ];

        if ($exception->getPrevious() !== null) {
            $description["previous"] = $this->describeThrowable($exception->getPrevious());
        }

        return $description;

    }//end describeThrowable()


    /**
     * A placeholder value as text.
     *
     * @param mixed $value The value.
     *
     * @return string The text.
     */
    private function stringify(mixed $value): string
    {
        if ($value instanceof \Throwable) {
            return sprintf("%s: %s", get_class($value), $value->getMessage());
        }

        if (is_bool($value) === true) {
            return $value === true ? "true" : "false";
        }

        if ($value === null) {
            return "null";
        }

        if (is_scalar($value) === true || $value instanceof \Stringable) {
            return (string) $value;
        }

        $encoded = json_encode($this->normalize($value), JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? "[unencodable]" : $encoded;

    }//end stringify()


    /**
     * Cuts a text to a length.
     *
     * @param string $value  The text.
     * @param int    $length The length.
     *
     * @return string The text, with an ellipsis when cut.
     */
    private function truncate(string $value, int $length): string
    {
        return (new LogContext())->truncate($value, $length);

    }//end truncate()


}//end class
