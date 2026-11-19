<?php

namespace GuiBranco\ProjectsMonitor\Library;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Formats the GitHub API proxy's traffic for the dashboard: the busiest
 * routes (api/v1/routes) and the latest requests with their full URL
 * (api/v1/requests). Every value from the proxy is HTML-escaped.
 */
final class GitHubApiProxyTrafficPresenter
{
    public const ROUTES_HEADER = [
                                  'Method',
                                  'Route',
                                  'Requests',
                                  'Errors',
                                  'Avg (ms)',
                                  'Max (ms)',
                                  'Callers',
                                 ];

    public const REQUESTS_HEADER = [
                                    'Time',
                                    'Caller',
                                    'Method',
                                    'URL',
                                    'Status',
                                    'Duration (ms)',
                                    'Size',
                                   ];

    /**
     * Status class (first digit) => Bootstrap badge colour.
     */
    private const STATUS_CLASSES = [
                                    2 => 'text-bg-success',
                                    3 => 'text-bg-info',
                                    4 => 'text-bg-warning',
                                    5 => 'text-bg-danger',
                                   ];

    /**
     * Method => Bootstrap badge colour; anything else is secondary.
     */
    private const METHOD_CLASSES = [
                                    'GET'    => 'text-bg-primary',
                                    'POST'   => 'text-bg-success',
                                    'PUT'    => 'text-bg-warning',
                                    'PATCH'  => 'text-bg-warning',
                                    'DELETE' => 'text-bg-danger',
                                   ];

    /**
     * Longest URL shown in full; longer ones are cut, with the full URL in
     * the cell's tooltip.
     */
    private const URL_LENGTH = 90;

    private const SIZE_UNITS = [
                                'B',
                                'KB',
                                'MB',
                                'GB',
                               ];

    private DateTimeZone $zone;

    private GitHubApiProxyCells $cells;


    /**
     * @param DateTimeZone|null        $zone  Display time zone; defaults to date.timezone.
     * @param GitHubApiProxyCells|null $cells Shared cell renderer (caller, errors, escaping).
     */
    public function __construct(?DateTimeZone $zone=null, ?GitHubApiProxyCells $cells=null)
    {
        $this->zone  = ($zone ?? new DateTimeZone(date_default_timezone_get()));
        $this->cells = ($cells ?? new GitHubApiProxyCells());

    }//end __construct()


    /**
     * Builds the traffic tables.
     *
     * @param array $routes   `data` of GET api/v1/routes.
     * @param array $requests `data` of GET api/v1/requests (newest first).
     *
     * @return array Keys: routes and requests, each a header row followed by one row per entry.
     */
    public function present(array $routes, array $requests): array
    {
        $routeRows = [self::ROUTES_HEADER];
        foreach ($routes as $route) {
            if (is_array($route) === true) {
                $routeRows[] = $this->presentRoute($route);
            }
        }

        $requestRows = [self::REQUESTS_HEADER];
        foreach ($requests as $request) {
            if (is_array($request) === true) {
                $requestRows[] = $this->presentRequest($request);
            }
        }

        return [
                'routes'   => $routeRows,
                'requests' => $requestRows,
               ];

    }//end present()


    /**
     * Renders one routes row.
     *
     * @param array $route One route entry.
     *
     * @return array
     */
    private function presentRoute(array $route): array
    {
        return [
                $this->methodBadge((string) ($route['method'] ?? '')),
                '<code>'.$this->cells->escape((string) ($route['route'] ?? '')).'</code>',
                number_format((int) ($route['requests'] ?? 0)),
                $this->cells->errorsBadge((int) ($route['errors'] ?? 0)),
                number_format((float) ($route['avg_duration_ms'] ?? 0), 1),
                number_format((int) ($route['max_duration_ms'] ?? 0)),
                number_format((int) ($route['distinct_callers'] ?? 0)),
               ];

    }//end presentRoute()


