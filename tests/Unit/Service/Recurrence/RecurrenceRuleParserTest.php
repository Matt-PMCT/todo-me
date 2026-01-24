<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Recurrence;

use App\Enum\RecurrenceType;
use App\Exception\InvalidRecurrenceException;
use App\Service\Recurrence\RecurrenceRuleParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecurrenceRuleParser::class)]
final class RecurrenceRuleParserTest extends TestCase
{
    private RecurrenceRuleParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RecurrenceRuleParser();
    }

    // --- Daily Patterns ---

    public function testParseDailyPattern(): void
    {
        $rule = $this->parser->parse('every day');

        self::assertSame('every day', $rule->originalText);
        self::assertSame(RecurrenceType::ABSOLUTE, $rule->type);
        self::assertSame('day', $rule->interval);
        self::assertSame(1, $rule->count);
    }

    public function testParseEveryNDays(): void
    {
        $rule = $this->parser->parse('every 3 days');

        self::assertSame('day', $rule->interval);
        self::assertSame(3, $rule->count);
    }

    public function testParseDailyShortcut(): void
    {
        $rule = $this->parser->parse('daily');

        self::assertSame('day', $rule->interval);
        self::assertSame(1, $rule->count);
    }

    // --- Weekly Patterns ---

    public function testParseWeeklyPattern(): void
    {
        $rule = $this->parser->parse('every week');

        self::assertSame('week', $rule->interval);
        self::assertSame(1, $rule->count);
        self::assertSame([], $rule->days);
    }

    public function testParseEveryNWeeks(): void
    {
        $rule = $this->parser->parse('every 2 weeks');

        self::assertSame('week', $rule->interval);
        self::assertSame(2, $rule->count);
    }

    public function testParseBiweekly(): void
    {
        $rule = $this->parser->parse('biweekly');

        self::assertSame('week', $rule->interval);
        self::assertSame(2, $rule->count);
    }

    public function testParseEveryMonday(): void
    {
        $rule = $this->parser->parse('every Monday');

        self::assertSame('week', $rule->interval);
        self::assertSame(1, $rule->count);
        self::assertSame([1], $rule->days);
    }

    public function testParseEveryMondayWednesdayFriday(): void
    {
        $rule = $this->parser->parse('every Mon, Wed, Fri');

        self::assertSame('week', $rule->interval);
        self::assertSame([1, 3, 5], $rule->days);
    }

    public function testParseDaysWithAndSeparator(): void
    {
        $rule = $this->parser->parse('every Tuesday and Thursday');

        self::assertSame('week', $rule->interval);
        self::assertSame([2, 4], $rule->days);
    }

    public function testParseWeekdays(): void
    {
        $rule = $this->parser->parse('weekdays');

        self::assertSame('week', $rule->interval);
        self::assertSame([1, 2, 3, 4, 5], $rule->days);
    }

    public function testParseWeekend(): void
    {
        $rule = $this->parser->parse('weekend');

        self::assertSame('week', $rule->interval);
        self::assertSame([0, 6], $rule->days);
    }

    public function testParseEveryWeekOnDays(): void
    {
        $rule = $this->parser->parse('every week on Monday');

        self::assertSame('week', $rule->interval);
        self::assertSame(1, $rule->count);
        self::assertSame([1], $rule->days);
    }

    public function testParseEvery2WeeksOnDays(): void
    {
        $rule = $this->parser->parse('every 2 weeks on Tuesday');

        self::assertSame('week', $rule->interval);
        self::assertSame(2, $rule->count);
        self::assertSame([2], $rule->days);
    }

    // --- Monthly Patterns ---

    public function testParseMonthlyPattern(): void
    {
        $rule = $this->parser->parse('every month');

        self::assertSame('month', $rule->interval);
        self::assertSame(1, $rule->count);
    }

    public function testParseEveryNMonths(): void
    {
        $rule = $this->parser->parse('every 3 months');

        self::assertSame('month', $rule->interval);
        self::assertSame(3, $rule->count);
    }

    public function testParseQuarterly(): void
    {
        $rule = $this->parser->parse('quarterly');

        self::assertSame('month', $rule->interval);
        self::assertSame(3, $rule->count);
    }

    public function testParseEvery15th(): void
    {
        $rule = $this->parser->parse('every 15th');

        self::assertSame('month', $rule->interval);
        self::assertSame(15, $rule->dayOfMonth);
    }

    public function testParseEvery1st(): void
    {
        $rule = $this->parser->parse('every 1st');

        self::assertSame('month', $rule->interval);
        self::assertSame(1, $rule->dayOfMonth);
    }

    public function testParseEveryMonthOnThe15th(): void
    {
        $rule = $this->parser->parse('every month on the 15th');

        self::assertSame('month', $rule->interval);
        self::assertSame(15, $rule->dayOfMonth);
    }

    public function testParseEveryLastDay(): void
    {
        $rule = $this->parser->parse('every last day');

        self::assertSame('month', $rule->interval);
        self::assertSame(-1, $rule->dayOfMonth);
    }

    public function testParseEveryMonthOnTheLastDay(): void
    {
        $rule = $this->parser->parse('every month on the last day');

        self::assertSame('month', $rule->interval);
        self::assertSame(-1, $rule->dayOfMonth);
    }

    // --- Yearly Patterns ---

    public function testParseYearlyPattern(): void
    {
        $rule = $this->parser->parse('every year');

        self::assertSame('year', $rule->interval);
        self::assertSame(1, $rule->count);
    }

    public function testParseAnnually(): void
    {
        $rule = $this->parser->parse('annually');

        self::assertSame('year', $rule->interval);
        self::assertSame(1, $rule->count);
    }

    public function testParseEveryJanuary15(): void
    {
        $rule = $this->parser->parse('every January 15');

        self::assertSame('year', $rule->interval);
        self::assertSame(15, $rule->dayOfMonth);
        self::assertSame(1, $rule->monthOfYear);
    }

    public function testParseEveryDecember25(): void
    {
        $rule = $this->parser->parse('every Dec 25');

        self::assertSame('year', $rule->interval);
        self::assertSame(25, $rule->dayOfMonth);
        self::assertSame(12, $rule->monthOfYear);
    }

    // --- Time Patterns ---

    public function testParseWithTimeAM(): void
    {
        $rule = $this->parser->parse('every day at 9am');

        self::assertSame('day', $rule->interval);
        self::assertSame('09:00', $rule->time);
    }

    public function testParseWithTimePM(): void
    {
        $rule = $this->parser->parse('every day at 2pm');

        self::assertSame('day', $rule->interval);
        self::assertSame('14:00', $rule->time);
    }

    public function testParseWithTime24Hour(): void
    {
        $rule = $this->parser->parse('every day at 14:30');

        self::assertSame('day', $rule->interval);
        self::assertSame('14:30', $rule->time);
    }

    public function testParseWithNoon(): void
    {
        $rule = $this->parser->parse('every day noon');

        self::assertSame('day', $rule->interval);
        self::assertSame('12:00', $rule->time);
    }

    public function testParseWithMidnight(): void
    {
        $rule = $this->parser->parse('every day midnight');

        self::assertSame('day', $rule->interval);
        self::assertSame('00:00', $rule->time);
    }

    public function testParseWithTime12AM(): void
    {
        $rule = $this->parser->parse('every day at 12am');

        self::assertSame('day', $rule->interval);
        self::assertSame('00:00', $rule->time);
    }

    public function testParseWithTime12PM(): void
    {
        $rule = $this->parser->parse('every day at 12pm');

        self::assertSame('day', $rule->interval);
        self::assertSame('12:00', $rule->time);
    }

    // --- End Date Patterns ---

    public function testParseWithEndDateISO(): void
    {
        $rule = $this->parser->parse('every day until 2026-12-31');

        self::assertSame('day', $rule->interval);
        self::assertNotNull($rule->endDate);
        self::assertSame('2026-12-31', $rule->endDate->format('Y-m-d'));
    }

    public function testParseWithEndDateMonthDay(): void
    {
        $rule = $this->parser->parse('every day until March 1');

        self::assertSame('day', $rule->interval);
        self::assertNotNull($rule->endDate);
        self::assertSame('03-01', $rule->endDate->format('m-d'));
    }

    public function testParseWithEndDateMonthDayYear(): void
    {
        $rule = $this->parser->parse('every day until March 1, 2027');

        self::assertSame('day', $rule->interval);
        self::assertNotNull($rule->endDate);
        self::assertSame('2027-03-01', $rule->endDate->format('Y-m-d'));
    }

    // --- Relative Type (every!) ---

    public function testParseRelativeDaily(): void
    {
        $rule = $this->parser->parse('every! day');

        self::assertSame(RecurrenceType::RELATIVE, $rule->type);
        self::assertSame('day', $rule->interval);
        self::assertTrue($rule->isRelative());
        self::assertFalse($rule->isAbsolute());
    }

    public function testParseRelativeWeekly(): void
    {
        $rule = $this->parser->parse('every! 2 weeks');

        self::assertSame(RecurrenceType::RELATIVE, $rule->type);
        self::assertSame('week', $rule->interval);
        self::assertSame(2, $rule->count);
    }

    public function testParseAbsoluteIsDefault(): void
    {
        $rule = $this->parser->parse('every day');

        self::assertSame(RecurrenceType::ABSOLUTE, $rule->type);
        self::assertTrue($rule->isAbsolute());
        self::assertFalse($rule->isRelative());
    }

    // --- Case Insensitivity ---

    #[DataProvider('caseInsensitiveProvider')]
    public function testCaseInsensitive(string $input, string $expectedInterval): void
    {
        $rule = $this->parser->parse($input);
        self::assertSame($expectedInterval, $rule->interval);
    }

    public static function caseInsensitiveProvider(): iterable
    {
        yield 'uppercase DAY' => ['EVERY DAY', 'day'];
        yield 'mixed case Day' => ['Every Day', 'day'];
        yield 'uppercase MONDAY' => ['every MONDAY', 'week'];
        yield 'mixed case Monday' => ['Every Monday', 'week'];
        yield 'uppercase DAILY' => ['DAILY', 'day'];
    }

    // --- Whitespace Tolerance ---

    public function testWhitespaceTolerance(): void
    {
        $rule = $this->parser->parse('  every   3   days  ');

        self::assertSame('day', $rule->interval);
        self::assertSame(3, $rule->count);
    }

    // --- Day Abbreviations ---

    #[DataProvider('dayAbbreviationProvider')]
    public function testDayAbbreviations(string $dayStr, int $expectedDay): void
    {
        $rule = $this->parser->parse("every $dayStr");
        self::assertSame([$expectedDay], $rule->days);
    }

    public static function dayAbbreviationProvider(): iterable
    {
        yield 'Monday full' => ['Monday', 1];
        yield 'Monday abbr' => ['Mon', 1];
        yield 'Monday short' => ['mo', 1];
        yield 'Tuesday full' => ['Tuesday', 2];
        yield 'Tuesday abbr' => ['Tue', 2];
        yield 'Wednesday full' => ['Wednesday', 3];
        yield 'Wednesday abbr' => ['Wed', 3];
        yield 'Thursday full' => ['Thursday', 4];
        yield 'Thursday abbr' => ['Thu', 4];
        yield 'Friday full' => ['Friday', 5];
        yield 'Friday abbr' => ['Fri', 5];
        yield 'Saturday full' => ['Saturday', 6];
        yield 'Saturday abbr' => ['Sat', 6];
        yield 'Sunday full' => ['Sunday', 0];
        yield 'Sunday abbr' => ['Sun', 0];
    }

    // --- Month Abbreviations ---

    #[DataProvider('monthAbbreviationProvider')]
    public function testMonthAbbreviations(string $monthStr, int $expectedMonth): void
    {
        $rule = $this->parser->parse("every $monthStr 1");
        self::assertSame($expectedMonth, $rule->monthOfYear);
    }

    public static function monthAbbreviationProvider(): iterable
    {
        yield 'January full' => ['January', 1];
        yield 'January abbr' => ['Jan', 1];
        yield 'February full' => ['February', 2];
        yield 'March abbr' => ['Mar', 3];
        yield 'June abbr' => ['Jun', 6];
        yield 'July full' => ['July', 7];
        yield 'September full' => ['September', 9];
        yield 'December abbr' => ['Dec', 12];
    }

    // --- Complex Patterns ---

    public function testComplexPatternWithAllComponents(): void
    {
        $rule = $this->parser->parse('every 2 weeks on Mon, Fri at 14:00 until December 31, 2026');

        self::assertSame(RecurrenceType::ABSOLUTE, $rule->type);
        self::assertSame('week', $rule->interval);
        self::assertSame(2, $rule->count);
        self::assertSame([1, 5], $rule->days);
        self::assertSame('14:00', $rule->time);
        self::assertNotNull($rule->endDate);
        self::assertSame('2026-12-31', $rule->endDate->format('Y-m-d'));
    }

    public function testEveryOtherDay(): void
    {
        $rule = $this->parser->parse('every other day');

        self::assertSame('day', $rule->interval);
        self::assertSame(2, $rule->count);
    }

    // --- Error Cases ---

    public function testEmptyStringThrowsException(): void
    {
        $this->expectException(InvalidRecurrenceException::class);
        $this->parser->parse('');
    }

    public function testWhitespaceOnlyThrowsException(): void
    {
        $this->expectException(InvalidRecurrenceException::class);
        $this->parser->parse('   ');
    }

    public function testInvalidPatternThrowsException(): void
    {
        $this->expectException(InvalidRecurrenceException::class);
        $this->expectExceptionMessage('Cannot parse recurrence pattern');
        $this->parser->parse('foo bar baz');
    }

    public function testInvalidDateInPatternThrowsException(): void
    {
        $this->expectException(InvalidRecurrenceException::class);
        $this->expectExceptionMessage('Invalid date');
        $this->parser->parse('every day until invalid-not-a-date');
    }

    // --- Original Text Preservation ---

    public function testOriginalTextPreserved(): void
    {
        $originalText = 'Every Monday at 9am';
        $rule = $this->parser->parse($originalText);

        self::assertSame($originalText, $rule->originalText);
    }
}
