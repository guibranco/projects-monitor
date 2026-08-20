<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Loads, schema-validates and resolves Src/Library/github-billing.json — the
 * single source of truth for which GitHub accounts to poll for Actions
 * included-usage (minutes/storage) and how their quota is derived.
 *
 * Validation happens eagerly in the constructor: a malformed file, an unknown
 * planType, or an accountType the plan doesn't allow (e.g. an org marked
 * "pro") throws GitHubBillingConfigException rather than silently producing a
 * wrong denominator downstream.
 */
class GitHubBillingConfig
{
    private const DEFAULT_CONFIG_PATH = __DIR__ . "/github-billing.json";
    private const DEFAULT_SCHEMA_PATH = __DIR__ . "/github-billing.schema.json";

    private array $plans;

    /** @var array<int, array{account: string, accountType: string, planType: string, cycleResetDay: ?int, tokenSecret: ?string, minutes: int, storageMb: int}> */
    private array $accounts;

    public function __construct(?string $configPath = null, ?string $schemaPath = null)
    {
        $configPath ??= self::DEFAULT_CONFIG_PATH;
        $schemaPath ??= self::DEFAULT_SCHEMA_PATH;

        $data = $this->readJsonFile($configPath);
        $schema = $this->readJsonFile($schemaPath);

        $schemaErrors = self::validateAgainstSchema($data, $schema);
        if ($schemaErrors !== []) {
            throw new GitHubBillingConfigException(
                "Invalid {$configPath}: " . implode("; ", $schemaErrors)
            );
        }

        $this->plans = $data["plans"];
        $this->accounts = $this->resolveAccounts($data["accounts"]);
    }

    /**
     * @return array<int, array{account: string, accountType: string, planType: string, cycleResetDay: ?int, tokenSecret: ?string, minutes: int, storageMb: int}>
     */
    public function getAccounts(): array
    {
        return $this->accounts;
    }

    public function getAccount(string $accountName): ?array
    {
        foreach ($this->accounts as $account) {
            if ($account["account"] === $accountName) {
                return $account;
            }
        }

        return null;
    }

    /** @return array<string, array{accountTypes: array<int, string>, minutes: int, storageMb: int}> */
    public function getPlans(): array
    {
        return $this->plans;
    }

    private function resolveAccounts(array $rawAccounts): array
    {
        $resolved = [];

        foreach ($rawAccounts as $rawAccount) {
            $planType = $rawAccount["planType"];

            if (!isset($this->plans[$planType])) {
                throw new GitHubBillingConfigException(
                    "Account '{$rawAccount["account"]}' has unknown planType '{$planType}'. " .
                    "Valid plans: " . implode(", ", array_keys($this->plans))
                );
            }

            $plan = $this->plans[$planType];
            $accountType = $rawAccount["accountType"];

            if (!in_array($accountType, $plan["accountTypes"], true)) {
                throw new GitHubBillingConfigException(
                    "Account '{$rawAccount["account"]}' has accountType '{$accountType}', " .
                    "which plan '{$planType}' does not allow (allowed: " .
                    implode(", ", $plan["accountTypes"]) . ")"
                );
            }

            $overrides = $rawAccount["overrides"] ?? [];

            $resolved[] = [
                "account" => $rawAccount["account"],
                "accountType" => $accountType,
                "planType" => $planType,
                "cycleResetDay" => $rawAccount["cycleResetDay"] ?? null,
                "tokenSecret" => $rawAccount["tokenSecret"] ?? null,
                "minutes" => $overrides["minutes"] ?? $plan["minutes"],
                "storageMb" => $overrides["storageMb"] ?? $plan["storageMb"],
            ];
        }

        return $resolved;
    }

