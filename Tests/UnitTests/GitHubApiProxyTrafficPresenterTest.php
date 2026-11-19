<?php

namespace GuiBranco\ProjectsMonitor\Tests\UnitTests;

use DateTimeZone;
use GuiBranco\ProjectsMonitor\Library\GitHubApiProxyTrafficPresenter;
use PHPUnit\Framework\TestCase;

/**
 * Routes and requests tables of the GitHub API proxy section.
 */
final class GitHubApiProxyTrafficPresenterTest extends TestCase
{


    /**
     * Presents the given routes and requests in Europe/Lisbon (UTC in November).
     *
     * @param array $routes   Routes data.
     * @param array $requests Requests data.
     *
     * @return array
     */
    private function present(array $routes, array $requests): array
    {
        $presenter = new GitHubApiProxyTrafficPresenter(new DateTimeZone('Europe/Lisbon'));

        return $presenter->present($routes, $requests);

    }//end present()


    /**
     * One request with the given fields over a plain GET.
     *
     * @param array $fields Fields to set.
     *
     * @return array Columns of the single requests row.
     */
    private function requestRow(array $fields): array
    {
        $request = array_merge(
            [
             'requested_at' => '2026-11-19T17:02:03.456Z',
             'caller'       => 'guibranco',
             'token_kind'   => 'pat',
             'method'       => 'GET',
             'path'         => '/repos/guibranco/pancake',
             'status'       => 200,
             'duration_ms'  => 123,
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
     * The request time is shown in the display time zone.
     *
     * @return void
     */
    public function testRequestTimeUsesTheDisplayZone(): void
    {
        $this->assertSame('19/11/2026 17:02:03', $this->requestRow([])[0]);
        $this->assertSame('—', $this->requestRow(['requested_at' => 'not a date'])[0]);

    }//end testRequestTimeUsesTheDisplayZone()


    /**
     * The URL column has the path and query string; long URLs are cut with
     * the full URL in the tooltip; GraphQL calls show their operation.
     *
     * @return void
     */
    public function testUrlColumn(): void
    {
        $this->assertSame(
            '<code title="/repos/guibranco/pancake?per_page=100&amp;page=2">/repos/guibranco/pancake?per_page=100&amp;page=2</code>',
            $this->requestRow(['query' => 'per_page=100&page=2'])[3]
        );

        $long = '/repos/guibranco/projects-monitor/contents/'.str_repeat('a', 100);
        $cell = $this->requestRow(['path' => $long])[3];
        $this->assertStringContainsString('title="'.$long.'"', $cell);
        $this->assertStringContainsString('…</code>', $cell);
        $this->assertStringNotContainsString('>'.$long.'<', $cell);

        $graphql = $this->requestRow(
            [
             'method'            => 'POST',
             'path'              => '/graphql',
             'graphql_operation' => 'ViewerRepos',
            ]
        );
        $this->assertSame('<span class="badge text-bg-success">POST</span>', $graphql[2]);
        $this->assertStringContainsString('ViewerRepos', $graphql[3]);

    }//end testUrlColumn()


    /**
     * Status badges are coloured by class; a proxy error shows its message.
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
            '<span class="badge text-bg-danger" title="dial tcp: i/o timeout">error</span>',
            $this->requestRow(['status' => 0, 'error' => 'dial tcp: i/o timeout'])[4]
        );

    }//end testStatusBadges()


    /**
     * Response sizes use the largest fitting unit.
     *
     * @return void
     */
    public function testSizes(): void
    {
        $this->assertSame("0\u{a0}B", $this->requestRow([])[6]);
        $this->assertSame("512\u{a0}B", $this->requestRow(['response_bytes' => 512])[6]);
        $this->assertSame("1.5\u{a0}KB", $this->requestRow(['response_bytes' => 1536])[6]);
        $this->assertSame("2.0\u{a0}MB", $this->requestRow(['response_bytes' => 2097152])[6]);

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
             'path'              => '/'.$xss,
             'query'             => 'q="><img src=x>',
             'graphql_operation' => $xss,
             'status'            => 0,
             'error'             => '"><script>',
            ]
        );
        $html = implode('', $row);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('"><', $html);

    }//end testValuesAreEscaped()


}//end class