    /**
     * Renders one requests row.
     *
     * @param array $request One request entry.
     *
     * @return array
     */
    private function presentRequest(array $request): array
    {
        return [
                $this->formatTime((string) ($request['requested_at'] ?? '')),
                $this->cells->caller((string) ($request['caller'] ?? ''), [(string) ($request['token_kind'] ?? '')]),
                $this->methodBadge((string) ($request['method'] ?? '')),
                $this->url(
                    (string) ($request['path'] ?? ''),
                    (string) ($request['query'] ?? ''),
                    (string) ($request['graphql_operation'] ?? '')
                ),
                $this->statusBadge((int) ($request['status'] ?? 0), (string) ($request['error'] ?? '')),
                number_format((int) ($request['duration_ms'] ?? 0)),
                $this->size((int) ($request['response_bytes'] ?? 0)),
               ];

    }//end presentRequest()


    /**
     * HTTP method as a coloured badge.
     *
     * @param string $method HTTP method.
     *
     * @return string
     */
    private function methodBadge(string $method): string
    {
        $method = strtoupper($method);
        $class  = (self::METHOD_CLASSES[$method] ?? 'text-bg-secondary');

        return '<span class="badge '.$class.'">'.$this->cells->escape($method).'</span>';

    }//end methodBadge()


    /**
     * The requested URL (path and query string), cut to URL_LENGTH with the
     * full URL in the tooltip; GraphQL calls show their operation name.
     *
     * @param string $path      Request path, e.g. /repos/guibranco/pancake/pulls.
     * @param string $query     Query string without the "?".
     * @param string $operation GraphQL operation name, if any.
     *
     * @return string
     */
    private function url(string $path, string $query, string $operation): string
    {
        $url = $path;
        if ($query !== '') {
            $url .= '?'.$query;
        }

        $shown = $url;
        if (mb_strlen($url) > self::URL_LENGTH) {
            $shown = mb_substr($url, 0, (self::URL_LENGTH - 1)).'…';
        }

        $cell = '<code title="'.$this->cells->escape($url).'">'.$this->cells->escape($shown).'</code>';
        if ($operation !== '') {
            $cell .= ' <span class="text-white-50 small">'.$this->cells->escape($operation).'</span>';
        }

        return $cell;

    }//end url()


    /**
     * Response status as a badge coloured by class; a proxy error (no
     * response from GitHub) is shown in its tooltip.
     *
     * @param integer $status HTTP status, 0 when GitHub was not reached.
     * @param string  $error  Proxy error message, if any.
     *
     * @return string
     */
    private function statusBadge(int $status, string $error): string
    {
        $class = (self::STATUS_CLASSES[intdiv($status, 100)] ?? 'text-bg-danger');
        $label = 'error';
        if ($status > 0) {
            $label = (string) $status;
        }

        $title = '';
        if ($error !== '') {
            $title = ' title="'.$this->cells->escape($error).'"';
        }

        return '<span class="badge '.$class.'"'.$title.'>'.$label.'</span>';

    }//end statusBadge()


    /**
     * Response size in B, KB, MB or GB, e.g. "47.1 KB".
     *
     * @param integer $bytes Response size in bytes.
     *
     * @return string
     */
    private function size(int $bytes): string
    {
        $value = (float) max(0, $bytes);
        $unit  = 0;
        while ($value >= 1024 && $unit < (count(self::SIZE_UNITS) - 1)) {
            $value /= 1024;
            $unit++;
        }

        $decimals = 1;
        if ($unit === 0) {
            $decimals = 0;
        }

        // A non-breaking space keeps the number and unit on one line.
        return number_format($value, $decimals)."\u{a0}".self::SIZE_UNITS[$unit];

    }//end size()


    /**
     * Converts an ISO 8601 timestamp to the display time zone.
     *
     * @param string $value Timestamp, e.g. 2026-11-19T17:02:00.123Z.
     *
     * @return string The formatted time, or "—" when missing or unparseable.
     */
    private function formatTime(string $value): string
    {
        if ($value === '') {
            return '—';
        }

        try {
            $date = new DateTimeImmutable($value);
        } catch (Exception $exception) {
            return '—';
        }

        return $date->setTimezone($this->zone)->format('d/m/Y H:i:s');

    }//end formatTime()


}//end class