    private function readJsonFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new GitHubBillingConfigException("File not found: {$path}");
        }

        $decoded = json_decode(file_get_contents($path), true);

        if (!is_array($decoded)) {
            throw new GitHubBillingConfigException("Invalid JSON: {$path} - " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Minimal structural JSON Schema validator covering the subset this
     * project's schemas use: type, required, properties, additionalProperties,
     * items, enum, minimum, maximum. Not a general-purpose implementation —
     * just enough that a typo in github-billing.json fails loudly.
     *
     * @return array<int, string> Human-readable error messages, empty when valid.
     */
    public static function validateAgainstSchema($data, array $schema, string $path = "$"): array
    {
        $typeErrors = self::validateType($data, $schema, $path);
        if ($typeErrors !== []) {
            return $typeErrors;
        }

        $errors = array_merge(
            self::validateEnum($data, $schema, $path),
            self::validateRange($data, $schema, $path)
        );

        if (is_array($data) && self::isAssoc($data)) {
            return array_merge($errors, self::validateObject($data, $schema, $path));
        }

        if (is_array($data) && isset($schema["items"])) {
            return array_merge($errors, self::validateItems($data, $schema, $path));
        }

        return $errors;
    }

    private static function validateType($data, array $schema, string $path): array
    {
        if (!isset($schema["type"])) {
            return [];
        }

        $types = is_array($schema["type"]) ? $schema["type"] : [$schema["type"]];
        if (self::matchesAnyType($data, $types)) {
            return [];
        }

        return ["{$path}: expected type " . implode("|", $types) . ", got " . self::describeType($data)];
    }

    private static function validateEnum($data, array $schema, string $path): array
    {
        if (!isset($schema["enum"]) || in_array($data, $schema["enum"], true)) {
            return [];
        }

        return ["{$path}: value must be one of [" . implode(", ", $schema["enum"]) . "]"];
    }

    private static function validateRange($data, array $schema, string $path): array
    {
        if (!is_int($data) && !is_float($data)) {
            return [];
        }

        $errors = [];
        if (isset($schema["minimum"]) && $data < $schema["minimum"]) {
            $errors[] = "{$path}: must be >= {$schema["minimum"]}";
        }
        if (isset($schema["maximum"]) && $data > $schema["maximum"]) {
            $errors[] = "{$path}: must be <= {$schema["maximum"]}";
        }

        return $errors;
    }

    private static function validateObject(array $data, array $schema, string $path): array
    {
        $errors = self::validateRequiredProperties($data, $schema, $path);
        $properties = $schema["properties"] ?? [];

        foreach ($data as $key => $value) {
            if (isset($properties[$key])) {
                $errors = array_merge($errors, self::validateAgainstSchema($value, $properties[$key], "{$path}.{$key}"));
                continue;
            }

            $errors = array_merge($errors, self::validateAdditionalProperty($key, $value, $schema, $path));
        }

        return $errors;
    }

    private static function validateRequiredProperties(array $data, array $schema, string $path): array
    {
        $errors = [];

        foreach ((array) ($schema["required"] ?? []) as $requiredKey) {
            if (!array_key_exists($requiredKey, $data)) {
                $errors[] = "{$path}: missing required property '{$requiredKey}'";
            }
        }

        return $errors;
    }

    private static function validateAdditionalProperty(int|string $key, $value, array $schema, string $path): array
    {
        $additionalProperties = $schema["additionalProperties"] ?? null;

        if ($additionalProperties === false) {
            return ["{$path}: unexpected property '{$key}'"];
        }

        if (is_array($additionalProperties)) {
            return self::validateAgainstSchema($value, $additionalProperties, "{$path}.{$key}");
        }

        return [];
    }

    private static function validateItems(array $data, array $schema, string $path): array
    {
        $errors = [];

        foreach ($data as $index => $item) {
            $errors = array_merge($errors, self::validateAgainstSchema($item, $schema["items"], "{$path}[{$index}]"));
        }

        return $errors;
    }

    private static function matchesAnyType($data, array $types): bool
    {
        foreach ($types as $type) {
            if (self::matchesType($data, $type)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesType($data, string $type): bool
    {
        return match ($type) {
            "object" => is_array($data) && (self::isAssoc($data) || $data === []),
            "array" => is_array($data) && !self::isAssoc($data),
            "string" => is_string($data),
            "integer" => is_int($data),
            "number" => is_int($data) || is_float($data),
            "boolean" => is_bool($data),
            "null" => $data === null,
            default => true,
        };
    }

    private static function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    private static function describeType($data): string
    {
        return match (true) {
            is_array($data) => self::isAssoc($data) ? "object" : "array",
            is_string($data) => "string",
            is_int($data) => "integer",
            is_float($data) => "number",
            is_bool($data) => "boolean",
            $data === null => "null",
            default => "unknown",
        };
    }
}
