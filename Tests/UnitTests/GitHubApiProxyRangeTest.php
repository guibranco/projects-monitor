<?php

use GuiBranco\ProjectsMonitor\Library\GitHubApiProxyRange;
use PHPUnit\Framework\TestCase;

/**
 * Tests the time ranges the dashboard offers for the github-api-proxy stats.
 */
class GitHubApiProxyRangeTest extends TestCase
{


    /**
     * Each range maps to a stats API period and a timeline bucket.
     *
     * @return void
     */
    public function testRangesMapToPeriodAndBucket(): void
    {
        $expected = [
                     '1h'  => [
                               'period' => '1h',
                               'bucket' => 'minute',
                               'points' => 60,
                              ],
                     '24h' => [
                               'period' => '24h',
                               'bucket' => 'hour',
                               'points' => 24,
                              ],
                     '7d'  => [
                               'period' => '7d',
                               'bucket' => 'hour',
                               'points' => 168,
                              ],
                     '30d' => [
                               'period' => '30d',
                               'bucket' => 'day',
                               'points' => 30,
                              ],
                     '90d' => [
                               'period' => '90d',
                               'bucket' => 'day',
                               'points' => 90,
                              ],
                    ];

        foreach ($expected as $key => $values) {
            $range = new GitHubApiProxyRange($key);
            $this->assertSame($key, $range->key());
            $this->assertSame($values['period'], $range->period());
            $this->assertSame($values['bucket'], $range->bucket());
            $this->assertSame($values['points'], $range->points());
        }

    }//end testRangesMapToPeriodAndBucket()


    /**
     * Missing or unknown ranges fall back to the last 24 hours.
     *
     * @return void
     */
    public function testUnknownRangeFallsBackToDefault(): void
    {
        foreach ([null, '', '1y', '24H', '7d&bucket=minute'] as $key) {
            $range = new GitHubApiProxyRange($key);
            $this->assertSame('24h', $range->key());
            $this->assertSame('last 24 hours', $range->label());
        }

    }//end testUnknownRangeFallsBackToDefault()


}//end class
