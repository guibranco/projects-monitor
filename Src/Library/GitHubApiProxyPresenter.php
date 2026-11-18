<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GuiBranco\Pancake\ShieldsIo;

/**
 * Renders the github-api-proxy stats API responses for the dashboard: a
 * summary for the caption, a 24-hour requests/errors line chart (one point
 * per hour, gaps filled with zeros), the callers table and the current
 * rate-limits table. Pure formatting, no HTTP, so it is unit-testable.
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
     * Longest user agent shown in the Client column.
     */
    private const USER_AGENT_LENGTH = 48;

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
     * @param DateTimeZone|null      $zone Display time zone; defaults to date.timezone,
     *                                     which Configuration sets from the browser's cookie.
     * @param DateTimeImmutable|null $now  Current time; defaults to now.
     */
    public function __construct(?DateTimeZone $zone=null, ?DateTimeImmutable $now=null)
    {
        $this->zone = ($zone ?? new DateTimeZone(date_default_timezone_get()));
        $this->now  = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')));

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
     * @return array Keys: summary, health, timeline, callers, rateLimits.
     */
    public function present(array $summary, array $timeline, array $callers, array $rateLimits, array $health): array
    {
        $shields = new ShieldsIo();

        $callerRows = [self::CALLERS_HEADER];
        foreach ($callers as $caller) {
            if (is_array($caller) === true) {
                $callerRows[] = $this->presentCaller($caller, $shields);
            }
        }

        $limitRows = [self::RATE_LIMITS_HEADER];
        foreach ($rateLimits as $limit) {
            if (is_array($limit) === true) {
                $limitRows[] = $this->presentRateLimit($limit, $shields);
            }
        }

        return [
                'summary'    => $this->presentSummary($summary, (count($callerRows) - 1)),
                'health'     => $this->presentHealth($health),
                'timeline'   => $this->presentTimeline($timeline),
                'callers'    => $callerRows,
                'rateLimits' => $limitRows,
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
        $writer = is_array($health['writer'] ?? null) ? $health['writer'] : [];

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
            $at     = $start->add(new DateInterval('PT'.$i.'H'));
            $counts = ($byHour[$at->format('Y-m-d H')] ?? [0, 0]);
            $rows[] = [
                       $at->setTimezone($this->zone)->format('H:i'),
                       $counts[0],
                       $counts[1],
                      ];
        }

        return $rows;

    }//end presentTimeline()


    /**
     * Renders one callers row.
     *
     * @param array     $caller  One caller entry.
     * @param ShieldsIo $shields Badge URL generator.
     *
     * @return array
     */
    private function presentCaller(array $caller, ShieldsIo $shields): array
    {
        $requests = (int) ($caller['requests'] ?? 0);
        $errors   = (int) ($caller['errors'] ?? 0);

        $errorsColor = 'green';
        if ($errors > 0) {
            $errorsColor = 'red';
        }

        return [
                $this->callerCell((string) ($caller['caller'] ?? ''), (array) ($caller['token_kinds'] ?? [])),
                $this->clientCell((array) ($caller['user_agents'] ?? []), (array) ($caller['client_ips'] ?? [])),
                number_format($requests),
                $this->badge($shields, 'errors', number_format($errors), $errorsColor, $errors.' requests answered 4xx/5xx'),
                number_format((float) ($caller['avg_duration_ms'] ?? 0), 1),
                $this->formatDate((string) ($caller['last_seen_at'] ?? '')),
               ];

    }//end presentCaller()


    /**
     * Renders one rate-limits row.
     *
     * @param array     $limit   One rate-limit entry.
     * @param ShieldsIo $shields Badge URL generator.
     *
     * @return array
     */
    private function presentRateLimit(array $limit, ShieldsIo $shields): array
    {
        $total     = (int) ($limit['limit'] ?? 0);
        $remaining = (int) ($limit['remaining'] ?? 0);
        $percent   = (float) ($limit['used_percent'] ?? 0);

        $resets = '—';
        $reset  = (string) ($limit['reset_at'] ?? '');
        if ($reset !== '') {
            try {
                $resets = (new DateTimeImmutable($reset))->setTimezone($this->zone)->format('H:i:s');
            } catch (Exception $exception) {
                $resets = '—';
            }
        }

        return [
                $this->callerCell((string) ($limit['caller'] ?? ''), [(string) ($limit['token_kind'] ?? '')]),
                $this->escape((string) ($limit['resource'] ?? '')),
                $this->badge(
                    $shields,
                    'used',
                    number_format($percent, 1).'%',
                    $this->usageColor($percent),
                    number_format($percent, 1).'% of the '.$total.' requests per window used'
                ),
                number_format($remaining).' / '.number_format($total),
                $resets,
               ];

    }//end presentRateLimit()


    /**
     * Colour of the used-quota badge.
     *
     * @param float $percent Used percentage.
     *
     * @return string
     */
    private function usageColor(float $percent): string
    {
        if ($percent >= 95) {
            return 'red';
        }

        if ($percent >= 80) {
            return 'orange';
        }

        if ($percent >= 50) {
            return 'yellow';
        }

        return 'green';

    }//end usageColor()


    /**
     * Caller cell. The proxy labels callers as a GitHub login,
     * installation:<account>, app:<id>, token:<fingerprint> (still being
     * resolved), invalid:<fingerprint> or anonymous.
     *
     * @param string $caller Caller label.
     * @param array  $kinds  Token kinds used by the caller.
     *
     * @return string
     */
    private function callerCell(string $caller, array $kinds): string
    {
        $kinds = array_values(array_filter(array_map('strval', $kinds), fn(string $kind) => $kind !== '' && $kind !== 'none'));
        $note  = '';
        if (count($kinds) > 0) {
            $note = ' <small class="text-white-50">'.$this->escape(implode(', ', $kinds)).'</small>';
        }

        [$prefix, $value] = array_pad(explode(':', $caller, 2), 2, '');

        switch ($prefix) {
        case 'installation':
            return $this->githubLink($value).' <small class="text-white-50">app installation</small>';
        case 'app':
            return 'GitHub App #'.$this->escape($value).$note;
        case 'token':
            return 'token <code>'.$this->escape($value).'</code> <small class="text-white-50">resolving</small>';
        case 'invalid':
            return 'invalid token <code>'.$this->escape($value).'</code>';
        case 'anonymous':
            return 'anonymous';
        }

        if ($caller === '') {
            return '—';
        }

        return $this->githubLink($caller).$note;

    }//end callerCell()


    /**
     * Client cell: the first user agent (shortened) and how many more were seen,
     * with the client IPs in the tooltip.
     *
     * @param array $userAgents User agents seen.
     * @param array $clientIps  Client IPs seen.
     *
     * @return string
     */
    private function clientCell(array $userAgents, array $clientIps): string
    {
        $userAgents = array_values(array_filter(array_map('strval', $userAgents), fn(string $ua) => $ua !== ''));
        if (count($userAgents) === 0) {
            return '—';
        }

        $first = $userAgents[0];
        if (mb_strlen($first) > self::USER_AGENT_LENGTH) {
            $first = mb_substr($first, 0, (self::USER_AGENT_LENGTH - 1)).'…';
        }

        $more = '';
        if (count($userAgents) > 1) {
            $more = ' <small class="text-white-50">+'.(count($userAgents) - 1).'</small>';
        }

        $title = implode("\n", array_merge($userAgents, array_map('strval', $clientIps)));

        return '<span title="'.$this->escape($title).'">'.$this->escape($first).'</span>'.$more;

    }//end clientCell()


    /**
     * Link to a GitHub account.
     *
     * @param string $login Account login.
     *
     * @return string
     */
    private function githubLink(string $login): string
    {
        $safe = $this->escape($login);
        $url  = $this->escape('https://github.com/'.rawurlencode($login));

        return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.$safe.'</a>';

    }//end githubLink()


    /**
     * Converts an ISO 8601 timestamp to the display time zone.
     *
     * @param string $value Timestamp, e.g. 2026-11-18T17:02:00.123Z.
     *
     * @return string The formatted time, or "—" when missing or unparseable.
     */
    private function formatDate(string $value): string
    {
        if ($value === '') {
            return '—';
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (Exception $exception) {
            return '—';
        }

        return $date->setTimezone($this->zone)->format(self::DATE_FORMAT);

    }//end formatDate()


    /**
     * HTML-escapes a value.
     *
     * @param string $value Raw text.
     *
     * @return string
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

    }//end escape()


    /**
     * Renders one shields.io badge as an <img> tag.
     *
     * @param ShieldsIo $shields Badge URL generator.
     * @param string    $label   Left-hand label.
     * @param string    $content Right-hand content.
     * @param string    $color   Content colour.
     * @param string    $alt     Alt/title text.
     *
     * @return string
     */
    private function badge(ShieldsIo $shields, string $label, string $content, string $color, string $alt): string
    {
        $badgeUrl = $shields->generateBadgeUrl($label, $content, $color, 'for-the-badge', 'black', null);
        $url      = $this->escape((string) $badgeUrl);
        $safeAlt  = $this->escape($alt);

        return '<img alt="'.$safeAlt.'" title="'.$safeAlt.'" src="'.$url.'" />';

    }//end badge()


}//end class
