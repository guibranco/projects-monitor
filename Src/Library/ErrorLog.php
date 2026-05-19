<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

class ErrorLog
{
    private \mysqli $connection;

    public function __construct()
    {
        $this->connection = (new Database())->getConnection();
    }

    /**
     * Returns true when an entry with the same path, date, file, and line already exists,
     * preventing duplicate rows for the same error occurrence.
     */
    private function isDuplicate(string $errorLogPath, string $date, string $file, int $line): bool
    {
        $sql = "SELECT id FROM errors WHERE error_log_path = ? AND date = ? AND file = ? AND line = ? LIMIT 1";
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param("sssi", $errorLogPath, $date, $file, $line);
        $stmt->execute();
        $stmt->store_result();
        $found = $stmt->num_rows > 0;
        $stmt->close();
        return $found;
    }

    /**
     * Inserts a parsed error entry into the errors table.
     * Returns false without querying the DB when the entry is a duplicate.
     *
     * @param string      $errorLogPath     Full server path of the source error_log file.
     * @param string      $date             MySQL datetime string (Y-m-d H:i:s).
     * @param string      $error            First line of the error message.
     * @param string      $errorMultiline   Full (potentially multiline) error message.
     * @param string      $file             PHP file where the error occurred.
     * @param int         $line             Line number where the error occurred.
     * @param string|null $stackTrace       Stack trace summary line, if captured.
     * @param string|null $stackTraceDetails Numbered stack frames (#0, #1 …), if captured.
     * @return bool True if a new row was inserted, false if duplicate or on failure.
     */
    public function saveError(
        string $errorLogPath,
        string $date,
        string $error,
        string $errorMultiline,
        string $file,
        int $line,
        ?string $stackTrace,
        ?string $stackTraceDetails
    ): bool {
        if ($this->isDuplicate($errorLogPath, $date, $file, $line)) {
            return false;
        }

        $sql = "INSERT INTO errors
                    (error_log_path, date, error, error_multiline, file, line, stack_trace, stack_trace_details)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param(
            "sssssiss",
            $errorLogPath,
            $date,
            $error,
            $errorMultiline,
            $file,
            $line,
            $stackTrace,
            $stackTraceDetails
        );
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    public function getTotal(): int
    {
        $total = 0;
        $stmt = $this->connection->prepare("SELECT COUNT(1) FROM errors");
        $stmt->execute();
        $stmt->bind_result($total);
        $stmt->fetch();
        $stmt->close();
        return $total;
    }

    public function getErrors(int $limit = 500): array
    {
        $rows = [];
        $stmt = $this->connection->prepare(
            "SELECT id, error_log_path, date, error, file, line
             FROM errors
             ORDER BY date DESC
             LIMIT ?"
        );
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
        return $rows;
    }

    public function truncate(): bool
    {
        return $this->connection->query("TRUNCATE TABLE errors") !== false;
    }

    public function deleteByPath(string $path): int
    {
        $stmt = $this->connection->prepare("DELETE FROM errors WHERE error_log_path = ?");
        $stmt->bind_param("s", $path);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }
}
