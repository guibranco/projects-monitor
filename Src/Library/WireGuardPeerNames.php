<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Resolves the name shown for a WireGuard peer from wireguard-clients.json.
 *
 * The file is read on the first lookup only. When it is missing (a warning)
 * or invalid (an error), the problem is logged once and every peer is shown
 * by its allowed IPs, so the WireGuard tile keeps working.
 */
class WireGuardPeerNames
{
    private const LOG_CATEGORY = 'wireguard';

    /**
     * Client names keyed by allowed IPs, or null until the file is read.
     *
     * @var array|null
     */
    private ?array $names = null;

    /**
     * Path of the wireguard-clients.json file.
     *
     * @var string
     */
    private string $configPath;

    /**
     * Where a missing or invalid file is reported.
     *
     * @var LogStream
     */
    private LogStream $logger;


    /**
     * Prepares the lookup; the file is read on the first call to getName().
     *
     * @param string    $configPath Path of the wireguard-clients.json file.
     * @param LogStream $logger     Where a missing or invalid file is reported.
     */
    public function __construct(string $configPath, LogStream $logger)
    {
        $this->configPath = $configPath;
        $this->logger = $logger;

    }//end __construct()


    /**
     * Name shown for a peer.
     *
     * @param string $allowedIps The peer's allowed IPs, as WireGuard reports them.
     *
     * @return string The configured client name, or the allowed IPs when they are not mapped.
     */
    public function getName(string $allowedIps): string
    {
        $this->names ??= $this->loadNames();

        if (isset($this->names[$allowedIps]) === true) {
            return $this->names[$allowedIps];
        }

        return $allowedIps;

    }//end getName()


    /**
     * Reads the configuration file, logging a missing or invalid file.
     *
     * @return array Client names keyed by allowed IPs; empty when the file is missing or invalid.
     */
    private function loadNames(): array
    {
        try {
            $config = new WireGuardClientsConfig($this->configPath);
        } catch (WireGuardClientsConfigException $exception) {
            $this->logger->error('Invalid WireGuard clients configuration: {reason}', ['reason' => $exception->getMessage(), 'exception' => $exception], self::LOG_CATEGORY);
            return [];
        }

        if ($config->isLoaded() === false) {
            $this->logger->warning('WireGuard clients configuration not found at {path}; showing peers by allowed IPs', ['path' => $this->configPath], self::LOG_CATEGORY);
        }

        return $config->getClients();

    }//end loadNames()


}//end class
