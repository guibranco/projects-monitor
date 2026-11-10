<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use GuiBranco\Pancake\ShieldsIo;

/**
 * Renders the WebhooksHandler's GET /broker/throttle response as the
 * Throttled Repositories table (header row first, one row per installation
 * and repository the per-repository throttle has sent to the Throttled Queue
 * since the handler started) plus the throttle settings for the caption.
 * The handler keeps these counters in memory, so they reset on restart.
 * Pure formatting, no HTTP, so it is unit-testable.
 */
class ThrottledRepositoriesPresenter
{

    public const TABLE_HEADER = [
                                 'Repository',
                                 'Installation',
                                 'Throttled',
                                 'Last throttled',
                                ];

    /**
     * Display format of the last throttled time, in the dashboard's time zone.
     */
    private const DATE_FORMAT = 'd/m/Y H:i:s';

    /**
     * Time zone the last throttled time is shown in.
     *
     * @var DateTimeZone
     */
    private $zone;


    /**
     * @param DateTimeZone|null $zone Display time zone; defaults to date.timezone,
     *                                which Configuration sets from the browser's cookie.
     */
    public function __construct(?DateTimeZone $zone=null)
    {
        $this->zone = ($zone ?? new DateTimeZone(date_default_timezone_get()));

    }//end __construct()


    /**
     * Builds the dashboard payload from the handler's throttle response.
     *
     * @param array $status Decoded GET /broker/throttle body.
     *
     * @return array Keys: enabled, permitsPerMinute, burstSize, drainPerMinute, repositories, total, throttled.
     */
    public function present(array $status): array
    {
        $shields   = new ShieldsIo();
        $rows      = [self::TABLE_HEADER];
        $throttled = 0;

        foreach (($status['repositories'] ?? []) as $repository) {
            if (is_array($repository) === false) {
                continue;
            }

            $count      = (int) ($repository['throttled'] ?? 0);
            $rows[]     = $this->presentRepository($repository, $count, $shields);
            $throttled += $count;
        }

        return [
                'enabled'          => (bool) ($status['enabled'] ?? false),
                'permitsPerMinute' => (int) ($status['permitsPerMinute'] ?? 0),
                'burstSize'        => (int) ($status['burstSize'] ?? 0),
                'drainPerMinute'   => (int) ($status['drainPerMinute'] ?? 0),
                'repositories'     => $rows,
                'total'            => (count($rows) - 1),
                'throttled'        => $throttled,
               ];

    }//end present()


    /**
     * Renders one repository row.
     *
     * @param array     $repository One entry of the handler's `repositories` array.
     * @param integer   $count      Payloads sent to the Throttled Queue.
     * @param ShieldsIo $shields    Badge URL generator.
     *
     * @return array
     */
    private function presentRepository(array $repository, int $count, ShieldsIo $shields): array
    {
        $repositoryId   = (int) ($repository['repositoryId'] ?? 0);
        $installationId = (int) ($repository['installationId'] ?? 0);
        $name           = trim((string) ($repository['repositoryName'] ?? ''));

        if ($name === '') {
            $cell = htmlspecialchars('#'.$repositoryId, ENT_QUOTES, 'UTF-8');
        } else {
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $url      = htmlspecialchars('https://github.com/'.$name, ENT_QUOTES, 'UTF-8');
            $cell     = '<a href="'.$url.'" target="_blank" rel="noopener noreferrer">'.$safeName.'</a>';
        }

        $installation = '—';
        if ($installationId > 0) {
            $installation = (string) $installationId;
        }

        $badge = $this->badge(
            $shields,
            'throttled',
            number_format($count),
            'orange',
            $count.' payloads sent to the Throttled Queue'
        );

        return [
                $cell,
                $installation,
                $badge,
                $this->formatDate((string) ($repository['lastThrottledAt'] ?? '')),
               ];

    }//end presentRepository()


    /**
     * Converts the handler's ISO 8601 timestamp to the display time zone.
     *
     * @param string $value Timestamp with offset, e.g. 2026-11-10T22:15:03.12+00:00.
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
        $url      = htmlspecialchars((string) $badgeUrl, ENT_QUOTES, 'UTF-8');
        $safeAlt  = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');

        return '<img alt="'.$safeAlt.'" title="'.$safeAlt.'" src="'.$url.'" />';

    }//end badge()


}//end class
