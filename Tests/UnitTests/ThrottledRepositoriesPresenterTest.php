<?php

use GuiBranco\ProjectsMonitor\Library\ThrottledRepositoriesPresenter;
use PHPUnit\Framework\TestCase;

/**
 * Tests the Throttled Repositories table rendering from the WebhooksHandler's
 * GET /broker/throttle body: settings passed through, one row per throttled
 * repository in handler order, totals, and safe output for odd entries.
 */
class ThrottledRepositoriesPresenterTest extends TestCase
{


    /**
     * Builds one throttle response exactly as the WebhooksHandler returns it.
     *
     * @return array
     */
    private function getHandlerResponse(): array
    {
        return [
                'enabled'          => true,
                'permitsPerMinute' => 60,
                'burstSize'        => 120,
                'drainPerMinute'   => 30,
                'repositories'     => [
                                       [
                                        'installationId'  => 111,
                                        'repositoryId'    => 222,
                                        'repositoryName'  => 'guibranco/projects-monitor',
                                        'throttled'       => 400,
                                        'lastThrottledAt' => '2026-11-10T22:15:03.1234567+00:00',
                                       ],
                                       [
                                        'installationId'  => 333,
                                        'repositoryId'    => 444,
                                        'repositoryName'  => 'guibranco/webhooks',
                                        'throttled'       => 18,
                                        'lastThrottledAt' => '2026-11-10T21:00:00+00:00',
                                       ],
                                      ],
               ];

    }//end getHandlerResponse()


    /**
     * Presenter showing times in São Paulo (UTC-3, no daylight saving time).
     *
     * @return ThrottledRepositoriesPresenter
     */
    private function presenter(): ThrottledRepositoriesPresenter
    {
        return new ThrottledRepositoriesPresenter(new DateTimeZone('America/Sao_Paulo'));

    }//end presenter()


    /**
     * Header row first, then one row per repository, in handler order.
     *
     * @return void
     */
    public function testPresentRendersHeaderAndOneRowPerRepository(): void
    {
        $result = $this->presenter()->present($this->getHandlerResponse());

        $this->assertSame(ThrottledRepositoriesPresenter::TABLE_HEADER, $result['repositories'][0]);
        $this->assertCount(3, $result['repositories']);
        $this->assertStringContainsString('guibranco/projects-monitor', $result['repositories'][1][0]);
        $this->assertStringContainsString('guibranco/webhooks', $result['repositories'][2][0]);
        $this->assertSame(2, $result['total']);

    }//end testPresentRendersHeaderAndOneRowPerRepository()


    /**
     * Settings are passed through and the throttled count is summed.
     *
     * @return void
     */
    public function testPresentPassesSettingsAndSumsThrottled(): void
    {
        $result = $this->presenter()->present($this->getHandlerResponse());

        $this->assertTrue($result['enabled']);
        $this->assertSame(60, $result['permitsPerMinute']);
        $this->assertSame(120, $result['burstSize']);
        $this->assertSame(30, $result['drainPerMinute']);
        $this->assertSame(418, $result['throttled']);

    }//end testPresentPassesSettingsAndSumsThrottled()


    /**
     * A row links the repository, shows the installation, a badge and the
     * last throttled time in the display time zone.
     *
     * @return void
     */
    public function testPresentRendersRowCells(): void
    {
        $row = $this->presenter()->present($this->getHandlerResponse())['repositories'][1];

        $this->assertStringContainsString('href="https://github.com/guibranco/projects-monitor"', $row[0]);
        $this->assertStringContainsString('rel="noopener noreferrer"', $row[0]);
        $this->assertSame('111', $row[1]);
        $this->assertStringContainsString('400 payloads sent to the Throttled Queue', $row[2]);
        $this->assertSame('10/11/2026 19:15:03', $row[3]);

    }//end testPresentRendersRowCells()


    /**
     * A repository without a name falls back to its id, without a link, and a
     * missing installation or time shows a dash.
     *
     * @return void
     */
    public function testPresentFallsBackForMissingFields(): void
    {
        $status = [
                   'enabled'      => true,
                   'repositories' => [
                                      [
                                       'installationId' => 0,
                                       'repositoryId'   => 555,
                                       'repositoryName' => null,
                                       'throttled'      => 1,
                                      ],
                                     ],
                  ];

        $row = $this->presenter()->present($status)['repositories'][1];

        $this->assertSame('#555', $row[0]);
        $this->assertSame('—', $row[1]);
        $this->assertSame('—', $row[3]);

    }//end testPresentFallsBackForMissingFields()


    /**
     * Repository names are escaped and an unparseable time shows a dash.
     *
     * @return void
     */
    public function testPresentEscapesNameAndToleratesBadDate(): void
    {
        $status = [
                   'repositories' => [
                                      [
                                       'installationId'  => 1,
                                       'repositoryId'    => 2,
                                       'repositoryName'  => 'evil/<script>',
                                       'throttled'       => 1,
                                       'lastThrottledAt' => 'not a date',
                                      ],
                                     ],
                  ];

        $row = $this->presenter()->present($status)['repositories'][1];

        $this->assertStringNotContainsString('<script>', $row[0]);
        $this->assertStringContainsString('&lt;script&gt;', $row[0]);
        $this->assertSame('—', $row[3]);

    }//end testPresentEscapesNameAndToleratesBadDate()


    /**
     * No throttled repositories yields a header-only table and zero totals;
     * a missing `enabled` reads as off.
     *
     * @return void
     */
    public function testPresentWithNoRepositoriesReturnsEmptyTotals(): void
    {
        $result = $this->presenter()->present(['repositories' => []]);

        $this->assertCount(1, $result['repositories']);
        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['throttled']);
        $this->assertFalse($result['enabled']);

    }//end testPresentWithNoRepositoriesReturnsEmptyTotals()


}//end class
