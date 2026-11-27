<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Manages the applications that send messages to the API: lists them, creates
 * them with a key (pmk-{acronym}-key) and a token, renames them, and rotates
 * their key or token. Every change sets updated_at; last_seen_at is set by
 * Logger when a message is stored.
 *
 * The full token is only returned by create() and rotateToken(); lists show it
 * masked.
 */
class ApplicationsManager
{
    public const NAME_MAX_LENGTH = 255;

    /**
     * How many keys with the same acronym are tried before giving up.
     */
    private const MAX_SEQUENCE = 1000;

    /**
     * @var \mysqli
     */
    private $connection;

    /**
     * @param \mysqli|null $connection The connection to use (a new one when null).
     */
    public function __construct($connection = null)
    {
        $this->connection = $connection ?? (new Database())->getConnection();
    }

    /**
     * Every application, by name, with its token masked.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        $sql = "SELECT id, name, `key`, token, created_at, updated_at, last_seen_at FROM applications ORDER BY name";
        $result = $this->connection->query($sql);
        if ($result === false) {
            throw new \RuntimeException("Failed to list the applications: " . $this->connection->error);
        }

        $applications = [];
        while ($row = $result->fetch_assoc()) {
            $applications[] = $this->present($row, false);
        }

        return $applications;
    }

    /**
     * Creates an application with a new key and token.
     *
     * @param string $name The application's name.
     *
     * @return array<string, mixed> The application, with its full token.
     *
     * @throws \InvalidArgumentException When the name is invalid or already used.
     */
    public function create(string $name): array
    {
        $name = $this->validateName($name);
        $this->ensureNameIsFree($name, 0);

        $key = $this->nextFreeKey($name, 0);
        $token = ApplicationKey::generateToken();

        $stmt = $this->prepare("INSERT INTO applications (`name`, `key`, `token`) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $key, $token);
        $this->execute($stmt, "create the application");
        $stmt->close();

        // The key is unique, so it identifies the new row.
        $row = $this->findBy("`key`", "s", $key);
        if ($row === null) {
            throw new \RuntimeException("The application " . $name . " was not found after being created");
        }

        return $this->present($row, true);
    }

    /**
     * Renames an application. The key is kept, so the application keeps
     * working; rotate the key to make it follow the new name.
     *
     * @param int    $id   The application's ID.
     * @param string $name The new name.
     *
     * @return array<string, mixed>|null The application, or null when there is none with this ID.
     *
     * @throws \InvalidArgumentException When the name is invalid or already used.
     */
    public function rename(int $id, string $name): ?array
    {
        $name = $this->validateName($name);
        if ($this->find($id) === null) {
            return null;
        }

        $this->ensureNameIsFree($name, $id);

        $stmt = $this->prepare("UPDATE applications SET `name` = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("si", $name, $id);
        $this->execute($stmt, "rename the application");
        $stmt->close();

        return $this->get($id, false);
    }

    /**
     * Sets the application's key to the one built from its current name.
     *
     * @param int $id The application's ID.
     *
     * @return array<string, mixed>|null The application, or null when there is none with this ID.
     */
    public function rotateKey(int $id): ?array
    {
        $application = $this->find($id);
        if ($application === null) {
            return null;
        }

        $key = $this->nextFreeKey($application["name"], $id);

        $stmt = $this->prepare("UPDATE applications SET `key` = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("si", $key, $id);
        $this->execute($stmt, "rotate the application's key");
        $stmt->close();

        return $this->get($id, false);
    }

    /**
     * Gives the application a new token. The old one stops working at once.
     *
     * @param int $id The application's ID.
     *
     * @return array<string, mixed>|null The application with its full new token, or null when there is none with this ID.
     */
    public function rotateToken(int $id): ?array
    {
        if ($this->find($id) === null) {
            return null;
        }

        $token = ApplicationKey::generateToken();

        $stmt = $this->prepare("UPDATE applications SET token = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("si", $token, $id);
        $this->execute($stmt, "rotate the application's token");
        $stmt->close();

        return $this->get($id, true);
    }

    /**
     * The first key for the name that no other application uses.
     *
     * @param string $name      The application's name.
     * @param int    $excludeId The application the key is for (0 for a new one).
     *
     * @return string
     */
    public function nextFreeKey(string $name, int $excludeId): string
    {
        $used = $this->keysInUse($name, $excludeId);

        for ($sequence = 1; $sequence <= self::MAX_SEQUENCE; $sequence++) {
            $key = ApplicationKey::build($name, $sequence);
            if (in_array($key, $used, true) === false) {
                return $key;
            }
        }

        throw new \RuntimeException("No free key for the application " . $name);
    }

    /**
     * Trims the name and checks it is not empty and fits the column.
     *
     * @param string $name The name.
     *
     * @return string The trimmed name.
     */
    public function validateName(string $name): string
    {
        $name = trim(preg_replace("/\s+/u", " ", $name) ?? "");
        if ($name === "") {
            throw new \InvalidArgumentException("The application's name is required");
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new \InvalidArgumentException("The application's name must have at most " . self::NAME_MAX_LENGTH . " characters");
        }

        return $name;
    }

    /**
     * The keys other applications use that start like the name's key.
     *
     * @param string $name      The application's name.
     * @param int    $excludeId The application to leave out.
     *
     * @return string[]
     */
    private function keysInUse(string $name, int $excludeId): array
    {
        $prefix = ApplicationKey::PREFIX . ApplicationKey::acronym($name) . "%";
        $stmt = $this->prepare("SELECT `key` FROM applications WHERE `key` LIKE ? AND id <> ?");
        $stmt->bind_param("si", $prefix, $excludeId);
        $this->execute($stmt, "read the keys in use");
        $result = $stmt->get_result();

        $keys = [];
        while ($row = $result->fetch_assoc()) {
            $keys[] = $row["key"];
        }

        $stmt->close();

        return $keys;
    }

    /**
     * @throws \InvalidArgumentException When another application has the name.
     */
    private function ensureNameIsFree(string $name, int $excludeId): void
    {
        $stmt = $this->prepare("SELECT id FROM applications WHERE `name` = ? AND id <> ? LIMIT 1");
        $stmt->bind_param("si", $name, $excludeId);
        $this->execute($stmt, "check the application's name");
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row !== null) {
            throw new \InvalidArgumentException("There is already an application named " . $name);
        }
    }

    /**
     * The application's row, or null.
     *
     * @param int $id The application's ID.
     *
     * @return array<string, mixed>|null
     */
    private function find(int $id): ?array
    {
        return $this->findBy("id", "i", $id);
    }

    /**
     * The row whose column equals the value, or null.
     *
     * @param string     $column The column, from this class only (never user input).
     * @param string     $type   The bind_param type of the value.
     * @param int|string $value  The value.
     *
     * @return array<string, mixed>|null
     */
    private function findBy(string $column, string $type, $value): ?array
    {
        $stmt = $this->prepare("SELECT id, name, `key`, token, created_at, updated_at, last_seen_at FROM applications WHERE " . $column . " = ?");
        $stmt->bind_param($type, $value);
        $this->execute($stmt, "read the application");
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    /**
     * The application, presented.
     */
    private function get(int $id, bool $withToken): array
    {
        $row = $this->find($id);
        if ($row === null) {
            throw new \RuntimeException("Application " . $id . " not found");
        }

        return $this->present($row, $withToken);
    }

    /**
     * The fields the UI needs, the token masked unless asked for.
     *
     * @param array<string, mixed> $row       The application's row.
     * @param bool                 $withToken Whether to include the full token.
     *
     * @return array<string, mixed>
     */
    public function present(array $row, bool $withToken): array
    {
        $application = [
            "id" => (int) $row["id"],
            "name" => (string) $row["name"],
            "key" => (string) $row["key"],
            "tokenMasked" => ApplicationKey::mask((string) $row["token"]),
            "keyFollowsPattern" => ApplicationKey::matchesName((string) $row["key"], (string) $row["name"]),
            "createdAt" => $row["created_at"] ?? null,
            "updatedAt" => $row["updated_at"] ?? null,
            "lastSeenAt" => $row["last_seen_at"] ?? null,
        ];

        if ($withToken) {
            $application["token"] = (string) $row["token"];
        }

        return $application;
    }

    private function prepare(string $sql): \mysqli_stmt
    {
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new \RuntimeException("Prepare failed: " . $this->connection->error);
        }

        return $stmt;
    }

    private function execute(\mysqli_stmt $stmt, string $action): void
    {
        if ($stmt->execute() === false) {
            $error = $stmt->error;
            $stmt->close();
            throw new \RuntimeException("Failed to " . $action . ": " . $error);
        }
    }
}
