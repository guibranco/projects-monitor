<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\LogStream as PancakeLogStream;

/**
 * Static facade over the Pancake LogStream client.
 *
 * Messages are message templates: `{name}` placeholders refer to keys of the
 * context (dotted names reach nested arrays) and are rendered by logstream-server,
 * which keeps the original text as `message_template`. Every entry from the same
 * line can then be found with `?message_template=` whatever the values were:
 *
 * ```php
 * LogStream::warning("SSH connection to {host} failed: {reason}", [
 *     "host" => $name,
 *     "reason" => $e->getMessage(),
 *     "exception" => $e,
 * ], "ssh");
 * ```
 *
 * Each entry is enriched with ambient context, without the caller passing it:
 *
 * - `request`: method, path, script, client IP, user agent and session user (web only)
 * - `process`: script and arguments count (CLI only)
 * - `runtime`: host, PID, SAPI, PHP version, peak memory and elapsed time
 * - `source`:  file, line and function of the call site
 *
 * Every entry of one request (or CLI run) shares a trace id: the incoming
 * `X-Request-Id`/`X-Trace-Id` header when valid, otherwise a generated UUID.
 * Throwables anywhere at the top level of the context are expanded into type,
 * message, code, file, line, previous and a truncated stack trace.
 */
class LogStream
{
    private const DEFAULT_APP_KEY = 'projects-monitor';

    /** Context keys the ambient data is stored under; caller keys of the same name win. */
    public const KEY_REQUEST = 'request';
    public const KEY_PROCESS = 'process';
    public const KEY_RUNTIME = 'runtime';
    public const KEY_SOURCE = 'source';

    public const LEVELS = ['debug', 'info', 'notice', 'warning', 'error', 'critical'];

    private const MAX_HEADER_LENGTH = 256;
    private const MAX_TRACE_LENGTH = 4000;
    private const TRACE_ID_PATTERN = '/^[A-Za-z0-9._:-]{8,128}$/';

    private static ?PancakeLogStream $client = null;
    private static bool $initialized = false;
    private static ?string $traceId = null;

    /**
     * Creates the client from secrets/logStream.secrets.php. Safe to call more
     * than once; logging methods call it themselves, so entries written before
     * Configuration::init() (e.g. at the top of an API endpoint) are not lost.
     */
    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        $secretsFile = __DIR__ . "/../secrets/logStream.secrets.php";
        if (!file_exists($secretsFile)) {
            return;
        }

        global $logStreamServer, $logStreamApiKey, $logStreamApiToken;
        require_once $secretsFile;

        if (empty($logStreamServer) || empty($logStreamApiToken)) {
            return;
        }

        $appKey = empty($logStreamApiKey) ? self::DEFAULT_APP_KEY : $logStreamApiKey;
        $configuration = new Configuration();

