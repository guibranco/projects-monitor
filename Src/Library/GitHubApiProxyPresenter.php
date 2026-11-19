<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Renders the github-api-proxy stats API responses for the dashboard: a
 * summary for the caption, a requests/errors line chart over the chosen
 * GitHubApiProxyRange (one point per minute, hour or day, gaps filled
 * with zeros), the callers table and the current
 * rate-limits table, with the HTML cells from GitHubApiProxyCells. Pure
 * formatting, no HTTP, so it is unit-testable.
 */
class GitHubApiProxyPresenter
{

    public const CALLERS_HEADER = [
                                   'Caller',
                                   'Client',
                                   'Requests',
                                   'Errors',
                                   'Avg (ms)',
                                   'Last seen',
                                  ];

    public const RATE_LIMITS_HEADER = [
                                       'Caller',
                                       'Resource',
                                       'Used',
                                       'Remaining',
                                       'Resets',
                                      ];

    public const TIMELINE_HEADER = [
                                    'Hour',
                                    'Requests',
                                    'Errors',
                                   ];

    /**
     * Per bucket: DateInterval spec (with a %d count), UTC key format of a
     * point, and the timeline's first column title.
     */
    private const BUCKET_STEPS = [
                                  'minute' => 'PT%dM',
                                  'hour'   => 'PT%dH',
                                  'day'    => 'P%dD',
                                 ];

    private const BUCKET_KEYS = [
                                 'minute' => 'Y-m-d H:i',
                                 'hour'   => 'Y-m-d H',
                                 'day'    => 'Y-m-d',
                                ];

    private const BUCKET_TITLES = [
                                   'minute' => 'Minute',
                                   'hour'   => 'Hour',
                                   'day'    => 'Day',
                                  ];

    /**
     * Display format of full dates, in the dashboard's time zone.
     */
    private const DATE_FORMAT = 'd/m/Y H:i:s';

    /**
     * Display time zone.
     *
     * @var DateTimeZone
     */
    private $zone;

    /**
     * Current time, injectable for tests.
     *
     * @var DateTimeImmutable
     */
    private $now;

    /**
     * HTML cell renderer.
     *
     * @var GitHubApiProxyCells
     */
    private $cells;


    /**
     * @param DateTimeZone|null      $zone Display time zone; defaults to date.timezone,
     *                                     which Configuration sets from the browser's cookie.
     * @param DateTimeImmutable|null $now  Current time; defaults to now.
     */
    public function __construct(?DateTimeZone $zone=null, ?DateTimeImmutable $now=null)
    {
        $this->zone  = ($zone ?? new DateTimeZone(date_default_timezone_get()));
        $this->now   = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $this->cells = new GitHubApiProxyCells();

    }//end __construct()


    /**
     * Builds the dashboard payload.
     *
     * @param array                    $summary    `data` of GET api/v1/summary.
     * @param array                    $timeline   `data` of GET api/v1/timeline (the range's buckets).
     * @param array                    $callers    `data` of GET api/v1/callers.
     * @param array                    $rateLimits `data` of GET api/v1/rate-limits.
     * @param array                    $health     GET /health body.
     * @param GitHubApiProxyRange|null $range      Range the stats were queried for; defaults to 24h.
     *
     * @return array Keys: range (key and label), summary, health, timeline,
     *               callers, callerTypes (the type of each callers row,
     *               header excluded) and rateLimits.
     */
    public function present(
        array $summary,
        array $timeline,
        array $callers,
        array $rateLimits,
        array $health,
        ?GitHubApiProxyRange $range=null
    ): array {
        $range = ($range ?? new GitHubApiProxyRange());

        $callerRows  = [self::CALLERS_HEADER];
        $callerTypes = [];
        foreach ($callers as $caller) {
            if (is_array($caller) === true) {
                $callerRows[]  = $this->presentCaller($caller);
                $callerTypes[] = $this->cells->callerType((string) ($caller['caller'] ?? ''));
            }
        }

        $limitRows = [self::RATE_LIMITS_HEADER];
        foreach ($rateLimits as $limit) {
            if (is_array($limit) === true) {
                $limitRows[] = $this->presentRateLimit($limit);
            }
        }

        return [
                'range'       => [
                                  'key'   => $range->key(),
                                  'label' => $range->label(),
                                 ],
                'summary'     => $this->presentSummary($summary, (count($callerRows) - 1)),
                'health'      => $this->presentHealth($health),
                'timeline'    => $this->presentTimeline($timeline, $range),
                'callers'     => $callerRows,
                'callerTypes' => $callerTypes,
                'rateLimits'  => $limitRows,
               ];

    }//end present()


