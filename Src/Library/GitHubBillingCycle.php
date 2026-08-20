<?php

declare(strict_types=1);

namespace GuiBranco\ProjectsMonitor\Library;

use DateTimeImmutable;
use DateInterval;

/**
 * Resolves an account's billing-cycle window from its cycleResetDay.
 *
 * GitHub Actions billing cycles are not calendar-aligned, and the three
 * tracked accounts may reset on different days. When cycleResetDay is null
 * we fall back to the calendar month (labelled "calendar month (approx)")
 * since that's the only boundary we can infer without it.
 */
class GitHubBillingCycle
{
    /**
     * @return array{start: DateTimeImmutable, end: DateTimeImmutable, daysUntilReset: int, label: string}
     */
    public static function resolve(?int $cycleResetDay, DateTimeImmutable $now): array
    {
        if ($cycleResetDay === null) {
            $start = new DateTimeImmutable($now->format("Y-m-01"));
            $end = $start->add(new DateInterval("P1M"));

            return [
                "start" => $start,
                "end" => $end,
                "daysUntilReset" => self::daysBetween($now, $end),
                "label" => "calendar month (approx)",
            ];
        }

        $thisMonthReset = self::resetDateForMonth($now, $cycleResetDay);

        if ($now < $thisMonthReset) {
            $end = $thisMonthReset;
            $start = self::resetDateForMonth($now->sub(new DateInterval("P1M")), $cycleResetDay);
        } else {
            $start = $thisMonthReset;
            $end = self::resetDateForMonth($now->add(new DateInterval("P1M")), $cycleResetDay);
        }

        return [
            "start" => $start,
            "end" => $end,
            "daysUntilReset" => self::daysBetween($now, $end),
            "label" => "billing cycle (resets day {$cycleResetDay})",
        ];
    }

    /**
     * The list of distinct [year, month] pairs a [start, end) window spans,
     * used to know which calendar months to query via the non-summary
     * /settings/billing/usage endpoint when a cycle crosses a month boundary.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    public static function monthsInWindow(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $months = [];
        $cursor = new DateTimeImmutable($start->format("Y-m-01"));
        $lastMonthStart = new DateTimeImmutable($end->sub(new DateInterval("P1D"))->format("Y-m-01"));

        while ($cursor <= $lastMonthStart) {
            $months[] = [(int) $cursor->format("Y"), (int) $cursor->format("n")];
            $cursor = $cursor->add(new DateInterval("P1M"));
        }

        return $months;
    }

    public static function isDateInWindow(string $date, DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        $day = new DateTimeImmutable(substr($date, 0, 10));
        return $day >= $start && $day < $end;
    }

    private static function resetDateForMonth(DateTimeImmutable $reference, int $cycleResetDay): DateTimeImmutable
    {
        $daysInMonth = (int) $reference->format("t");
        $clampedDay = min($cycleResetDay, $daysInMonth);

        return new DateTimeImmutable($reference->format("Y-m") . "-" . sprintf("%02d", $clampedDay));
    }

    private static function daysBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return max(0, $from->diff($to)->days);
    }
}