        self::$client = new PancakeLogStream(
            baseUrl: $logStreamServer,
            appKey: $appKey,
            // The app id is the deployed version, like the webhooks project does.
            appId: $configuration->getVersion(),
            authMode: PancakeLogStream::AUTH_API_KEY,
            apiKey: $appKey,
            apiToken: $logStreamApiToken,
            userAgent: defined('USER_AGENT_VENDOR') ? constant('USER_AGENT_VENDOR') : $configuration->getUserAgent()
        );
    }

    public static function debug(string $message, ?array $context = null, string $category = ''): void
    {
        self::write('debug', $message, $context, $category);
    }

    public static function info(string $message, ?array $context = null, string $category = ''): void
    {
        self::write('info', $message, $context, $category);
    }

    public static function notice(string $message, ?array $context = null, string $category = ''): void
    {
        self::write('notice', $message, $context, $category);
    }

    public static function warning(string $message, ?array $context = null, string $category = ''): void
    {
        self::write('warning', $message, $context, $category);
    }

    public static function error(string $message, ?array $context = null, string $category = ''): void
    {
        self::write('error', $message, $context, $category);
    }

    public static function critical(string $message, ?array $context = null, string $category = ''): void
    {
        self::write('critical', $message, $context, $category);
    }

    /**
     * Logs at a level chosen at runtime (one of LEVELS); an unknown level is logged as error.
     */
    public static function log(string $level, string $message, ?array $context = null, string $category = ''): void
    {
        self::write(in_array($level, self::LEVELS, true) ? $level : 'error', $message, $context, $category);
    }

    /** The trace id shared by every entry of this request or CLI run. */
    public static function getTraceId(): string
    {
        return self::$traceId ??= self::resolveTraceId($_SERVER);
    }

    /**
     * Returns the incoming X-Request-Id (or X-Trace-Id) header when it is a
     * safe identifier, otherwise a new UUID v4.
     */
    public static function resolveTraceId(array $server): string
    {
        foreach (['HTTP_X_REQUEST_ID', 'HTTP_X_TRACE_ID'] as $header) {
            $value = $server[$header] ?? null;
            if (is_string($value) && preg_match(self::TRACE_ID_PATTERN, $value) === 1) {
                return $value;
            }
        }

        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Merges the caller context with the ambient request/process, runtime and
     * source data. Caller keys win over ambient keys of the same name.
     *
     * @param array      $server  $_SERVER (or a test double)
     * @param array|null $session $_SESSION when a session was started
     * @param array|null $source  Call site, as returned by callSite()
     * @param string     $sapi    PHP_SAPI (or a test double)
     */
    public static function buildContext(
        ?array $context,
        array $server,
        ?array $session,
        ?array $source = null,
        string $sapi = PHP_SAPI
    ): array {
        $context = self::normalizeThrowables($context ?? []);

        $ambient = [];
        if ($sapi === 'cli') {
            $ambient[self::KEY_PROCESS] = self::processContext($server);
        } else {
            $ambient[self::KEY_REQUEST] = self::requestContext($server, $session);
        }

        $ambient[self::KEY_RUNTIME] = self::runtimeContext($server, $sapi);

        if ($source !== null) {
            $ambient[self::KEY_SOURCE] = $source;
        }

        return $context + $ambient;
    }

    /**
     * Finds the first frame outside this class: the file and line that called
     * LogStream, and the function or method that line belongs to.
     *
     * @param array $frames debug_backtrace() output, innermost first
     */
    public static function callSite(array $frames): ?array
    {
        foreach ($frames as $index => $frame) {
            if (!isset($frame['file']) || $frame['file'] === __FILE__) {
                continue;
            }

            $caller = $frames[$index + 1] ?? null;
            $function = '{main}';
            if ($caller !== null && isset($caller['function'])) {
                $function = isset($caller['class'])
                    ? self::shortClassName($caller['class']) . '::' . $caller['function']
                    : $caller['function'];
            }

            return [
                'file' => self::relativePath($frame['file']),
                'line' => $frame['line'] ?? null,
                'function' => $function,
            ];
        }

        return null;
    }

    /** Expands every top-level Throwable value into a serializable array. */
    public static function normalizeThrowables(array $context): array
    {
        foreach ($context as $key => $value) {
            if ($value instanceof \Throwable) {
                $context[$key] = self::describeThrowable($value);
            }
        }

        return $context;
    }

    public static function describeThrowable(\Throwable $throwable): array
    {
        $description = [
            'type' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'file' => self::relativePath($throwable->getFile()),
            'line' => $throwable->getLine(),
            'trace' => self::truncate($throwable->getTraceAsString(), self::MAX_TRACE_LENGTH),
        ];

        $previous = $throwable->getPrevious();
        if ($previous !== null) {
            $description['previous'] = [
                'type' => get_class($previous),
                'message' => $previous->getMessage(),
                'file' => self::relativePath($previous->getFile()),
                'line' => $previous->getLine(),
            ];
        }

        return $description;
    }

    private static function write(string $level, string $message, ?array $context, string $category): void
    {
        self::initialize();

        if (self::$client === null) {
            return;
        }

        $source = self::callSite(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4));
        $session = isset($_SESSION) && is_array($_SESSION) ? $_SESSION : null;
        $fullContext = self::buildContext($context, $_SERVER, $session, $source);

        try {
            self::$client->{$level}($message, $fullContext, $category, self::getTraceId());
        } catch (\Throwable $e) {
            // Logging must never break the request that is being logged.
            error_log("LogStream: failed to send entry: " . $e->getMessage());
        }
    }

    private static function requestContext(array $server, ?array $session): array
    {
        $uri = (string) ($server['REQUEST_URI'] ?? '');
        $path = parse_url($uri, PHP_URL_PATH);

        $request = [
            'method' => $server['REQUEST_METHOD'] ?? null,
            'path' => is_string($path) ? $path : null,
            'script' => $server['SCRIPT_NAME'] ?? null,
            'host' => $server['HTTP_HOST'] ?? null,
            'client_ip' => $server['REMOTE_ADDR'] ?? null,
            'user_agent' => isset($server['HTTP_USER_AGENT'])
                ? self::truncate((string) $server['HTTP_USER_AGENT'], self::MAX_HEADER_LENGTH)
                : null,
            'referer' => isset($server['HTTP_REFERER'])
                ? self::truncate((string) strtok((string) $server['HTTP_REFERER'], '?'), self::MAX_HEADER_LENGTH)
                : null,
        ];

        if ($session !== null && !empty($session['user_id'])) {
            $request['user_id'] = $session['user_id'];
        }

        return array_filter($request, static fn ($value) => $value !== null && $value !== '');
    }

    private static function processContext(array $server): array
    {
        $argv = $server['argv'] ?? [];

        return [
            'script' => isset($argv[0]) ? basename((string) $argv[0]) : ($server['SCRIPT_NAME'] ?? null),
            'arguments' => max(0, count($argv) - 1),
        ];
    }

    private static function runtimeContext(array $server, string $sapi): array
    {
        $runtime = [
            'hostname' => gethostname() ?: null,
            'pid' => getmypid() ?: null,
            'sapi' => $sapi,
            'php_version' => PHP_VERSION,
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ];

        if (isset($server['REQUEST_TIME_FLOAT'])) {
            $runtime['elapsed_ms'] = (int) round((microtime(true) - (float) $server['REQUEST_TIME_FLOAT']) * 1000);
        }

        return $runtime;
    }

    private static function relativePath(string $file): string
    {
        $root = dirname(__DIR__) . DIRECTORY_SEPARATOR;

        return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
    }

    private static function shortClassName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    private static function truncate(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) . '…' : $value;
    }
}
