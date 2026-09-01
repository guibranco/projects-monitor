<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

use InvalidArgumentException;
use RuntimeException;

/**
 * Parses PHP error logs with multiline error messages and optional stack traces.
 */
class LogParser
{
    /**
     * Regex pattern to extract log entries.
     *
     * Captures:
     * - date (timestamp)
     * - multilineError (error message, possibly multiline)
     * - file (file path where error occurred)
     * - line (line number)
     * - stackTraceDetails (optional stack trace lines)
     *
     * multilineError's lookahead deliberately uses \z, not bare $, for the
     * "no file/line info follows" fallback: the /m modifier makes $ match at
     * every line end (needed so back-to-back single-line entries each stop at
     * their own line), which would otherwise cut a genuinely multi-line error
     * body off at its first embedded newline instead of searching further for
     * "in file.php:NN". The extra alternative still bounds the capture against
     * a following [date]-prefixed entry that itself has no file/line info.
     */
    private const REGEX_PATTERN = '/
        \[
            (?<date>\d{2}-[A-Za-z]{3}-\d{4} \s \d{2}:\d{2}:\d{2} \s [A-Za-z\/_]+?)
        \]
        \s
        (?<multilineError>
            (?:.*?)
            (?=\s+in\s.+?\.php(?:\son\sline\s|:)\d+|\n\[\d{2}-[A-Za-z]{3}-\d{4}|\z)
        )
        (?:
            \s+in\s(?<file>.+?\.php)
            (?:\son\sline\s|:)
            (?<line>\d+)
            (?:\nStack\strace:\n
                (?<stackTraceDetails>
                    (?:\#\d+\s.+?\n)*
                )
                \s+thrown\sin\s.+?\.php\son\sline\s\d+
            )?
        )?
    $/msx';

    /**
     * Parses a full log string and extracts error entries.
     *
     * @param string $logContents The complete multiline log text.
     *
     * @return array<int, array{
     *     date: string,
     *     multilineError: string,
     *     file: string,
     *     line: string,
     *     stackTraceDetails?: string
     * }>
     *
     * @throws InvalidArgumentException If the log string is empty.
     */
    public function parse(string $logContents): array
    {
        if (trim($logContents) === '') {
            throw new InvalidArgumentException('Log content cannot be empty.');
        }

        $matches = [];
        $results = [];

        preg_match_all(self::REGEX_PATTERN, $logContents, $matches, PREG_SET_ORDER);

        $error = preg_last_error();
        if ($error !== PREG_NO_ERROR) {
            throw new RuntimeException('Regex error: ' . preg_last_error_msg(), $error);
        }

        foreach ($matches as $match) {
            $entry = [
                'date' => $match['date'],
                'multilineError' => trim($match['multilineError']),
                'file' => $match['file'] ?? 'NO FILE',
                'line' => $match['line'] ?? '-',
            ];

            if (isset($match['stackTraceDetails']) && $match['stackTraceDetails'] !== '') {
                $entry['stackTraceDetails'] = trim($match['stackTraceDetails']);
            }

            $results[] = $entry;
        }

        return $results;
    }
}