    /**
     * Totals for the caption.
     *
     * @param array   $summary Summary data.
     * @param integer $callers Number of distinct callers in the period.
     *
     * @return array
     */
    private function presentSummary(array $summary, int $callers): array
    {
        $clientErrors = (int) ($summary['client_errors'] ?? 0);
        $serverErrors = (int) ($summary['server_errors'] ?? 0);

        return [
                'requests'         => (int) ($summary['requests'] ?? 0),
                'errors'           => ($clientErrors + $serverErrors),
                'serverErrors'     => $serverErrors,
                'upstreamFailures' => (int) ($summary['upstream_failures'] ?? 0),
                'rateLimited'      => (int) ($summary['rate_limited'] ?? 0),
                'blocked'          => (int) ($summary['blocked'] ?? 0),
                'avgDurationMs'    => (float) ($summary['avg_duration_ms'] ?? 0),
                'callers'          => $callers,
                'lastRequestAt'    => $this->formatDate((string) ($summary['last_request_at'] ?? '')),
               ];

    }//end presentSummary()


    /**
     * Service health for the caption.
     *
     * @param array $health Health body.
     *
     * @return array
     */
    private function presentHealth(array $health): array
    {
        $writer = [];
        if (isset($health['writer']) === true && is_array($health['writer']) === true) {
            $writer = $health['writer'];
        }

        return [
                'status'        => (string) ($health['status'] ?? 'unknown'),
                'version'       => (string) ($health['version'] ?? ''),
                'database'      => (string) ($health['database'] ?? 'unknown'),
                'uptimeSeconds' => (int) ($health['uptime_seconds'] ?? 0),
                'dropped'       => (int) ($writer['dropped_since_start'] ?? 0),
               ];

    }//end presentHealth()


    /**
     * One point per bucket of the range, ending at the current one, oldest
     * first; buckets with no requests (absent from the API, which only
     * returns non-empty buckets) are 0.
     *
     * @param array               $timeline Timeline buckets.
     * @param GitHubApiProxyRange $range    Range, giving the bucket and point count.
     *
     * @return array Header row, then [label, requests, errors] rows.
     */
    private function presentTimeline(array $timeline, GitHubApiProxyRange $range): array
    {
        $utc    = new DateTimeZone('UTC');
        $bucket = $range->bucket();
        $step   = self::BUCKET_STEPS[$bucket];
        $keyFmt = self::BUCKET_KEYS[$bucket];

        $byBucket = [];
        foreach ($timeline as $point) {
            if (is_array($point) === false || isset($point['bucket']) === false) {
                continue;
            }

            try {
                $at = new DateTimeImmutable((string) $point['bucket']);
            } catch (Exception $exception) {
                continue;
            }

            $byBucket[$at->setTimezone($utc)->format($keyFmt)] = [
                                                                  (int) ($point['requests'] ?? 0),
                                                                  (int) ($point['errors'] ?? 0),
                                                                 ];
        }

        $points = $range->points();
        $start  = $this->bucketStart($bucket)->sub(new DateInterval(sprintf($step, ($points - 1))));

        $header    = self::TIMELINE_HEADER;
        $header[0] = self::BUCKET_TITLES[$bucket];
        $rows      = [$header];
        for ($i = 0; $i < $points; $i++) {
            $at     = $start->add(new DateInterval(sprintf($step, $i)));
            $counts = ($byBucket[$at->format($keyFmt)] ?? [0, 0]);
            $rows[] = [
                       $this->bucketLabel($at, $bucket, $points),
                       $counts[0],
                       $counts[1],
                      ];
        }

        return $rows;

    }//end presentTimeline()


