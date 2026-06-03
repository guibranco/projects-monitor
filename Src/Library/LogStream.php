<?php

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\LogStream as PancakeLogStream;

class LogStream
{
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

        global $logStreamServer, $logStreamToken, $logStreamAppId;
        require_once $secretsFile;

        if (empty($logStreamServer) || empty($logStreamToken)) {
            return;
        }

        self::$client = new PancakeLogStream(
            baseUrl: $logStreamServer,
            appKey: 'projects-monitor',
            appId: $logStreamAppId ?? 'production',
            authMode: PancakeLogStream::AUTH_BEARER,
            apiSecret: $logStreamToken,
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
