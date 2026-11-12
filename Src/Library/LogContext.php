<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Builds the ambient context LogStream adds to every entry: the request (web)
 * or the process (CLI), the runtime, the call site and expanded throwables.
 */
class LogContext
{
    public const KEY_REQUEST = "request";

    public const KEY_PROCESS = "process";

    public const KEY_RUNTIME = "runtime";

    public const KEY_SOURCE = "source";

    private const MAX_HEADER_LENGTH = 256;

    private const MAX_TRACE_LENGTH = 4000;

    private const TRACE_ID_PATTERN = "/^[A-Za-z0-9._:-]{8,128}$/";


    /**
     * Merges the caller context with the ambient data. Caller keys win over
     * ambient keys of the same name.
     *
     * @param array|null $context The context given by the caller.
     * @param array      $server  The server variables ($_SERVER).
     * @param mixed      $userId  The logged in user, or null.
     * @param array|null $source  The call site, from getCallSite().
     * @param string     $sapi    The server API (PHP_SAPI).
     *
     * @return array The context to send.
     */
    public function build(?array $context, array $server, mixed $userId, ?array $source, string $sapi): array
    {
        $ambient = [];
        if ($sapi === "cli") {
            $ambient[self::KEY_PROCESS] = $this->getProcessContext($server);
        }

        if ($sapi !== "cli") {
            $ambient[self::KEY_REQUEST] = $this->getRequestContext($server, $userId);
        }

        $ambient[self::KEY_RUNTIME] = $this->getRuntimeContext($server, $sapi);
        if ($source !== null) {
            $ambient[self::KEY_SOURCE] = $source;
        }

        $merged = ($this->normalizeThrowables(($context ?? [])) + $ambient);

        return $merged;

    }//end build()


    /**
     * The request data of a web request, without the empty fields.
     *
     * @param array $server The server variables.
     * @param mixed $userId The logged in user, or null.
     *
     * @return array The method, path, script, host, client IP, user agent and user.
     */
    public function getRequestContext(array $server, mixed $userId): array
    {
        $path = parse_url((string) ($server["REQUEST_URI"] ?? ""), PHP_URL_PATH);

        $request               = [];
        $request["method"]     = ($server["REQUEST_METHOD"] ?? null);
        $request["path"]       = $path;
        $request["script"]     = ($server["SCRIPT_NAME"] ?? null);
        $request["host"]       = ($server["HTTP_HOST"] ?? null);
        $request["client_ip"]  = ($server["REMOTE_ADDR"] ?? null);
        $request["user_agent"] = $this->truncate((string) ($server["HTTP_USER_AGENT"] ?? ""), self::MAX_HEADER_LENGTH);
        $request["user_id"]    = $userId;

        return array_filter($request, fn ($value) => in_array($value, [null, "", false], true) === false);

    }//end getRequestContext()


    /**
     * The process data of a CLI run.
     *
     * @param array $server The server variables.
     *
     * @return array The script name and the number of arguments.
     */
    public function getProcessContext(array $server): array
    {
        $argv = ($server["argv"] ?? []);

        $process              = [];
        $process["script"]    = basename((string) ($argv[0] ?? ""));
        $process["arguments"] = max(0, (count($argv) - 1));

        return $process;

    }//end getProcessContext()


    /**
     * The runtime data: host, process, PHP and resource usage.
     *
     * @param array  $server The server variables.
     * @param string $sapi   The server API.
     *
     * @return array The hostname, PID, SAPI, PHP version, peak memory and elapsed time.
     */
    public function getRuntimeContext(array $server, string $sapi): array
    {
        $runtime                   = [];
        $runtime["hostname"]       = gethostname();
        $runtime["pid"]            = getmypid();
        $runtime["sapi"]           = $sapi;
        $runtime["php_version"]    = PHP_VERSION;
        $runtime["memory_peak_mb"] = round((memory_get_peak_usage(true) / 1048576), 2);
        if (isset($server["REQUEST_TIME_FLOAT"]) === true) {
            $elapsed = (microtime(true) - (float) $server["REQUEST_TIME_FLOAT"]);
            $runtime["elapsed_ms"] = (int) round(($elapsed * 1000));
        }

        return $runtime;

    }//end getRuntimeContext()


    /**
     * The first frame outside the logging classes: the file and line that
     * logged, and the function or method that line belongs to.
     *
     * @param array $frames    The debug_backtrace() frames, innermost first.
     * @param array $skipFiles The files of the logging classes.
     *
     * @return array|null The file, line and function, or null when every frame is skipped.
     */
    public function getCallSite(array $frames, array $skipFiles): ?array
    {
        $callSite = null;
        foreach ($frames as $index => $frame) {
            $file = ($frame["file"] ?? null);
            if ($file === null || in_array($file, $skipFiles, true) === true) {
                continue;
            }

            $callSite             = [];
            $callSite["file"]     = $this->getRelativePath($file);
            $callSite["line"]     = ($frame["line"] ?? null);
            $callSite["function"] = $this->getFunctionName(($frames[($index + 1)] ?? []));
            break;
        }

        return $callSite;

    }//end getCallSite()


