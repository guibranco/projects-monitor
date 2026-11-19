<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Renders the github-api-proxy stats API responses for the dashboard: a
 * summary for the caption, a 24-hour requests/errors line chart (one point
 * per hour, gaps filled with zeros), the callers table and the current
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
     * Hourly points on the timeline (the stats API is queried for 24h).
     */
    private const TIMELINE_HOURS = 24;

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
     * @param array $summary    `data` of GET api/v1/summary.
     * @param array $timeline   `data` of GET api/v1/timeline (hour buckets).
     * @param array $callers    `data` of GET api/v1/callers.
     * @param array $rateLimits `data` of GET api/v1/rate-limits.
     * @param array $health     GET /health body.
     *
     * @return array Keys: summary, health, timeline, callers, callerTypes
     *               (the type of each callers row, header excluded) and rateLimits.
     */
    public function present(array $summary, array $timeline, array $callers, array $rateLimits, array $health): array
    {
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
                'summary'     => $this->presentSummary($summary, (count($callerRows) - 1)),
                'health'      => $this->presentHealth($health),
                'timeline'    => $this->presentTimeline($timeline),
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
     * One point per hour for the last 24 hours, oldest first; hours with no
     * requests (absent from the API, which only returns non-empty buckets) are 0.
     *
     * @param array $timeline Timeline buckets.
     *
     * @return array Header row, then [label, requests, errors] rows.
     */
    private function presentTimeline(array $timeline): array
    {
        $byHour = [];
        foreach ($timeline as $point) {
            if (is_array($point) === false || isset($point['bucket']) === false) {
                continue;
            }

            try {
                $bucket = new DateTimeImmutable((string) $point['bucket']);
            } catch (Exception $exception) {
                continue;
            }

            $key          = $bucket->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H');
            $byHour[$key] = [
                             (int) ($point['requests'] ?? 0),
                             (int) ($point['errors'] ?? 0),
                            ];
        }

        $utc   = new DateTimeZone('UTC');
        $hour  = $this->now->setTimezone($utc)->setTime((int) $this->now->setTimezone($utc)->format('H'), 0);
        $start = $hour->sub(new DateInterval('PT'.(self::TIMELINE_HOURS - 1).'H'));

        $rows = [self::TIMELINE_HEADER];
        for ($i = 0; $i < self::TIMELINE_HOURS; $i++) {
            $hourAt = $start->add(new DateInterval('PT'.$i.'H'));
            $counts = ($byHour[$hourAt->format('Y-m-d H')] ?? [0, 0]);
            $rows[] = [
                       $hourAt->setTimezone($this->zone)->format('H:i'),
                       $counts[0],
                       $counts[1],
                      ];
        }

        return $rows;

    }//end presentTimeline()


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
