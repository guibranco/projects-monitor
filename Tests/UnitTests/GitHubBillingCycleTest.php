<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\GitHubBillingCycle;

/** Tests GitHubBillingCycle's reset-window resolution, month-span enumeration, and date-in-window checks. */
class GitHubBillingCycleTest extends TestCase
{
    /** A null cycleResetDay falls back to the calendar month, labelled "calendar month (approx)". */
    public function testNullCycleResetDayFallsBackToCalendarMonth()
    {
        $now = new DateTimeImmutable("2026-08-20");
        $cycle = GitHubBillingCycle::resolve(null, $now);

        $this->assertSame("2026-08-01", $cycle["start"]->format("Y-m-d"));
        $this->assertSame("2026-09-01", $cycle["end"]->format("Y-m-d"));
        $this->assertSame(12, $cycle["daysUntilReset"]);
        $this->assertSame("calendar month (approx)", $cycle["label"]);
    }

    /** When "now" is past this month's reset day, the window runs from this month's reset to next month's. */
    public function testCycleResetDayAlreadyPassedThisMonth()
    {
        $now = new DateTimeImmutable("2026-08-20");
        $cycle = GitHubBillingCycle::resolve(15, $now);

        $this->assertSame("2026-08-15", $cycle["start"]->format("Y-m-d"));
        $this->assertSame("2026-09-15", $cycle["end"]->format("Y-m-d"));
        $this->assertSame(26, $cycle["daysUntilReset"]);
        $this->assertSame("billing cycle (resets day 15)", $cycle["label"]);
    }

    /** When "now" is before this month's reset day, the window runs from last month's reset to this month's. */
    public function testCycleResetDayNotYetReachedThisMonth()
    {
        $now = new DateTimeImmutable("2026-08-20");
        $cycle = GitHubBillingCycle::resolve(25, $now);

        $this->assertSame("2026-07-25", $cycle["start"]->format("Y-m-d"));
        $this->assertSame("2026-08-25", $cycle["end"]->format("Y-m-d"));
        $this->assertSame(5, $cycle["daysUntilReset"]);
    }

    /** A resetDay past the end of a shorter month clamps to that month's last day instead of rolling into the next. */
    public function testCycleResetDayClampsToShorterMonth()
    {
        // April has 30 days — a resetDay of 31 must clamp, not roll into May.
        $now = new DateTimeImmutable("2026-04-15");
        $cycle = GitHubBillingCycle::resolve(31, $now);

        $this->assertSame("2026-03-31", $cycle["start"]->format("Y-m-d"));
        $this->assertSame("2026-04-30", $cycle["end"]->format("Y-m-d"));
    }

    /** Regression: subtracting a month must not overflow into the following month when the target day doesn't exist last month. */
    public function testCycleResetDayDoesNotOverflowWhenSubtractingAMonthFromADayThatDoesNotExistLastMonth()
    {
        // Regression: 2026-03-30 minus one month lands on "Feb 30", which PHP's
        // DateInterval normalizes by overflowing into March instead of clamping —
        // resolve() must anchor to day 1 before doing month arithmetic to avoid this.
        $now = new DateTimeImmutable("2026-03-30");
        $cycle = GitHubBillingCycle::resolve(31, $now);

        $this->assertSame("2026-02-28", $cycle["start"]->format("Y-m-d"));
        $this->assertSame("2026-03-31", $cycle["end"]->format("Y-m-d"));
    }

    /** Regression: adding a month must not overflow into the month after next when the target day doesn't exist next month. */
    public function testCycleResetDayDoesNotOverflowWhenAddingAMonthFromADayThatDoesNotExistNextMonth()
    {
        // Regression: 2026-01-31 plus one month lands on "Feb 31", which PHP's
        // DateInterval normalizes into March instead of clamping to Feb 28 —
        // resolve() must anchor to day 1 before doing month arithmetic to avoid this.
        $now = new DateTimeImmutable("2026-01-31");
        $cycle = GitHubBillingCycle::resolve(15, $now);

        $this->assertSame("2026-01-15", $cycle["start"]->format("Y-m-d"));
        $this->assertSame("2026-02-15", $cycle["end"]->format("Y-m-d"));
    }

    /** A window matching exactly one calendar month yields a single [year, month] pair. */
    public function testMonthsInWindowSingleCalendarMonth()
    {
        $start = new DateTimeImmutable("2026-08-01");
        $end = new DateTimeImmutable("2026-09-01");

        $this->assertSame([[2026, 8]], GitHubBillingCycle::monthsInWindow($start, $end));
    }

    /** A window crossing a month boundary yields both months, in order. */
    public function testMonthsInWindowSpanningTwoCalendarMonths()
    {
        $start = new DateTimeImmutable("2026-08-15");
        $end = new DateTimeImmutable("2026-09-15");

        $this->assertSame([[2026, 8], [2026, 9]], GitHubBillingCycle::monthsInWindow($start, $end));
    }

    /** A window crossing a year boundary (December into January) yields both months with the correct years. */
    public function testMonthsInWindowSpanningYearBoundary()
    {
        $start = new DateTimeImmutable("2025-12-20");
        $end = new DateTimeImmutable("2026-01-20");

        $this->assertSame([[2025, 12], [2026, 1]], GitHubBillingCycle::monthsInWindow($start, $end));
    }

    /** isDateInWindow() treats the window as start-inclusive, end-exclusive. */
    public function testIsDateInWindowIsStartInclusiveEndExclusive()
    {
        $start = new DateTimeImmutable("2026-08-01");
        $end = new DateTimeImmutable("2026-09-01");

        $this->assertTrue(GitHubBillingCycle::isDateInWindow("2026-08-01", $start, $end));
        $this->assertTrue(GitHubBillingCycle::isDateInWindow("2026-08-31", $start, $end));
        $this->assertFalse(GitHubBillingCycle::isDateInWindow("2026-09-01", $start, $end));
        $this->assertFalse(GitHubBillingCycle::isDateInWindow("2026-07-31", $start, $end));
    }
}
