<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

/**
 * The time ranges the dashboard can show for the github-api-proxy stats,
 * each with the timeline bucket and number of points that suit it. The
 * proxy accepts periods up to 90 days, but its retention job
 * (Storage:RetentionDays, 30 by default) deletes older rows, so the widest
 * range shows whatever is still kept.
 */
class GitHubApiProxyRange
{

    public const DEFAULT = '24h';

    /**
     * Range key => stats API period, timeline bucket, points and label.
     */
    private const RANGES = [
                            '1h'  => [
                                      'period' => '1h',
                                      'bucket' => 'minute',
                                      'points' => 60,
                                      'label'  => 'last hour',
                                     ],
                            '24h' => [
                                      'period' => '24h',
                                      'bucket' => 'hour',
                                      'points' => 24,
                                      'label'  => 'last 24 hours',
                                     ],
                            '7d'  => [
                                      'period' => '7d',
                                      'bucket' => 'hour',
                                      'points' => 168,
                                      'label'  => 'last 7 days',
                                     ],
                            '30d' => [
                                      'period' => '30d',
                                      'bucket' => 'day',
                                      'points' => 30,
                                      'label'  => 'last 30 days',
                                     ],
                            '90d' => [
                                      'period' => '90d',
                                      'bucket' => 'day',
                                      'points' => 90,
                                      'label'  => 'last 90 days',
                                     ],
                           ];

    /**
     * Selected range key.
     *
     * @var string
     */
    private $key;


    /**
     * @param string|null $key A range key; anything unknown falls back to the default.
     */
    public function __construct(?string $key=null)
    {
        $key       = trim((string) $key);
        $this->key = (isset(self::RANGES[$key]) === true) ? $key : self::DEFAULT;

    }//end __construct()


    /**
     * Every range key, in display order.
     *
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys(self::RANGES);

    }//end keys()


    /**
     * @return string
     */
    public function key(): string
    {
        return $this->key;

    }//end key()


    /**
     * The stats API `period` parameter.
     *
     * @return string
     */
    public function period(): string
    {
        return self::RANGES[$this->key]['period'];

    }//end period()


    /**
     * The timeline `bucket` parameter: minute, hour or day.
     *
     * @return string
     */
    public function bucket(): string
    {
        return self::RANGES[$this->key]['bucket'];

    }//end bucket()


    /**
     * Number of points on the timeline.
     *
     * @return integer
     */
    public function points(): int
    {
        return self::RANGES[$this->key]['points'];

    }//end points()


    /**
     * Human label, e.g. "last 7 days".
     *
     * @return string
     */
    public function label(): string
    {
        return self::RANGES[$this->key]['label'];

    }//end label()


}//end class
