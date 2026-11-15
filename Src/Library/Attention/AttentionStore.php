<?php

namespace GuiBranco\ProjectsMonitor\Library\Attention;

/**
 * Persists snoozes and acknowledgements of attention items, by fingerprint,
 * and reads the threshold overrides, in the attention_states and
 * attention_thresholds tables.
 */
class AttentionStore
{
    public const SNOOZED = "snoozed";

    public const ACKNOWLEDGED = "acknowledged";

    /**
     * The database connection.
     *
     * @var \mysqli
     */
    private \mysqli $connection;


    /**
     * Uses an open database connection.
     *
     * @param \mysqli $connection The connection.
     */
    public function __construct(\mysqli $connection)
    {
        $this->connection = $connection;

    }//end __construct()


    /**
     * Every stored state, by fingerprint, as rows of state, snoozedUntil (Unix time) and acknowledgedSince.
     *
     * @return array
     */
    public function getStates(): array
    {
        $states = [];
        $result = $this->connection->query("SELECT fingerprint, state, snoozed_until, acknowledged_since FROM attention_states");
        while (($row = $result->fetch_assoc()) !== null) {
            $state = [];

            $state["state"]             = $row["state"];
            $state["snoozedUntil"]      = $this->toTimestamp($row["snoozed_until"]);
            $state["acknowledgedSince"] = $row["acknowledged_since"];

            $states[$row["fingerprint"]] = $state;
        }

        return $states;

    }//end getStates()


    /**
     * The threshold overrides, by name.
     *
     * @return array
     */
    public function getThresholdOverrides(): array
    {
        $overrides = [];
        $result    = $this->connection->query("SELECT name, value FROM attention_thresholds");
        while (($row = $result->fetch_assoc()) !== null) {
            $overrides[$row["name"]] = (float) $row["value"];
        }

        return $overrides;

    }//end getThresholdOverrides()


    /**
     * Hides an item until a given time.
     *
     * @param string  $fingerprint The item's fingerprint.
     * @param integer $until       Unix time the snooze ends.
     * @param integer $userId      Who snoozed it.
     *
     * @return boolean
     */
    public function snooze(string $fingerprint, int $until, int $userId): bool
    {
        $state     = self::SNOOZED;
        $untilDate = gmdate("Y-m-d H:i:s", $until);
        $sql       = "REPLACE INTO attention_states (fingerprint, state, snoozed_until, acknowledged_since, user_id) VALUES (?, ?, ?, NULL, ?)";
        $stmt      = $this->connection->prepare($sql);
        $stmt->bind_param("sssi", $fingerprint, $state, $untilDate, $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;

    }//end snooze()


    /**
     * Marks an item as seen, for as long as its since value does not change.
     *
     * @param string      $fingerprint The item's fingerprint.
     * @param string|null $since       The item's since value when acknowledged.
     * @param integer     $userId      Who acknowledged it.
     *
     * @return boolean
     */
    public function acknowledge(string $fingerprint, ?string $since, int $userId): bool
    {
        $state = self::ACKNOWLEDGED;
        $sql   = "REPLACE INTO attention_states (fingerprint, state, snoozed_until, acknowledged_since, user_id) VALUES (?, ?, NULL, ?, ?)";
        $stmt  = $this->connection->prepare($sql);
        $stmt->bind_param("sssi", $fingerprint, $state, $since, $userId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;

    }//end acknowledge()


    /**
     * Removes the snooze or acknowledgement of an item.
     *
     * @param string $fingerprint The item's fingerprint.
     *
     * @return boolean
     */
    public function clear(string $fingerprint): bool
    {
        $stmt = $this->connection->prepare("DELETE FROM attention_states WHERE fingerprint = ?");
        $stmt->bind_param("s", $fingerprint);
        $result = $stmt->execute();
        $stmt->close();
        return $result;

    }//end clear()


    /**
     * Deletes ended snoozes and the acknowledgements of items that are no longer raised.
     *
     * @param array   $fingerprints The fingerprints of every item raised now.
     * @param integer $now          The current Unix time.
     *
     * @return void
     */
    public function prune(array $fingerprints, int $now): void
    {
        foreach ($this->getStates() as $fingerprint => $state) {
            $ended = $state["state"] === self::SNOOZED && (int) $state["snoozedUntil"] <= $now;
            $gone  = $state["state"] === self::ACKNOWLEDGED && in_array($fingerprint, $fingerprints, true) === false;
            if ($ended === true || $gone === true) {
                $this->clear((string) $fingerprint);
            }
        }

    }//end prune()


    /**
     * A UTC DATETIME column as Unix time.
     *
     * @param string|null $value The column value.
     *
     * @return integer|null
     */
    private function toTimestamp(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $time = strtotime(sprintf("%s UTC", $value));
        if ($time === false) {
            return null;
        }

        return $time;

    }//end toTimestamp()


}//end class
