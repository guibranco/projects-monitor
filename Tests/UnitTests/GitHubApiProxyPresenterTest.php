<?php

use GuiBranco\ProjectsMonitor\Library\GitHubApiProxyPresenter;
use GuiBranco\ProjectsMonitor\Library\GitHubApiProxyRange;
use PHPUnit\Framework\TestCase;

/**
 * Tests the rendering of the github-api-proxy stats API for the dashboard:
 * summary and health for the caption, the 24-point hourly timeline with
 * gaps filled, the callers and rate-limits tables, and safe output for
 * every caller label the proxy produces.
 */
class GitHubApiProxyPresenterTest extends TestCase
{


    /**
     * Presenter showing times in São Paulo (UTC-3, no daylight saving time),
     * with "now" fixed at 2026-11-18 17:30 UTC.
     *
     * @return GitHubApiProxyPresenter
     */
    private function presenter(): GitHubApiProxyPresenter
    {
        return new GitHubApiProxyPresenter(
            new DateTimeZone('America/Sao_Paulo'),
            new DateTimeImmutable('2026-11-18T17:30:00Z')
        );

    }//end presenter()


    /**
     * Summary data exactly as GET api/v1/summary returns it.
     *
     * @return array
     */
    private function summary(): array
    {
        return [
                'requests'          => 1240,
                'client_errors'     => 10,
                'server_errors'     => 2,
                'upstream_failures' => 1,
                'rate_limited'      => 3,
                'blocked'           => 4,
                'avg_duration_ms'   => 182.4,
                'last_request_at'   => '2026-11-18T17:20:00.5Z',
               ];

    }//end summary()


    /**
     * Health body exactly as GET /health returns it.
     *
     * @return array
     */
    private function health(): array
    {
        return [
                'status'         => 'ok',
                'version'        => '0.2.0',
                'database'       => 'ok',
                'uptime_seconds' => 3600,
                'writer'         => [
                                     'queued'              => 0,
                                     'dropped_since_start' => 0,
                                     'failing'             => false,
                                    ],
               ];

    }//end health()


    /**
     * Calls present() with the given callers and rate limits.
     *
     * @param array $callers    Callers data.
     * @param array $rateLimits Rate-limits data.
     * @param array       $timeline   Timeline data.
     * @param string|null $range      Range key; null for the default.
     *
     * @return array
     */
    private function present(array $callers=[], array $rateLimits=[], array $timeline=[], ?string $range=null): array
    {
        $rangeObject = ($range === null) ? null : new GitHubApiProxyRange($range);

        return $this->presenter()->present($this->summary(), $timeline, $callers, $rateLimits, $this->health(), $rangeObject);

    }//end present()


    /**
     * Summary and health are passed through for the caption.
     *
     * @return void
     */
    public function testSummaryAndHealth(): void
    {
        $caller = [
                   'caller'   => 'guibranco',
                   'requests' => 5,
                  ];
        $result = $this->present([$caller]);

        $this->assertSame(1240, $result['summary']['requests']);
        $this->assertSame(12, $result['summary']['errors']);
        $this->assertSame(2, $result['summary']['serverErrors']);
        $this->assertSame(1, $result['summary']['upstreamFailures']);
        $this->assertSame(3, $result['summary']['rateLimited']);
        $this->assertSame(4, $result['summary']['blocked']);
        $this->assertSame(182.4, $result['summary']['avgDurationMs']);
        $this->assertSame(1, $result['summary']['callers']);
        $this->assertSame('18/11/2026 14:20:00', $result['summary']['lastRequestAt']);
        $this->assertSame('ok', $result['health']['status']);
        $this->assertSame('0.2.0', $result['health']['version']);
        $this->assertSame('ok', $result['health']['database']);
        $this->assertSame(0, $result['health']['dropped']);

    }//end testSummaryAndHealth()


    /**
     * The timeline always has 24 hourly points ending at the current hour,
     * with hours missing from the API filled with zeros.
     *
     * @return void
     */
    public function testTimelineIsFilledToTwentyFourHours(): void
    {
        $timeline = [
                     [
                      'bucket'   => '2026-11-18T17:00:00Z',
                      'requests' => 40,
                      'errors'   => 2,
                     ],
                     [
                      'bucket'   => '2026-11-17T18:00:00Z',
                      'requests' => 7,
                      'errors'   => 0,
                     ],
                     [
                      'bucket'   => '2026-11-16T10:00:00Z',
                      'requests' => 99,
                      'errors'   => 9,
                     ],
                    ];
        $rows     = $this->present([], [], $timeline)['timeline'];

        $this->assertSame(GitHubApiProxyPresenter::TIMELINE_HEADER, $rows[0]);
        $this->assertCount(25, $rows);
        // Oldest point: 2026-11-17 18:00 UTC = 15:00 in São Paulo.
        $this->assertSame(['15:00', 7, 0], $rows[1]);
        // Newest point: the current hour, 17:00 UTC = 14:00 in São Paulo.
        $this->assertSame(['14:00', 40, 2], $rows[24]);
        // An hour with no traffic.
        $this->assertSame(['16:00', 0, 0], $rows[2]);
        // The bucket older than 24 hours is ignored.
        $this->assertSame(47, array_sum(array_column(array_slice($rows, 1), 1)));

    }//end testTimelineIsFilledToTwentyFourHours()


