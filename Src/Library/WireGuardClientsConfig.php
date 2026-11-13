<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Loads and validates Src/Library/wireguard-clients.json, the mapping from a
 * WireGuard peer's allowed IPs to the client name shown on the dashboard.
 *
 * A missing file is not an error: the mapping is empty and peers are shown by
 * their allowed IPs (see isLoaded()). A file that exists but is not valid JSON,
 * does not match wireguard-clients.schema.json, has a blank value or repeats
 * an allowedIps entry throws WireGuardClientsConfigException with a message
 * naming the file and the offending JSON path.
 */
class WireGuardClientsConfig
{
    private const MINIMUM_VERSION = 1;

    /**
     * Client names keyed by the peer's allowed IPs.
     *
     * @var array
     */
    private array $clients = [];

    /**
     * Whether the configuration file was found and loaded.
     *
     * @var boolean
     */
    private bool $loaded = false;

    /**
     * Path of the configuration file.
     *
     * @var string
     */
    private string $configPath;


    /**
     * Loads the configuration file, when it exists.
     *
     * @param string $configPath Path of the wireguard-clients.json file.
     *
     * @throws WireGuardClientsConfigException When the file exists but is unreadable or invalid.
     */
    public function __construct(string $configPath)
    {
        $this->configPath = $configPath;
        $data = $this->readConfig();

        if ($data === null) {
            return;
        }

        $this->validateRoot($data);
        $this->clients = $this->resolveClients($data->clients);
        $this->loaded = true;

    }//end __construct()


    /**
     * Whether the configuration file was found and loaded.
     *
     * @return boolean
     */
    public function isLoaded(): bool
    {
        return $this->loaded;

    }//end isLoaded()


    /**
     * Path of the configuration file.
     *
     * @return string
     */
    public function getConfigPath(): string
    {
        return $this->configPath;

    }//end getConfigPath()


    /**
     * Client names keyed by the peer's allowed IPs.
     *
     * @return array
     */
    public function getClients(): array
    {
        return $this->clients;

    }//end getClients()


    /**
     * Reads and decodes the configuration file.
     *
     * @return \stdClass|null The decoded file, or null when it does not exist.
     *
     * @throws WireGuardClientsConfigException When the file is unreadable or not a JSON object.
     */
    private function readConfig(): ?\stdClass
    {
        if (file_exists($this->configPath) === false) {
            return null;
        }

        $contents = file_get_contents($this->configPath);
        if ($contents === false) {
            throw new WireGuardClientsConfigException(sprintf('Unable to read file: %s', $this->configPath));
        }

        $data = json_decode($contents);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new WireGuardClientsConfigException(sprintf('Invalid JSON: %s - %s', $this->configPath, json_last_error_msg()));
        }

        if (($data instanceof \stdClass) === false) {
            throw $this->createError('$ must be a JSON object');
        }

        return $data;

    }//end readConfig()


    /**
     * Checks the top-level properties of the configuration.
     *
     * @param \stdClass $data The decoded configuration file.
     *
     * @return void
     *
     * @throws WireGuardClientsConfigException When a property is missing, unexpected or of the wrong type.
     */
    private function validateRoot(\stdClass $data): void
    {
        $this->validateProperties($data, '$', ['$schema', 'version', 'clients']);

        $version = ($data->version ?? null);
        if (is_int($version) === false || $version < self::MINIMUM_VERSION) {
            throw $this->createError(sprintf('$.version must be an integer >= %d', self::MINIMUM_VERSION));
        }

        if (is_array(($data->clients ?? null)) === false) {
            throw $this->createError('$.clients must be an array');
        }

    }//end validateRoot()


    /**
     * Checks that an object has no property outside the allowed list.
     *
     * @param \stdClass $data    The object to check.
     * @param string    $path    JSON path of the object, used in the error message.
     * @param array     $allowed Names of the allowed properties.
     *
     * @return void
     *
     * @throws WireGuardClientsConfigException On an unexpected property.
     */
    private function validateProperties(\stdClass $data, string $path, array $allowed): void
    {
        foreach (array_keys(get_object_vars($data)) as $property) {
            if (in_array($property, $allowed, true) === false) {
                throw $this->createError(sprintf("%s: unexpected property '%s'", $path, $property));
            }
        }

    }//end validateProperties()


    /**
     * Builds the allowed IPs to client name mapping from the clients list.
     *
     * @param array $rawClients The decoded "clients" array.
     *
     * @return array Client names keyed by allowed IPs.
     *
     * @throws WireGuardClientsConfigException On an invalid client or a duplicate allowedIps.
     */
    private function resolveClients(array $rawClients): array
    {
        $clients = [];

        foreach ($rawClients as $index => $rawClient) {
            $path = sprintf('$.clients[%d]', $index);
            if (($rawClient instanceof \stdClass) === false) {
                throw $this->createError(sprintf('%s must be an object', $path));
            }

            $this->validateProperties($rawClient, $path, ['allowedIps', 'name']);
            $allowedIps = $this->getRequiredString($rawClient, $path, 'allowedIps');
            $name = $this->getRequiredString($rawClient, $path, 'name');

            if (isset($clients[$allowedIps]) === true) {
                throw $this->createError(sprintf("%s repeats allowedIps '%s' (already mapped to '%s')", $path, $allowedIps, $clients[$allowedIps]));
            }

            $clients[$allowedIps] = $name;
        }

        return $clients;

    }//end resolveClients()


    /**
     * Reads a required, non-empty string property of a client.
     *
     * @param \stdClass $client   The client entry.
     * @param string    $path     JSON path of the client, used in the error message.
     * @param string    $property Name of the property to read.
     *
     * @return string The trimmed value.
     *
     * @throws WireGuardClientsConfigException When the property is missing, not a string or blank.
     */
    private function getRequiredString(\stdClass $client, string $path, string $property): string
    {
        $value = ($client->{$property} ?? null);
        if (is_string($value) === false || trim($value) === '') {
            throw $this->createError(sprintf('%s.%s must be a non-empty string', $path, $property));
        }

        return trim($value);

    }//end getRequiredString()


    /**
     * Builds the exception for an invalid configuration.
     *
     * @param string $detail What is wrong, starting with its JSON path.
     *
     * @return WireGuardClientsConfigException
     */
    private function createError(string $detail): WireGuardClientsConfigException
    {
        return new WireGuardClientsConfigException(sprintf('Invalid %s: %s', $this->configPath, $detail));

    }//end createError()


}//end class
