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
                     '1h'  => ['1h', 'minute', 60],
                     '24h' => ['24h', 'hour', 24],
                     '7d'  => ['7d', 'hour', 168],
                     '30d' => ['30d', 'day', 30],
                     '90d' => ['90d', 'day', 90],
                    ];

        $this->assertSame(array_keys($expected), GitHubApiProxyRange::keys());
        foreach ($expected as $key => [$period, $bucket, $points]) {
            $range = new GitHubApiProxyRange($key);
            $this->assertSame($key, $range->key());
            $this->assertSame($period, $range->period());
            $this->assertSame($bucket, $range->bucket());
            $this->assertSame($points, $range->points());
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
