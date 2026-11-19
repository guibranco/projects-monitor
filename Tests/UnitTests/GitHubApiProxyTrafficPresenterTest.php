<?php

namespace GuiBranco\ProjectsMonitor\Tests\UnitTests;

use DateTimeZone;
use GuiBranco\ProjectsMonitor\Library\GitHubApiProxyTrafficPresenter;
use PHPUnit\Framework\TestCase;

/**
 * Routes and requests (route usage) tables of the GitHub API proxy section.
 */
final class GitHubApiProxyTrafficPresenterTest extends TestCase
{


    /**
     * Presents the given routes and requests in Europe/Lisbon (UTC in November).
     *
     * @param array $routes   Routes data.
     * @param array $requests Route usage data.
     *
     * @return array
     */
    private function present(array $routes, array $requests): array
    {
        $presenter = new GitHubApiProxyTrafficPresenter(new DateTimeZone('Europe/Lisbon'));

        return $presenter->present($routes, $requests);

    }//end present()


    /**
     * One route-usage entry with the given fields over a plain GET.
     *
     * @param array $fields Fields to set.
     *
     * @return array Columns of the single requests row.
     */
    private function requestRow(array $fields): array
    {
        $request = array_merge(
            [
             'caller'            => 'guibranco',
             'token_kind'        => 'pat',
             'token_fingerprint' => '3f2a9c1b7d0e',
             'method'            => 'GET',
             'route'             => '/repos/{owner}/{repo}/pulls',
             'status'            => 200,
             'requests'          => 4321,
             'last_request_at'   => '2026-11-19T17:02:03.456Z',
             'avg_duration_ms'   => 123.45,
            ],
            $fields
        );

        return $this->present([], [$request])['requests'][1];

    }//end requestRow()


    /**
     * Both tables start with their header and keep one row per entry.
     *
     * @return void
     */
    public function testHeadersAndRows(): void
    {
        $route  = [
                   'method'           => 'GET',
                   'route'            => '/repos/{owner}/{repo}/pulls',
                   'requests'         => 1234,
                   'errors'           => 0,
                   'avg_duration_ms'  => 81.26,
                   'max_duration_ms'  => 1500,
                   'distinct_callers' => 3,
                  ];
        $result = $this->present([$route], [['method' => 'GET']]);

        $this->assertSame(GitHubApiProxyTrafficPresenter::ROUTES_HEADER, $result['routes'][0]);
        $this->assertSame(GitHubApiProxyTrafficPresenter::REQUESTS_HEADER, $result['requests'][0]);
        $this->assertCount(2, $result['routes']);
        $this->assertCount(2, $result['requests']);

        $row = $result['routes'][1];
        $this->assertSame('<span class="badge text-bg-primary">GET</span>', $row[0]);
        $this->assertSame('<code>/repos/{owner}/{repo}/pulls</code>', $row[1]);
        $this->assertSame('1,234', $row[2]);
        $this->assertSame('81.3', $row[4]);
        $this->assertSame('1,500', $row[5]);
        $this->assertSame('3', $row[6]);

    }//end testHeadersAndRows()


    /**
     * Entries that are not objects are skipped.
     *
     * @return void
     */
    public function testMalformedEntriesAreSkipped(): void
    {
        $result = $this->present(['x', 1], ['x', null]);

        $this->assertCount(1, $result['routes']);
        $this->assertCount(1, $result['requests']);

    }//end testMalformedEntriesAreSkipped()


    /**
     * Each row is one token's calls to one route with one status: count,
     * last request time (display time zone) and average duration.
     *
     * @return void
     */
    public function testRouteUsageColumns(): void
    {
        $row = $this->requestRow([]);

        $this->assertStringContainsString('guibranco', $row[0]);
        $this->assertSame('<code>3f2a9c1b7d0e</code>', $row[1]);
        $this->assertSame('<span class="badge text-bg-primary">GET</span>', $row[2]);
        $this->assertSame('<code>/repos/{owner}/{repo}/pulls</code>', $row[3]);
        $this->assertSame('4,321', $row[5]);
        $this->assertSame('19/11/2026 17:02:03', $row[6]);
        $this->assertSame('123.5', $row[7]);

    }//end testRouteUsageColumns()


    /**
     * Requests without a token have no fingerprint; a bad last time is "—".
     *
     * @return void
     */
    public function testMissingTokenAndTime(): void
    {
        $row = $this->requestRow(
            [
             'caller'            => 'anonymous',
             'token_fingerprint' => null,
             'last_request_at'   => 'not a date',
            ]
        );

        $this->assertSame('anonymous', $row[0]);
        $this->assertSame('—', $row[1]);
        $this->assertSame('—', $row[6]);

    }//end testMissingTokenAndTime()


    /**
     * Status badges are coloured by class; 0 (GitHub not reached) is an error.
     *
     * @return void
     */
    public function testStatusBadges(): void
    {
        $this->assertSame('<span class="badge text-bg-success">200</span>', $this->requestRow(['status' => 200])[4]);
        $this->assertSame('<span class="badge text-bg-info">304</span>', $this->requestRow(['status' => 304])[4]);
        $this->assertSame('<span class="badge text-bg-warning">404</span>', $this->requestRow(['status' => 404])[4]);
        $this->assertSame('<span class="badge text-bg-danger">502</span>', $this->requestRow(['status' => 502])[4]);
        $this->assertSame(
            '<span class="badge text-bg-danger">error</span>',
            $this->requestRow(['status' => 0])[4]
        );

    }//end testStatusBadges()


    /**
     * Total response sizes use the largest fitting unit.
     *
     * @return void
     */
    public function testSizes(): void
    {
        $this->assertSame("0\u{a0}B", $this->requestRow([])[8]);
        $this->assertSame("512\u{a0}B", $this->requestRow(['response_bytes' => 512])[8]);
        $this->assertSame("1.5\u{a0}KB", $this->requestRow(['response_bytes' => 1536])[8]);
        $this->assertSame("2.0\u{a0}MB", $this->requestRow(['response_bytes' => 2097152])[8]);

    }//end testSizes()


    /**
     * Values from the proxy are HTML-escaped in every column.
     *
     * @return void
     */
    public function testValuesAreEscaped(): void
    {
        $xss    = '<script>alert(1)</script>';
        $route  = [
                   'method' => '<b>',
                   'route'  => $xss,
                  ];
        $result = $this->present([$route], []);
        $this->assertStringNotContainsString('<script>', implode('', $result['routes'][1]));
        $this->assertStringNotContainsString('<b>', implode('', $result['routes'][1]));

        $row = $this->requestRow(
            [
             'caller'            => 'token:"><img src=x>',
             'token_fingerprint' => '"><script>',
             'method'            => $xss,
             'route'             => '/'.$xss,
             'last_request_at'   => $xss,
            ]
        );
        $html = implode('', $row);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('"><', $html);

    }//end testValuesAreEscaped()


}//end class