    /**
     * The payload names the range it covers; the default is 24 hours.
     *
     * @return void
     */
    public function testRangeIsReported(): void
    {
        $this->assertSame(['key' => '24h', 'label' => 'last 24 hours'], $this->present()['range']);
        $this->assertSame(['key' => '7d', 'label' => 'last 7 days'], $this->present([], [], [], '7d')['range']);

    }//end testRangeIsReported()


    /**
     * The 1-hour range has 60 minute points, ending at the current minute.
     *
     * @return void
     */
    public function testOneHourTimelineIsPerMinute(): void
    {
        $timeline = [
                     [
                      'bucket'   => '2026-11-18T17:30:00Z',
                      'requests' => 5,
                      'errors'   => 1,
                     ],
                     [
                      'bucket'   => '2026-11-18T16:31:00Z',
                      'requests' => 2,
                      'errors'   => 0,
                     ],
                    ];
        $rows     = $this->present([], [], $timeline, '1h')['timeline'];

        $this->assertSame(['Minute', 'Requests', 'Errors'], $rows[0]);
        $this->assertCount(61, $rows);
        $this->assertSame(['13:31', 2, 0], $rows[1]);
        $this->assertSame(['14:30', 5, 1], $rows[60]);

    }//end testOneHourTimelineIsPerMinute()


    /**
     * The 7-day range is hourly with the date in each label.
     *
     * @return void
     */
    public function testSevenDayTimelineIsHourlyWithDates(): void
    {
        $timeline = [
                     [
                      'bucket'   => '2026-11-12T00:00:00Z',
                      'requests' => 3,
                      'errors'   => 0,
                     ],
                    ];
        $rows     = $this->present([], [], $timeline, '7d')['timeline'];

        $this->assertSame('Hour', $rows[0][0]);
        $this->assertCount(169, $rows);
        // Oldest point: 2026-11-11 18:00 UTC = 15:00 in São Paulo.
        $this->assertSame('11/11 15:00', $rows[1][0]);
        $this->assertSame('18/11 14:00', $rows[168][0]);
        $this->assertSame(3, array_sum(array_column(array_slice($rows, 1), 1)));

    }//end testSevenDayTimelineIsHourlyWithDates()


    /**
     * The 30-day range has one point per UTC day, ending today.
     *
     * @return void
     */
    public function testThirtyDayTimelineIsDaily(): void
    {
        $timeline = [
                     [
                      'bucket'   => '2026-11-18T00:00:00Z',
                      'requests' => 10,
                      'errors'   => 2,
                     ],
                     [
                      'bucket'   => '2026-10-20T00:00:00Z',
                      'requests' => 4,
                      'errors'   => 0,
                     ],
                    ];
        $rows     = $this->present([], [], $timeline, '30d')['timeline'];

        $this->assertSame('Day', $rows[0][0]);
        $this->assertCount(31, $rows);
        $this->assertSame(['20/10', 4, 0], $rows[1]);
        $this->assertSame(['18/11', 10, 2], $rows[30]);

    }//end testThirtyDayTimelineIsDaily()


    /**
     * Every caller label the proxy produces gets its own rendering.
     *
     * @return void
     */
    public function testCallerLabels(): void
    {
        $callers = [
                    [
                     'caller'          => 'guibranco',
                     'token_kinds'     => ['pat'],
                     'requests'        => 1000,
                     'errors'          => 3,
                     'avg_duration_ms' => 120.26,
                     'last_seen_at'    => '2026-11-18T17:00:00Z',
                     'user_agents'     => [
                                           'projects-monitor/2.0',
                                           'curl/8.5',
                                          ],
                     'client_ips'      => ['198.51.100.30'],
                    ],
                    [
                     'caller'      => 'installation:ApiBR',
                     'token_kinds' => ['app_installation'],
                    ],
                    [
                     'caller'      => 'app:123456',
                     'token_kinds' => ['app_jwt'],
                    ],
                    ['caller' => 'token:0123456789ab'],
                    ['caller' => 'invalid:ba9876543210'],
                    [
                     'caller'      => 'anonymous',
                     'token_kinds' => ['none'],
                    ],
                   ];
        $rows    = $this->present($callers)['callers'];

        $this->assertSame(GitHubApiProxyPresenter::CALLERS_HEADER, $rows[0]);
        $this->assertCount(7, $rows);

        $this->assertStringContainsString('href="https://github.com/guibranco"', $rows[1][0]);
        $this->assertStringContainsString('pat', $rows[1][0]);
        $this->assertStringContainsString('projects-monitor/2.0', $rows[1][1]);
        $this->assertStringContainsString('+1', $rows[1][1]);
        $this->assertStringContainsString('198.51.100.30', $rows[1][1]);
        $this->assertSame('1,000', $rows[1][2]);
        $this->assertStringContainsString('<img', $rows[1][3]);
        $this->assertStringContainsString('red', $rows[1][3]);
        $this->assertSame('120.3', $rows[1][4]);
        $this->assertSame('18/11/2026 14:00:00', $rows[1][5]);

        $this->assertStringContainsString('href="https://github.com/ApiBR"', $rows[2][0]);
        $this->assertStringContainsString('app installation', $rows[2][0]);
        $this->assertStringContainsString('GitHub App #123456', $rows[3][0]);
        $this->assertStringContainsString('resolving', $rows[4][0]);
        $this->assertStringContainsString('invalid token', $rows[5][0]);
        $this->assertSame('anonymous', $rows[6][0]);
        $this->assertSame('—', $rows[6][1]);
        $this->assertSame('—', $rows[6][5]);

    }//end testCallerLabels()


