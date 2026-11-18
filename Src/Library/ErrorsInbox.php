<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * The Errors page's single list: application messages, PHP errors stored in
 * the database and error_log entries not ingested yet, grouped by a
 * fingerprint of where they happen and their message with the variable
 * parts (numbers, ids, hashes) taken out. A PHP error keeps the same
 * fingerprint before and after ProcessErrorLogs moves it from an error_log
 * file into the database, so both stages land in one group. No I/O.
 */
class ErrorsInbox
{
    public const SOURCE_APP = "app";

    public const SOURCE_PHP = "php";

    public const SOURCE_ERROR_LOG = "error_log";

    public const SOURCES = [
                            self::SOURCE_APP       => "App messages",
                            self::SOURCE_PHP       => "PHP errors",
                            self::SOURCE_ERROR_LOG => "error_log (not ingested)",
                           ];

    private const MAX_MESSAGE_LENGTH = 500;

    /**
     * The groups being built, by fingerprint.
     *
     * @var array
     */
    private array $groups = [];


    /**
     * The message with its variable parts replaced, so repeats of one error
     * compare equal: UUIDs, hexadecimal ids and numbers become placeholders
     * and whitespace is collapsed.
     *
     * @param string $message The message.
     *
     * @return string
     */
    public function normalize(string $message): string
    {
        $patterns = [
                     "/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i" => "{uuid}",
                     "/\b0x[0-9a-f]+\b/i"                                                => "{hex}",
                     "/\b(?=[0-9a-f]*\d)(?=[0-9a-f]*[a-f])[0-9a-f]{12,}\b/i"             => "{hex}",
                     "/\d+(\.\d+)?/"                                                     => "{n}",
                     "/\s+/"                                                             => " ",
                    ];

        $normalized = (string) preg_replace(array_keys($patterns), array_values($patterns), $message);
        return mb_substr(trim($normalized), 0, self::MAX_MESSAGE_LENGTH);

    }//end normalize()


    /**
     * The group fingerprint: the kind of error, where it happens and its
     * normalized message, hashed.
     *
     * @param string $kind    "app" or "php" (error_log entries are PHP errors).
     * @param string $origin  The application, or the file and line.
     * @param string $message The message.
     *
     * @return string
     */
    public function fingerprint(string $kind, string $origin, string $message): string
    {
        $hash = sha1(sprintf("%s|%s|%s", $kind, $origin, $this->normalize($message)));
        return sprintf("%s:%s", $kind, substr($hash, 0, 16));

    }//end fingerprint()


    /**
     * Groups the three sources into one list, most recent first.
     *
     * @param array $appRows   Logger::getGroupedMessageRows() rows: application, message, count, lastSeen, sampleId.
     * @param array $phpRows   ErrorLog::getErrors() rows: error_log_path, date, error, file, line.
     * @param array $logRows   CPanel::getErrorLogEntries() rows: date, directory, multilineError, file, line.
     * @param array $unread    Sources that could not be read, by source, with the reason.
     *
     * @return array
     */
    public function build(array $appRows, array $phpRows, array $logRows, array $unread=[]): array
    {
        $this->groups = [];

        foreach ($appRows as $row) {
            $this->addApp($row);
        }

        foreach ($phpRows as $row) {
            $this->addPhp(self::SOURCE_PHP, (string) ($row["error_log_path"] ?? ""), $row);
        }

        foreach ($logRows as $row) {
            $row["error"] = ($row["error"] ?? ($row["multilineError"] ?? ""));
            $this->addPhp(self::SOURCE_ERROR_LOG, (string) ($row["directory"] ?? ""), $row);
        }

        $groups = array_values($this->groups);
        usort($groups, [$this, "compareGroups"]);

        $sources = [];
        foreach (self::SOURCES as $source => $label) {
            $inSource = array_filter($groups, fn ($group) => isset($group["sources"][$source]));

            $entry = [];

            $entry["label"]       = $label;
            $entry["groups"]      = count($inSource);
            $entry["occurrences"] = array_sum(array_map(fn ($group) => $group["sources"][$source], $inSource));
            $entry["error"]       = ($unread[$source] ?? null);

            $sources[$source] = $entry;
        }

        $response = [];

        $response["groups"]      = $groups;
        $response["sources"]     = $sources;
        $response["total"]       = count($groups);
        $response["occurrences"] = array_sum(array_column($groups, "count"));

        return $response;

    }//end build()


