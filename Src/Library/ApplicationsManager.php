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
    public const MAX_SEQUENCE = 1000;

    /**
     * The columns read for an application.
     */
    private const COLUMNS = "id, name, `key`, token, created_at, updated_at, last_seen_at";

    /**
     * The database connection.
     *
     * @var \mysqli
     */
    private $connection;

    /**
     * Builds the keys and tokens.
     *
     * @var ApplicationKey
     */
    private ApplicationKey $applicationKey;


    /**
     * Creates the manager.
     *
     * @param \mysqli        $connection     The database connection.
     * @param ApplicationKey $applicationKey Builds the keys and tokens.
     */
    public function __construct($connection, ApplicationKey $applicationKey)
    {
        $this->connection     = $connection;
        $this->applicationKey = $applicationKey;

    }//end __construct()


    /**
     * Every application, by name, with its token masked.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ApplicationsException When the query fails.
     */
    public function list(): array
    {
        $result = $this->connection->query(sprintf("SELECT %s FROM applications ORDER BY name", self::COLUMNS));
        if ($result === false) {
            throw new ApplicationsException(sprintf("Failed to list the applications: %s", $this->getError($this->connection)));
        }

        $applications = [];
        while (($row = $result->fetch_assoc()) !== null) {
            $applications[] = $this->getFields($row, false);
        }

        return $applications;

    }//end list()


    /**
     * Creates an application with a new key and token.
     *
     * @param string $name The application's name.
     *
     * @return array<string, mixed> The application, with its full token.
     *
     * @throws InvalidApplicationException When the name is invalid or already used.
     * @throws ApplicationsException       When the database fails.
     */
    public function create(string $name): array
    {
        $name = $this->getValidName($name, 0);
        $key  = $this->nextFreeKey($name, 0);

        $values = [
                   $name,
                   $key,
                   $this->applicationKey->generateToken(),
                  ];
        $this->runStatement("INSERT INTO applications (`name`, `key`, `token`) VALUES (?, ?, ?)", "sss", $values)->close();

        // The key is unique, so it identifies the new row.
        $row = $this->getRowBy("`key`", "s", $key);
        if ($row === null) {
            throw new ApplicationsException(sprintf("The application %s was not found after being created", $name));
        }

        return $this->getFields($row, true);

    }//end create()


    /**
     * Renames an application. The key is kept, so the application keeps
     * working; rotate the key to make it follow the new name.
     *
     * @param integer $appId The application's ID.
     * @param string  $name  The new name.
     *
     * @return array<string, mixed>|null The application, or null when there is none with this ID.
     *
     * @throws InvalidApplicationException When the name is invalid or already used.
     */
    public function rename(int $appId, string $name): ?array
    {
        if ($this->getRowBy("id", "i", $appId) === null) {
            return null;
        }

        $values = [
                   $this->getValidName($name, $appId),
                   $appId,
                  ];
        $this->runStatement("UPDATE applications SET `name` = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", "si", $values)->close();

        return $this->getApplication($appId, false);

    }//end rename()


    /**
     * Sets the application's key to the one built from its current name.
     *
     * @param integer $appId The application's ID.
     *
     * @return array<string, mixed>|null The application, or null when there is none with this ID.
     */
    public function rotateKey(int $appId): ?array
    {
        $row = $this->getRowBy("id", "i", $appId);
        if ($row === null) {
            return null;
        }

        $values = [
                   $this->nextFreeKey((string) $row["name"], $appId),
                   $appId,
                  ];
        $this->runStatement("UPDATE applications SET `key` = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", "si", $values)->close();

        return $this->getApplication($appId, false);

    }//end rotateKey()


    /**
     * Gives the application a new token. The old one stops working at once.
     *
     * @param integer $appId The application's ID.
     *
     * @return array<string, mixed>|null The application with its full new token, or null when there is none with this ID.
     */
    public function rotateToken(int $appId): ?array
    {
        if ($this->getRowBy("id", "i", $appId) === null) {
            return null;
        }

        $values = [
                   $this->applicationKey->generateToken(),
                   $appId,
                  ];
        $this->runStatement("UPDATE applications SET token = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?", "si", $values)->close();

        return $this->getApplication($appId, true);

    }//end rotateToken()


    /**
     * The first key for the name that no other application uses.
     *
     * @param string  $name      The application's name.
     * @param integer $excludeId The application the key is for (0 for a new one).
     *
     * @return string
     *
     * @throws ApplicationsException When every key up to MAX_SEQUENCE is taken.
     */
    public function nextFreeKey(string $name, int $excludeId): string
    {
        $used = $this->getKeysInUse($name, $excludeId);

        for ($sequence = 1; $sequence <= self::MAX_SEQUENCE; $sequence++) {
            $key = $this->applicationKey->build($name, $sequence);
            if (in_array($key, $used, true) === false) {
                return $key;
            }
        }

        throw new ApplicationsException(sprintf("No free key for the application %s", $name));

    }//end nextFreeKey()


    /**
     * The name trimmed, once checked: not empty, fits the column, and not used
     * by another application.
     *
     * @param string  $name      The name.
     * @param integer $excludeId The application being named (0 for a new one).
     *
     * @return string
     *
     * @throws InvalidApplicationException When the name is invalid or already used.
     */
    public function getValidName(string $name, int $excludeId): string
    {
        $name = trim((string) preg_replace("/\s+/u", " ", $name));
        if ($name === "") {
            throw new InvalidApplicationException("The application's name is required");
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidApplicationException(sprintf("The application's name must have at most %d characters", self::NAME_MAX_LENGTH));
        }

        $stmt = $this->runStatement("SELECT id FROM applications WHERE `name` = ? AND id <> ? LIMIT 1", "si", [$name, $excludeId]);
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row !== null) {
            throw new InvalidApplicationException(sprintf("There is already an application named %s", $name));
        }

        return $name;

    }//end getValidName()


    /**
     * The fields the UI needs, the token masked unless asked for.
     *
     * @param array<string, mixed> $row       The application's row.
     * @param boolean              $withToken Whether to include the full token.
     *
     * @return array<string, mixed>
     */
    public function getFields(array $row, bool $withToken): array
    {
        $name  = (string) $row["name"];
        $key   = (string) $row["key"];
        $token = (string) $row["token"];

        $application = [
                        "id"                => (int) $row["id"],
                        "name"              => $name,
                        "key"               => $key,
                        "tokenMasked"       => $this->applicationKey->mask($token),
                        "keyFollowsPattern" => $this->applicationKey->matchesName($key, $name),
                        "createdAt"         => ($row["created_at"] ?? null),
                        "updatedAt"         => ($row["updated_at"] ?? null),
                        "lastSeenAt"        => ($row["last_seen_at"] ?? null),
                       ];

        if ($withToken === true) {
            $application["token"] = $token;
        }

        return $application;

    }//end getFields()


    /**
     * The keys other applications use that start like the name's key.
     *
     * @param string  $name      The application's name.
     * @param integer $excludeId The application to leave out.
     *
     * @return string[]
     */
    private function getKeysInUse(string $name, int $excludeId): array
    {
        $prefix = sprintf("%s%s%%", ApplicationKey::PREFIX, $this->applicationKey->acronym($name));
        $stmt   = $this->runStatement("SELECT `key` FROM applications WHERE `key` LIKE ? AND id <> ?", "si", [$prefix, $excludeId]);
        $result = $stmt->get_result();

        $keys = [];
        while (($row = $result->fetch_assoc()) !== null) {
            $keys[] = $row["key"];
        }

        $stmt->close();

        return $keys;

    }//end getKeysInUse()


    /**
     * The application, with the fields the UI needs.
     *
     * @param integer $appId     The application's ID.
     * @param boolean $withToken Whether to include the full token.
     *
     * @return array<string, mixed>
     *
     * @throws ApplicationsException When there is no application with this ID.
     */
    private function getApplication(int $appId, bool $withToken): array
    {
        $row = $this->getRowBy("id", "i", $appId);
        if ($row === null) {
            throw new ApplicationsException(sprintf("Application %d not found", $appId));
        }

        return $this->getFields($row, $withToken);

    }//end getApplication()


    /**
     * The row whose column equals the value, or null.
     *
     * @param string         $column The column, from this class only (never user input).
     * @param string         $type   The bind_param type of the value.
     * @param integer|string $value  The value.
     *
     * @return array<string, mixed>|null
     */
    private function getRowBy(string $column, string $type, $value): ?array
    {
        $sql  = sprintf("SELECT %s FROM applications WHERE %s = ?", self::COLUMNS, $column);
        $stmt = $this->runStatement($sql, $type, [$value]);
        $row  = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row;

    }//end getRowBy()


    /**
     * Prepares, binds and runs a statement. The caller closes it.
     *
     * @param string            $sql    The SQL, with ? placeholders.
     * @param string            $types  The bind_param types.
     * @param array<int, mixed> $values The values.
     *
     * @return \mysqli_stmt
     *
     * @throws ApplicationsException When the statement cannot be prepared or run.
     */
    private function runStatement(string $sql, string $types, array $values): \mysqli_stmt
    {
        $stmt = $this->connection->prepare($sql);
        if ($stmt === false) {
            throw new ApplicationsException(sprintf("Prepare failed: %s", $this->getError($this->connection)));
        }

        $stmt->bind_param($types, ...$values);
        if ($stmt->execute() === false) {
            $error = $this->getError($stmt);
            $stmt->close();
            throw new ApplicationsException(sprintf("Failed to run %s: %s", $sql, $error));
        }

        return $stmt;

    }//end runStatement()


    /**
     * The error of a connection or statement, or "unknown error" when it
     * cannot be read (the object is already closed, for example).
     *
     * @param \mysqli|\mysqli_stmt $handle The connection or statement.
     *
     * @return string
     */
    private function getError($handle): string
    {
        $error = "";
        try {
            $error = (string) $handle->error;
        } catch (\Throwable $exception) {
            $error = sprintf("unknown error (%s)", get_class($exception));
        }

        if ($error === "") {
            $error = "unknown error";
        }

        return $error;

    }//end getError()


}//end class