    /**
     * The name of the function a frame runs.
     *
     * @param array $frame The debug_backtrace() frame.
     *
     * @return string Class::method, the function name, or {main} for top-level code.
     */
    public function getFunctionName(array $frame): string
    {
        if (isset($frame["function"]) === false) {
            return "{main}";
        }

        if (isset($frame["class"]) === false) {
            return $frame["function"];
        }

        return sprintf("%s::%s", $this->getShortClassName($frame["class"]), $frame["function"]);

    }//end getFunctionName()


    /**
     * Expands every top-level throwable of the context into an array.
     *
     * @param array $context The context given by the caller.
     *
     * @return array The context, with throwables described.
     */
    public function normalizeThrowables(array $context): array
    {
        foreach ($context as $key => $value) {
            if ($value instanceof \Throwable) {
                $context[$key] = $this->describeThrowable($value);
            }
        }

        return $context;

    }//end normalizeThrowables()


    /**
     * Describes a throwable and the one before it.
     *
     * @param \Throwable $throwable The throwable to describe.
     *
     * @return array The type, message, code, file, line, truncated trace and previous throwable.
     */
    public function describeThrowable(\Throwable $throwable): array
    {
        $description            = [];
        $description["type"]    = get_class($throwable);
        $description["message"] = $throwable->getMessage();
        $description["code"]    = $throwable->getCode();
        $description["file"]    = $this->getRelativePath($throwable->getFile());
        $description["line"]    = $throwable->getLine();
        $description["trace"]   = $this->truncate($this->formatTrace($throwable), self::MAX_TRACE_LENGTH);

        $previous = $throwable->getPrevious();
        if ($previous !== null) {
            $description["previous"]            = [];
            $description["previous"]["type"]    = get_class($previous);
            $description["previous"]["message"] = $previous->getMessage();
            $description["previous"]["file"]    = $this->getRelativePath($previous->getFile());
            $description["previous"]["line"]    = $previous->getLine();
        }

        return $description;

    }//end describeThrowable()


    /**
     * Formats the stack trace of a throwable with the frame locations and
     * function names only, so call arguments (which may hold secrets) are never sent.
     *
     * @param \Throwable $throwable The throwable.
     *
     * @return string One line per frame, like getTraceAsString() without the arguments.
     */
    public function formatTrace(\Throwable $throwable): string
    {
        $lines = [];
        foreach ($throwable->getTrace() as $index => $frame) {
            $location = "[internal function]";
            if (isset($frame["file"]) === true) {
                $location = sprintf("%s(%d)", $this->getRelativePath($frame["file"]), ($frame["line"] ?? 0));
            }

            $lines[] = sprintf("#%d %s: %s()", $index, $location, $this->getFunctionName($frame));
        }

        $lines[] = sprintf("#%d {main}", count($lines));

        return implode("\n", $lines);

    }//end formatTrace()


    /**
     * The incoming X-Request-Id (or X-Trace-Id) header when it is a safe
     * identifier, otherwise a new UUID v4.
     *
     * @param array $server The server variables.
     *
     * @return string The trace id.
     */
    public function resolveTraceId(array $server): string
    {
        foreach (["HTTP_X_REQUEST_ID", "HTTP_X_TRACE_ID"] as $header) {
            $value = ($server[$header] ?? null);
            if (is_string($value) === true && preg_match(self::TRACE_ID_PATTERN, $value) === 1) {
                return $value;
            }
        }

        return $this->createUuid();

    }//end resolveTraceId()


    /**
     * A random UUID v4.
     *
     * @return string The UUID.
     */
    public function createUuid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr(((ord($bytes[6]) & 0x0f) | 0x40));
        $bytes[8] = chr(((ord($bytes[8]) & 0x3f) | 0x80));

        return vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($bytes), 4));

    }//end createUuid()


    /**
     * Shortens a value to a maximum length, marking the cut.
     *
     * @param string  $value  The value.
     * @param integer $length The maximum length.
     *
     * @return string The value, or its first characters followed by an ellipsis.
     */
    public function truncate(string $value, int $length): string
    {
        if (mb_strlen($value) <= $length) {
            return $value;
        }

        return sprintf("%s…", mb_substr($value, 0, $length));

    }//end truncate()


    /**
     * A file path relative to the Src directory, or unchanged when outside it.
     *
     * @param string $file The absolute path.
     *
     * @return string The relative path.
     */
    public function getRelativePath(string $file): string
    {
        $root = sprintf("%s%s", dirname(__DIR__), DIRECTORY_SEPARATOR);
        if (str_starts_with($file, $root) === false) {
            return $file;
        }

        return substr($file, strlen($root));

    }//end getRelativePath()


    /**
     * A class name without its namespace.
     *
     * @param string $class The fully qualified class name.
     *
     * @return string The short class name.
     */
    public function getShortClassName(string $class): string
    {
        $position = strrpos($class, "\\");
        if ($position === false) {
            return $class;
        }

        return substr($class, ($position + 1));

    }//end getShortClassName()


}//end class
