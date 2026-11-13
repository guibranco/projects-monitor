<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Loads and validates Src/Library/wireguard-clients.json — the mapping from a
 * WireGuard peer's allowed IPs to the client name shown on the dashboard.
 *
 * A missing file is not an error: the mapping is simply empty and peers are
 * shown by their allowed IPs (see isLoaded()). A file that exists but is not
 * valid JSON, fails the schema, has a blank field or repeats an allowedIps
 * entry throws WireGuardClientsConfigException with a message naming the
 * file and the offending entry.
 */
class WireGuardClientsConfig
{
    private const DEFAULT_CONFIG_PATH = __DIR__ . "/wireguard-clients.json";
    private const DEFAULT_SCHEMA_PATH = __DIR__ . "/wireguard-clients.schema.json";

    /** @var array<string, string> allowedIps => client name. */
    private array $clients = [];

    private bool $loaded = false;

    private string $configPath;

    /**
     * @throws WireGuardClientsConfigException When the file exists but is unreadable or invalid.
     */
    public function __construct(?string $configPath = null, ?string $schemaPath = null)
    {
        $this->configPath = $configPath ?? self::DEFAULT_CONFIG_PATH;
        $schemaPath ??= self::DEFAULT_SCHEMA_PATH;

        if (!file_exists($this->configPath)) {
            return;
        }

        $contents = file_get_contents($this->configPath);
        if ($contents === false) {
            throw new WireGuardClientsConfigException("Unable to read file: {$this->configPath}");
        }

        // Object mode keeps {} and [] distinct for the schema check (see GitHubBillingConfig).
        $data = json_decode($contents);
        if (!is_object($data)) {
            $reason = json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : "root must be a JSON object";
            throw new WireGuardClientsConfigException("Invalid JSON: {$this->configPath} - {$reason}");
        }

        $schema = $this->readSchema($schemaPath);
        $errors = GitHubBillingConfig::validateAgainstSchema($data, $schema);
        if ($errors !== []) {
            throw new WireGuardClientsConfigException("Invalid {$this->configPath}: " . implode("; ", $errors));
        }

        $this->clients = $this->resolveClients($data->clients);
        $this->loaded = true;
    }

    /** True when the configuration file was found and loaded. */
    public function isLoaded(): bool
    {
        return $this->loaded;
    }

    public function getConfigPath(): string
    {
        return $this->configPath;
    }

    /** @return array<string, string> allowedIps => client name. */
    public function getClients(): array
    {
        return $this->clients;
    }

    /** Client name for the given allowed IPs, or the allowed IPs themselves when not mapped. */
    public function getClientName(string $allowedIps): string
    {
        return $this->clients[$allowedIps] ?? $allowedIps;
    }

    /**
     * @param array<int, \stdClass> $rawClients
     * @return array<string, string>
     * @throws WireGuardClientsConfigException On a blank field or a duplicate allowedIps.
     */
    private function resolveClients(array $rawClients): array
    {
        $clients = [];

        foreach ($rawClients as $index => $rawClient) {
            $allowedIps = trim($rawClient->allowedIps);
            $name = trim($rawClient->name);

            if ($allowedIps === "" || $name === "") {
                throw new WireGuardClientsConfigException(
                    "Invalid {$this->configPath}: \$.clients[{$index}] must have a non-empty 'allowedIps' and 'name'"
                );
            }

            if (isset($clients[$allowedIps])) {
                throw new WireGuardClientsConfigException(
                    "Invalid {$this->configPath}: \$.clients[{$index}] repeats allowedIps '{$allowedIps}' " .
                    "(already mapped to '{$clients[$allowedIps]}')"
                );
            }

            $clients[$allowedIps] = $name;
        }

        return $clients;
    }

    /** @throws WireGuardClientsConfigException When the schema file is missing or invalid. */
    private function readSchema(string $schemaPath): array
    {
        $contents = file_exists($schemaPath) ? file_get_contents($schemaPath) : false;
        $schema = $contents === false ? null : json_decode($contents, true);

        if (!is_array($schema)) {
            throw new WireGuardClientsConfigException("Missing or invalid schema file: {$schemaPath}");
        }

        return $schema;
    }
}