    /**
     * Every callers row gets a type for the table's filter chips, in row order.
     *
     * @return void
     */
    public function testCallerTypes(): void
    {
        $labels  = [
                    'guibranco',
                    'dependabot[bot]',
                    'installation:ApiBR',
                    'app:123456',
                    'token:0123456789ab',
                    'invalid:ba9876543210',
                    'anonymous',
                    '',
                    'app',
                    'token',
                    'installation',
                   ];
        $callers = array_map(fn(string $label): array => ['caller' => $label], $labels);
        $result  = $this->present($callers);

        $this->assertSame(
            [
             'user',
             'bot',
             'installation',
             'app',
             'token',
             'invalid',
             'anonymous',
             'unknown',
             'user',
             'user',
             'user',
            ],
            $result['callerTypes']
        );
        $this->assertCount((count($result['callers']) - 1), $result['callerTypes']);

    }//end testCallerTypes()


    /**
     * Caller labels and user agents are HTML-escaped.
     *
     * @return void
     */
    public function testOutputIsEscaped(): void
    {
        $rows = $this->present(
            [
             [
              'caller'      => '<script>alert(1)</script>',
              'user_agents' => ['"><img src=x onerror=alert(1)>'],
             ],
            ]
        )['callers'];

        $this->assertStringNotContainsString('<script>', $rows[1][0]);
        $this->assertStringNotContainsString('<img src=x', $rows[1][1]);

    }//end testOutputIsEscaped()


    /**
     * Rate limits show the used percentage as a coloured badge, the remaining
     * quota and the reset time in the dashboard time zone.
     *
     * @return void
     */
    public function testRateLimits(): void
    {
        $limits = [
                   [
                    'caller'       => 'guibranco',
                    'token_kind'   => 'pat',
                    'resource'     => 'core',
                    'limit'        => 5000,
                    'remaining'    => 100,
                    'used_percent' => 98.0,
                    'reset_at'     => '2026-11-18T18:00:00Z',
                   ],
                   [
                    'caller'       => 'installation:ApiBR',
                    'token_kind'   => 'app_installation',
                    'resource'     => 'graphql',
                    'limit'        => 5000,
                    'remaining'    => 4900,
                    'used_percent' => 2.0,
                    'reset_at'     => 'not a date',
                   ],
                  ];
        $rows   = $this->present([], $limits)['rateLimits'];

        $this->assertSame(GitHubApiProxyPresenter::RATE_LIMITS_HEADER, $rows[0]);
        $this->assertSame('core', $rows[1][1]);
        $this->assertStringContainsString('red', $rows[1][2]);
        $this->assertStringContainsString('98.0', $rows[1][2]);
        $this->assertSame('100 / 5,000', $rows[1][3]);
        $this->assertSame('15:00:00', $rows[1][4]);
        $this->assertStringContainsString('green', $rows[2][2]);
        $this->assertSame('—', $rows[2][4]);

    }//end testRateLimits()


    /**
     * Malformed entries are skipped rather than breaking the tables.
     *
     * @return void
     */
    public function testMalformedEntriesAreSkipped(): void
    {
        $result = $this->presenter()->present([], ['oops', ['bucket' => 'nope']], ['oops'], [42], []);

        $this->assertCount(1, $result['callers']);
        $this->assertSame([], $result['callerTypes']);
        $this->assertCount(1, $result['rateLimits']);
        $this->assertCount(25, $result['timeline']);
        $this->assertSame(0, $result['summary']['requests']);
        $this->assertSame('unknown', $result['health']['status']);

    }//end testMalformedEntriesAreSkipped()


}//end class
