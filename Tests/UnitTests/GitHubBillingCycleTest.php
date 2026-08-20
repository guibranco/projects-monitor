<?php

use PHPUnit\Framework\TestCase;
use GuiBranco\ProjectsMonitor\Library\GitHubBillingCycle;

class GitHubBillingCycleTest extends TestCase
{
    public function testNullCycleResetDayFallsBackToCalendarMonth()
    {
        $now = new DateTimeImmutable("2026-08-20");
        $cycle = GitHubBillingCycle::resolve(null, $now);

        $this->assertSame("2026-08-01", $cycle["start"]->format("Y-m-d"));
        $this->assertSame("2026-09-01", $cycle["end"]->format("Y-m-d"));
        $this->assertSame(12, $cycle["daysUntilReset"]);
        $this->assertSame("calendar month (approx)", $cycle["label"]);
    }

    public function testCycleResetDayAlreadyPassedThisMonth()
    {
        $now = new DateTimeImmutable("2026-08-20");
        $cycle = GitHubBillingCycle::resolve(15, $now);

        $this->assertSame("2026-08-15", $cycle["start"]->format("Y-m-d"));
        $this->assertSame("2026-09-15", $cycle["end"]->format("Y-m-d"));
        $this->assertSame(26, $cycle["daysUntilReset"]);
        $this->assertSame("billing cycle (resets day 15)", $cycle["label"]);
    }

    public function testCycleResetDayNotYetReachedThisMonth()
    {
        $now = new DateTimeImmutable("2026-08-20");
        $cycle = GitHubBillingCycle::resolve(25, $now);

        $this->assertSame("2026-07-25", $cycle["start"]->format("Y-m-d"));
        $this->assertSame("2026-08-25", $cycle["end"]->format("Y-m-d"));
        $this->assertSame(5, $cycle["daysUntilReset"]);
    }

    public function testCycleResetDayClampsToShorterMonth()
    {
        // April has 30 days — a resetDay of 31 must clamp, not roll into May.
        $now = new DateTimeImmutable("2026-04-15");
        $cycle = GitHubBillingCycle::resolve(31, $now);

        $this->assertSame("2026-03-31", $cycle["start"]->format("Y-m-d"));
        $this->assertSame("2026-04-30", $cycle["end"]->format("Y-m-d"));
    }

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

    public function testMonthsInWindowSingleCalendarMonth()
    {
        $start = new DateTimeImmutable("2026-08-01");
        $end = new DateTimeImmutable("2026-09-01");

        $this->assertSame([[2026, 8]], GitHubBillingCycle::monthsInWindow($start, $end));
    }

    public function testMonthsInWindowSpanningTwoCalendarMonths()
    {
        $start = new DateTimeImmutable("2026-08-15");
        $end = new DateTimeImmutable("2026-09-15");

        $this->assertSame([[2026, 8], [2026, 9]], GitHubBillingCycle::monthsInWindow($start, $end));
    }

    public function testMonthsInWindowSpanningYearBoundary()
    {
        $start = new DateTimeImmutable("2025-12-20");
        $end = new DateTimeImmutable("2026-01-20");

        $this->assertSame([[2025, 12], [2026, 1]], GitHubBillingCycle::monthsInWindow($start, $end));
    }

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