    /**
     * Adds an application message group (already grouped by the Logger, but
     * per user agent and with the raw message).
     *
     * @param array $row The Logger row.
     *
     * @return void
     */
    private function addApp(array $row): void
    {
        $application = (string) ($row["application"] ?? "");
        $message     = (string) ($row["message"] ?? "");
        $group       = $this->getGroup(self::SOURCE_APP, $application, $message);

        $group["count"] += (int) ($row["count"] ?? 1);
        $group["sources"][self::SOURCE_APP] = (($group["sources"][self::SOURCE_APP] ?? 0) + (int) ($row["count"] ?? 1));
        $group["lastSeen"] = $this->latest($group["lastSeen"], $this->toLocal($row["lastSeen"] ?? null));
        if (isset($row["sampleId"]) === true) {
            $group["actions"]["sampleIds"][] = (int) $row["sampleId"];
        }

        $this->groups[$group["fingerprint"]] = $group;

    }//end addApp()


    /**
     * Adds a PHP error, stored in the database or still in an error_log file.
     *
     * @param string $source   SOURCE_PHP or SOURCE_ERROR_LOG.
     * @param string $location The error_log path (database) or directory (error_log file).
     * @param array  $row      The row: date, error, file, line.
     *
     * @return void
     */
    private function addPhp(string $source, string $location, array $row): void
    {
        // As ProcessErrorLogs stores them: only the first line of the error,
        // a non-numeric line as 0; paths compared relative to the home directory.
        $file   = (string) preg_replace("#^/home/[^/]+/#", "", (string) ($row["file"] ?? ""));
        $line   = is_numeric($row["line"] ?? null) === true ? (int) $row["line"] : 0;
        $error  = strtok(trim((string) ($row["error"] ?? "")), "\r\n");
        $origin = sprintf("%s:%d", $file, $line);
        $group  = $this->getGroup(self::SOURCE_PHP, $origin, $error === false ? "" : $error);

        $group["count"]++;
        $group["sources"][$source] = (($group["sources"][$source] ?? 0) + 1);
        $group["lastSeen"]         = $this->latest($group["lastSeen"], $this->toLocal($row["date"] ?? null));

        $key = "paths";
        if ($source === self::SOURCE_ERROR_LOG) {
            $key = "directories";
        }

        if ($location !== "" && in_array($location, $group["actions"][$key], true) === false) {
            $group["actions"][$key][] = $location;
        }

        $this->groups[$group["fingerprint"]] = $group;

    }//end addPhp()


    /**
     * The group of a fingerprint, new when it has not been seen yet.
     *
     * @param string $kind    "app" or "php".
     * @param string $origin  The application, or the file and line.
     * @param string $message The message.
     *
     * @return array
     */
    private function getGroup(string $kind, string $origin, string $message): array
    {
        $fingerprint = $this->fingerprint($kind, $origin, $message);
        if (isset($this->groups[$fingerprint]) === true) {
            return $this->groups[$fingerprint];
        }

        $group = [];

        $group["fingerprint"] = $fingerprint;
        $group["kind"]        = $kind;
        $group["origin"]      = $origin;
        $group["message"]     = mb_substr(trim($message), 0, self::MAX_MESSAGE_LENGTH);
        $group["count"]       = 0;
        $group["lastSeen"]    = null;
        $group["sources"]     = [];
        $group["actions"]     = [
                                 "sampleIds"   => [],
                                 "paths"       => [],
                                 "directories" => [],
                                ];

        return $group;

    }//end getGroup()


    /**
     * A date as "Y-m-d H:i:s" in the request's time zone, as the dashboard's
     * other tables show them; null when it cannot be read.
     *
     * @param string|null $date The date ("Y-m-d H:i:s" is kept; error_log dates carry their zone).
     *
     * @return string|null
     */
    private function toLocal(?string $date): ?string
    {
        if ($date === null || trim($date) === "") {
            return null;
        }

        if (preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/", $date) === 1) {
            return $date;
        }

        $time = strtotime($date);
        if ($time === false) {
            return null;
        }

        return date("Y-m-d H:i:s", $time);

    }//end toLocal()


    /**
     * The later of two "Y-m-d H:i:s" dates, either may be null.
     *
     * @param string|null $left  A date.
     * @param string|null $right Another date.
     *
     * @return string|null
     */
    private function latest(?string $left, ?string $right): ?string
    {
        if ($left === null) {
            return $right;
        }

        if ($right === null) {
            return $left;
        }

        return max($left, $right);

    }//end latest()


    /**
     * Orders groups: most recently seen first, then most frequent, then by message.
     *
     * @param array $left  A group.
     * @param array $right Another group.
     *
     * @return integer
     */
    public function compareGroups(array $left, array $right): int
    {
        return [(string) $right["lastSeen"], $right["count"], $left["message"]] <=> [(string) $left["lastSeen"], $left["count"], $right["message"]];

    }//end compareGroups()


}//end class
