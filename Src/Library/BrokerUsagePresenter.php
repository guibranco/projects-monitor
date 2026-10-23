<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

use GuiBranco\Pancake\ShieldsIo;

/**
 * Renders the WebhooksHandler's GET /broker/usage/current response as the
 * Broker Usage table (header row first, one row per broker connection) plus
 * the headline figures the gauge needs. Usage is the CloudAMQP-counted figure
 * (published payloads × message cost: one publish plus one delivery per
 * bound queue), so the percentage shown is the one the broker will block on.
 * Pure formatting, no HTTP, so it is unit-testable.
 */
class BrokerUsagePresenter
{

    public const TABLE_HEADER = [
                                 'Broker',
                                 'Published',
                                 'Usage',
                                 'Remaining',
                                 'Used',
                                ];

    /**
     * Percentage thresholds (inclusive lower bound) for the "Used" badge colour.
     */
    private const WARN_PERCENT = 70;

    private const CRITICAL_PERCENT = 90;


    /**
     * Builds the dashboard payload from the handler's usage response.
     *
     * @param array $usage Decoded GET /broker/usage/current body.
     *
     * @return array Keys: period, messageCost, brokers, total, usage, limit, percent, maxPercent.
     */
    public function present(array $usage): array
    {
        $shields     = new ShieldsIo();
        $rows        = [self::TABLE_HEADER];
        $messageCost = max((int) ($usage['messageCost'] ?? 1), 1);
        $totalUsage  = 0;
        $totalLimit  = 0;
        $maxPercent  = 0.0;

        foreach (($usage['brokers'] ?? []) as $broker) {
            $row = $this->presentBroker($broker, $messageCost, $shields);

            $rows[]      = $row['item'];
            $totalUsage += $row['usage'];
            $totalLimit += $row['limit'];
            $maxPercent  = max($maxPercent, $row['percent']);
        }

        $totalPercent = $this->percentOf($totalUsage, $totalLimit, 0.0);

        return [
                'period'      => (string) ($usage['period'] ?? ''),
                'messageCost' => $messageCost,
                'brokers'     => $rows,
                'total'       => (count($rows) - 1),
                'usage'       => $totalUsage,
                'limit'       => $totalLimit,
                'percent'     => $totalPercent,
                'maxPercent'  => $maxPercent,
               ];

    }//end present()


    /**
     * Renders one broker row.
     *
     * @param array     $broker      One entry of the handler's `brokers` array.
     * @param integer   $messageCost Fallback cost when the entry carries none.
     * @param ShieldsIo $shields     Badge URL generator.
     *
     * @return array{item:array,usage:int,limit:int,percent:float}
     */
    private function presentBroker(array $broker, int $messageCost, ShieldsIo $shields): array
    {
        $name  = (string) ($broker['broker'] ?? 'unknown');
        $count = (int) ($broker['messageCount'] ?? 0);
        $cost  = max((int) ($broker['messageCost'] ?? $messageCost), 1);
        $limit = (int) ($broker['monthlyLimit'] ?? 0);

        // Older handler versions have no cost-adjusted fields; derive them so the
        // panel degrades to "cost 1" rather than showing blanks.
        $usage     = (int) ($broker['usage'] ?? ($count * $cost));
        $remaining = (int) ($broker['remaining'] ?? max(($limit - $usage), 0));
        $percent   = $this->percentOf($usage, $limit, 100.0);
        if (isset($broker['usagePercent']) === true) {
            $percent = (float) $broker['usagePercent'];
        }

        $safeName  = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $usedColor = $this->percentColor($percent);

        $remainColor = 'red';
        if ($remaining > 0) {
            $remainColor = 'green';
        }

        $published = $this->badge(
            $shields,
            'published',
            number_format($count),
            'blue',
            $count.' payloads published'
        );
        $usageImg  = $this->badge(
            $shields,
            'usage',
            number_format($usage).' / '.number_format($limit),
            $usedColor,
            $usage.' of '.$limit.' broker-counted messages (×'.$cost.' per payload)'
        );
        $remainImg = $this->badge(
            $shields,
            'remaining',
            number_format($remaining),
            $remainColor,
            $remaining.' broker-counted messages left'
        );
        $usedImg   = $this->badge(
            $shields,
            'used',
            number_format($percent, 2).'%',
            $usedColor,
            $percent.'% of the monthly limit'
        );

        $item = [
                 $safeName,
                 $published,
                 $usageImg,
                 $remainImg,
                 $usedImg,
                ];

        return [
                'item'    => $item,
                'usage'   => $usage,
                'limit'   => $limit,
                'percent' => $percent,
               ];

    }//end presentBroker()


    /**
     * Percentage of $limit that $value represents, two decimals.
     *
     * @param integer $value     Numerator.
     * @param integer $limit     Denominator.
     * @param float   $whenEmpty Value to return when the limit is zero.
     *
     * @return float
     */
    private function percentOf(int $value, int $limit, float $whenEmpty): float
    {
        if ($limit <= 0) {
            return $whenEmpty;
        }

        return round((($value * 100) / $limit), 2);

    }//end percentOf()


    /**
     * Badge colour for a usage percentage: green below 70%, yellow to 90%, red above.
     *
     * @param float $percent Percentage of the monthly limit used.
     *
     * @return string
     */
    public function percentColor(float $percent): string
    {
        if ($percent >= self::CRITICAL_PERCENT) {
            return 'red';
        }

        if ($percent >= self::WARN_PERCENT) {
            return 'yellow';
        }

        return 'green';

    }//end percentColor()


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
