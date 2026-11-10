<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\LogStream as PancakeLogStream;

class LogStream
{
    private const DEFAULT_APP_KEY = 'projects-monitor';

    private static ?PancakeLogStream $client = null;

    public static function initialize(): void
    {
        if (self::$client !== null) {
            return;
        }

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

        self::$client = new PancakeLogStream(
            baseUrl: $logStreamServer,
            appKey: $appKey,
            // The app id is the deployed version, like the webhooks project does.
            appId: (new Configuration())->getVersion(),
            authMode: PancakeLogStream::AUTH_API_KEY,
            apiKey: $appKey,
            apiToken: $logStreamApiToken,
            userAgent: defined('USER_AGENT_VENDOR') ? USER_AGENT_VENDOR : ''
        );
    }

    public static function debug(string $message, ?array $context = null, string $category = ''): void
    {
        self::$client?->debug($message, $context, $category);
    }

    public static function info(string $message, ?array $context = null, string $category = ''): void
    {
        self::$client?->info($message, $context, $category);
    }

    public static function notice(string $message, ?array $context = null, string $category = ''): void
    {
        self::$client?->notice($message, $context, $category);
    }

    public static function warning(string $message, ?array $context = null, string $category = ''): void
    {
        self::$client?->warning($message, $context, $category);
    }

    public static function error(string $message, ?array $context = null, string $category = ''): void
    {
        self::$client?->error($message, $context, $category);
    }

    public static function critical(string $message, ?array $context = null, string $category = ''): void
    {
        self::$client?->critical($message, $context, $category);
    }
}
