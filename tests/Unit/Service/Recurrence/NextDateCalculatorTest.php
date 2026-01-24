<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Recurrence;

use App\Enum\RecurrenceType;
use App\Service\Recurrence\NextDateCalculator;
use App\ValueObject\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(NextDateCalculator::class)]
final class NextDateCalculatorTest extends TestCase
{
    private NextDateCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new NextDateCalculator();
    }

    // --- Daily Tests ---

    public function testDailyRecurrence(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every day',
            type: RecurrenceType::ABSOLUTE,
            interval: 'day',
        );
        $reference = new \DateTimeImmutable('2026-01-15');

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2026-01-16', $next->format('Y-m-d'));
    }

    public function testEveryThreeDays(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every 3 days',
            type: RecurrenceType::ABSOLUTE,
            interval: 'day',
            count: 3,
        );
        $reference = new \DateTimeImmutable('2026-01-15');

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2026-01-18', $next->format('Y-m-d'));
    }

    public function testDailyWithTime(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every day at 9am',
            type: RecurrenceType::ABSOLUTE,
            interval: 'day',
            time: '09:00',
        );
        $reference = new \DateTimeImmutable('2026-01-15 14:00:00');

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2026-01-16', $next->format('Y-m-d'));
        self::assertSame('09:00', $next->format('H:i'));
    }

    // --- Weekly Tests ---

    public function testWeeklyRecurrence(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every week',
            type: RecurrenceType::ABSOLUTE,
            interval: 'week',
        );
        $reference = new \DateTimeImmutable('2026-01-15'); // Wednesday

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2026-01-22', $next->format('Y-m-d'));
    }

    public function testEveryTwoWeeks(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every 2 weeks',
            type: RecurrenceType::ABSOLUTE,
            interval: 'week',
            count: 2,
        );
        $reference = new \DateTimeImmutable('2026-01-15');

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2026-01-29', $next->format('Y-m-d'));
    }

    public function testWeeklyOnSpecificDay(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every Monday',
            type: RecurrenceType::ABSOLUTE,
            interval: 'week',
            days: [1], // Monday
        );
        $reference = new \DateTimeImmutable('2026-01-15'); // Wednesday

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2026-01-19', $next->format('Y-m-d')); // Next Monday
        self::assertSame('Monday', $next->format('l'));
    }

    public function testWeeklyOnMultipleDays(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every Mon, Wed, Fri',
            type: RecurrenceType::ABSOLUTE,
            interval: 'week',
            days: [1, 3, 5], // Mon, Wed, Fri
        );
        $reference = new \DateTimeImmutable('2026-01-13'); // Tuesday

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2026-01-14', $next->format('Y-m-d')); // Next Wednesday
    }

    public function testWeeklyOnMultipleDaysWrapsToNextWeek(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every Mon, Wed',
            type: RecurrenceType::ABSOLUTE,
            interval: 'week',
            days: [1, 3], // Mon, Wed
        );
        $reference = new \DateTimeImmutable('2026-01-15'); // Wednesday

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2026-01-19', $next->format('Y-m-d')); // Next Monday
    }

    // --- Monthly Tests ---

    public function testMonthlyRecurrence(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every month',
            type: RecurrenceType::ABSOLUTE,
            interval: 'month',
        );
        $reference = new \DateTimeImmutable('2026-01-15');

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2026-02-15', $next->format('Y-m-d'));
    }

    public function testEveryThreeMonths(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every 3 months',
            type: RecurrenceType::ABSOLUTE,
            interval: 'month',
            count: 3,
        );
        $reference = new \DateTimeImmutable('2026-01-15');

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2026-04-15', $next->format('Y-m-d'));
    }

    public function testMonthlyOnSpecificDay(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every 15th',
            type: RecurrenceType::ABSOLUTE,
            interval: 'month',
            dayOfMonth: 15,
        );
        $reference = new \DateTimeImmutable('2026-01-15');

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2026-02-15', $next->format('Y-m-d'));
    }

    public function testMonthlyOnLastDay(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every last day',
            type: RecurrenceType::ABSOLUTE,
            interval: 'month',
            dayOfMonth: -1,
        );
        $reference = new \DateTimeImmutable('2026-01-31');

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2026-02-28', $next->format('Y-m-d')); // Feb has 28 days in 2026
    }

    public function testMonthly31stInShortMonth(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every 31st',
            type: RecurrenceType::ABSOLUTE,
            interval: 'month',
            dayOfMonth: 31,
        );
        $reference = new \DateTimeImmutable('2026-01-31');

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2026-02-28', $next->format('Y-m-d')); // Feb has 28 days
    }

    public function testMonthly30thInApril(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every 30th',
            type: RecurrenceType::ABSOLUTE,
            interval: 'month',
            dayOfMonth: 30,
        );
        $reference = new \DateTimeImmutable('2026-03-30');

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2026-04-30', $next->format('Y-m-d')); // April has 30 days
    }

    // --- Yearly Tests ---

    public function testYearlyRecurrence(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every year',
            type: RecurrenceType::ABSOLUTE,
            interval: 'year',
        );
        $reference = new \DateTimeImmutable('2026-01-15');

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2027-01-15', $next->format('Y-m-d'));
    }

    public function testYearlyOnSpecificDate(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every January 15',
            type: RecurrenceType::ABSOLUTE,
            interval: 'year',
            dayOfMonth: 15,
            monthOfYear: 1,
        );
        $reference = new \DateTimeImmutable('2026-01-15');

        $next = $this->calculator->calculate($rule, $reference);

        self::assertSame('2027-01-15', $next->format('Y-m-d'));
    }

    public function testLeapYearFeb29ToNonLeapYear(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every February 29',
            type: RecurrenceType::ABSOLUTE,
            interval: 'year',
            dayOfMonth: 29,
            monthOfYear: 2,
        );
        $reference = new \DateTimeImmutable('2024-02-29'); // 2024 is a leap year

        $next = $this->calculator->calculate($rule, $reference);

        // 2025 is not a leap year, so Feb 29 → Feb 28
        self::assertSame('2025-02-28', $next->format('Y-m-d'));
    }

    // --- End Date Tests ---

    public function testShouldCreateNextInstanceWithoutEndDate(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every day',
            type: RecurrenceType::ABSOLUTE,
            interval: 'day',
        );
        $nextDate = new \DateTimeImmutable('2099-12-31');

        self::assertTrue($this->calculator->shouldCreateNextInstance($rule, $nextDate));
    }

    public function testShouldCreateNextInstanceBeforeEndDate(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every day until Dec 31',
            type: RecurrenceType::ABSOLUTE,
            interval: 'day',
            endDate: new \DateTimeImmutable('2026-12-31'),
        );
        $nextDate = new \DateTimeImmutable('2026-06-15');

        self::assertTrue($this->calculator->shouldCreateNextInstance($rule, $nextDate));
    }

    public function testShouldCreateNextInstanceOnEndDate(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every day until Dec 31',
            type: RecurrenceType::ABSOLUTE,
            interval: 'day',
            endDate: new \DateTimeImmutable('2026-12-31'),
        );
        $nextDate = new \DateTimeImmutable('2026-12-31');

        self::assertTrue($this->calculator->shouldCreateNextInstance($rule, $nextDate));
    }

    public function testShouldNotCreateNextInstanceAfterEndDate(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every day until Dec 31',
            type: RecurrenceType::ABSOLUTE,
            interval: 'day',
            endDate: new \DateTimeImmutable('2026-12-31'),
        );
        $nextDate = new \DateTimeImmutable('2027-01-01');

        self::assertFalse($this->calculator->shouldCreateNextInstance($rule, $nextDate));
    }

    // --- Absolute vs Relative Tests ---

    public function testAbsoluteRecurrenceUsesOriginalSchedule(): void
    {
        // For absolute recurrence, the reference date is the original due date
        // The next date is calculated from the schedule, not completion
        $rule = RecurrenceRule::create(
            originalText: 'every week',
            type: RecurrenceType::ABSOLUTE,
            interval: 'week',
        );
        $originalDueDate = new \DateTimeImmutable('2026-01-15');

        $next = $this->calculator->calculate($rule, $originalDueDate);

        self::assertSame('2026-01-22', $next->format('Y-m-d'));
    }

    public function testRelativeRecurrenceUsesCompletionDate(): void
    {
        // For relative recurrence, the reference date is when the task was completed
        $rule = RecurrenceRule::create(
            originalText: 'every! week',
            type: RecurrenceType::RELATIVE,
            interval: 'week',
        );
        $completionDate = new \DateTimeImmutable('2026-01-20'); // Completed 5 days late

        $next = $this->calculator->calculate($rule, $completionDate);

        // Next date is 1 week from completion, not from original due date
        self::assertSame('2026-01-27', $next->format('Y-m-d'));
    }
}
