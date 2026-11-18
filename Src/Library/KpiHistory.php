<?php

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * Server-side history of the dashboard's KPI tiles, in the kpi_samples
 * table: at most one sample per metric per minute, kept for a retention
 * window (kpi.history.retentionHours). Every dashboard that is open sends
 * the values it shows, so the sparklines are the same in every browser and
 * survive clearing the browser's storage.
 */
class KpiHistory
{
    public const METRIC_PATTERN = "/^[A-Za-z0-9_]{1,100}$/";

    public const MAX_SAMPLES_PER_REQUEST = 50;

    /**
     * The database connection, null for the pure helpers only.
     *
     * @var \mysqli|null
     */
    private ?\mysqli $connection;

    /**
     * How long samples are kept, in seconds.
     *
     * @var integer
     */
    private int $retentionSeconds;


    /**
     * Creates the history.
     *
     * @param \mysqli|null $connection     The connection (null for the pure helpers only).
     * @param float        $retentionHours How long samples are kept.
     */
    public function __construct(?\mysqli $connection, float $retentionHours=24)
    {
        $this->connection       = $connection;
        $this->retentionSeconds = (int) max(3600, round($retentionHours * 3600));

    }//end __construct()


    /**
     * The valid samples of a request body's "samples" object: metric names
     * matching METRIC_PATTERN with finite numeric values, at most
     * MAX_SAMPLES_PER_REQUEST of them.
     *
     * @param mixed $samples The "samples" value of the body.
     *
     * @return array Values by metric.
     */
    public function filterSamples($samples): array
    {
        if (is_array($samples) === false) {
            return [];
        }

        $valid = [];
        foreach ($samples as $metric => $value) {
            if (is_string($metric) === false || preg_match(self::METRIC_PATTERN, $metric) !== 1) {
                continue;
            }

            if ((is_int($value) === false && is_float($value) === false) || is_finite((float) $value) === false) {
                continue;
            }

            $valid[$metric] = (float) $value;
            if (count($valid) >= self::MAX_SAMPLES_PER_REQUEST) {
                break;
            }
        }

        return $valid;

    }//end filterSamples()


    /**
     * The valid metric names of a comma-separated list.
     *
     * @param string $list The list, e.g. "gauge_chart_queues,gauge_chart_webhooks".
     *
     * @return array
     */
    public function parseMetrics(string $list): array
    {
        $metrics = array_filter(
            array_map("trim", explode(",", $list)),
            fn ($metric) => preg_match(self::METRIC_PATTERN, $metric) === 1
        );

        return array_slice(array_values(array_unique($metrics)), 0, self::MAX_SAMPLES_PER_REQUEST);

    }//end parseMetrics()


    /**
     * The minute a sample is stored at, as a UTC DATETIME.
     *
     * @param integer $now The current Unix time.
     *
     * @return string
     */
    public function minuteOf(int $now): string
    {
        return gmdate("Y-m-d H:i:00", $now);

    }//end minuteOf()


    /**
     * Stores samples (replacing the one of the same minute) and deletes
     * those past the retention window.
     *
     * @param array   $samples Values by metric, as filterSamples() returns them.
     * @param integer $now     The current Unix time.
     *
     * @return integer How many were stored.
     */
    public function record(array $samples, int $now): int
    {
        $minute = $this->minuteOf($now);
        $stmt   = $this->connection->prepare(
            "INSERT INTO kpi_samples (metric, sampled_at, value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)"
        );

        $stored = 0;
        foreach ($samples as $metric => $value) {
            $name  = (string) $metric;
            $float = (float) $value;
            $stmt->bind_param("ssd", $name, $minute, $float);
            if ($stmt->execute() === true) {
                $stored++;
            }
        }

        $stmt->close();

        $cutoff = gmdate("Y-m-d H:i:s", ($now - $this->retentionSeconds));
        $delete = $this->connection->prepare("DELETE FROM kpi_samples WHERE sampled_at < ?");
        $delete->bind_param("s", $cutoff);
        $delete->execute();
        $delete->close();

        return $stored;

    }//end record()


    /**
     * The samples of the retention window, oldest first.
     *
     * @param array   $metrics The metrics.
     * @param integer $now     The current Unix time.
     *
     * @return array Series by metric: lists of t (Unix milliseconds) and v.
     */
    public function get(array $metrics, int $now): array
    {
        $series = array_fill_keys($metrics, []);
        if (count($metrics) === 0) {
            return $series;
        }

        $cutoff       = gmdate("Y-m-d H:i:s", ($now - $this->retentionSeconds));
        $placeholders = implode(",", array_fill(0, count($metrics), "?"));
        $stmt         = $this->connection->prepare(
            "SELECT metric, sampled_at, value FROM kpi_samples WHERE sampled_at >= ? AND metric IN ($placeholders) ORDER BY sampled_at"
        );
        $params = array_merge([$cutoff], $metrics);
        $stmt->bind_param(str_repeat("s", count($params)), ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while (($row = $result->fetch_assoc()) !== null) {
            $series[$row["metric"]][] = $this->toPoint($row["sampled_at"], (float) $row["value"]);
        }

        $stmt->close();

        return $series;

    }//end get()


    /**
     * A sample as the dashboard's sparklines take it.
     *
     * @param string $sampledAt The UTC DATETIME.
     * @param float  $value     The value.
     *
     * @return array
     */
    public function toPoint(string $sampledAt, float $value): array
    {
        return [
                "t" => ((int) strtotime($sampledAt." UTC") * 1000),
                "v" => $value,
               ];

    }//end toPoint()


}//end class