    /**
     * Start of the bucket the current time falls in, in UTC (the proxy
     * buckets by UTC minute, hour and day).
     *
     * @param string $bucket minute, hour or day.
     *
     * @return DateTimeImmutable
     */
    private function bucketStart(string $bucket): DateTimeImmutable
    {
        $now = $this->now->setTimezone(new DateTimeZone('UTC'));
        $h   = (int) $now->format('H');
        if ($bucket === 'day') {
            return $now->setTime(0, 0);
        }

        if ($bucket === 'hour') {
            return $now->setTime($h, 0);
        }

        return $now->setTime($h, (int) $now->format('i'));

    }//end bucketStart()


    /**
     * Axis label of one timeline point. Minutes and hours are shown in the
     * dashboard's time zone (with the date once the range spans days); days
     * are UTC days, so they keep their UTC date.
     *
     * @param DateTimeImmutable $at     Bucket start, UTC.
     * @param string            $bucket minute, hour or day.
     * @param integer           $points Number of points in the range.
     *
     * @return string
     */
    private function bucketLabel(DateTimeImmutable $at, string $bucket, int $points): string
    {
        if ($bucket === 'day') {
            return $at->format('d/m');
        }

        $local = $at->setTimezone($this->zone);
        if ($bucket === 'hour' && $points > 24) {
            return $local->format('d/m H:i');
        }

        return $local->format('H:i');

    }//end bucketLabel()


    /**
     * Renders one callers row.
     *
     * @param array $caller One caller entry.
     *
     * @return array
     */
    private function presentCaller(array $caller): array
    {
        return [
                $this->cells->caller((string) ($caller['caller'] ?? ''), (array) ($caller['token_kinds'] ?? [])),
                $this->cells->client((array) ($caller['user_agents'] ?? []), (array) ($caller['client_ips'] ?? [])),
                number_format((int) ($caller['requests'] ?? 0)),
                $this->cells->errorsBadge((int) ($caller['errors'] ?? 0)),
                number_format((float) ($caller['avg_duration_ms'] ?? 0), 1),
                $this->formatDate((string) ($caller['last_seen_at'] ?? '')),
               ];

    }//end presentCaller()


    /**
     * Renders one rate-limits row.
     *
     * @param array $limit One rate-limit entry.
     *
     * @return array
     */
    private function presentRateLimit(array $limit): array
    {
        $total = (int) ($limit['limit'] ?? 0);

        return [
                $this->cells->caller((string) ($limit['caller'] ?? ''), [(string) ($limit['token_kind'] ?? '')]),
                $this->cells->escape((string) ($limit['resource'] ?? '')),
                $this->cells->usageBadge((float) ($limit['used_percent'] ?? 0), $total),
                number_format((int) ($limit['remaining'] ?? 0)).' / '.number_format($total),
                $this->formatDate((string) ($limit['reset_at'] ?? ''), 'H:i:s'),
               ];

    }//end presentRateLimit()


    /**
     * Converts an ISO 8601 timestamp to the display time zone.
     *
     * @param string $value  Timestamp, e.g. 2026-11-18T17:02:00.123Z.
     * @param string $format Output format.
     *
     * @return string The formatted time, or "—" when missing or unparseable.
     */
    private function formatDate(string $value, string $format=self::DATE_FORMAT): string
    {
        if ($value === '') {
            return '—';
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (Exception $exception) {
            return '—';
        }

        return $date->setTimezone($this->zone)->format($format);

    }//end formatDate()


}//end class
