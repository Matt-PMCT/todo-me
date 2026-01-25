<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Service\Parser\DateParserService;
use App\Service\Parser\TimezoneHelper;
use App\ValueObject\DateParseResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DateParserServiceTest extends TestCase
{
    private TimezoneHelper&MockObject $timezoneHelper;
    private DateParserService $parser;
    private \DateTimeImmutable $fixedNow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->timezoneHelper = $this->createMock(TimezoneHelper::class);
        $this->parser = new DateParserService($this->timezoneHelper);

        // Fixed "now" for consistent testing: 2026-01-23 (Friday) at midnight UTC
        $this->fixedNow = new \DateTimeImmutable('2026-01-23 00:00:00', new \DateTimeZone('UTC'));

        $this->timezoneHelper->method('getStartOfDay')
            ->willReturn($this->fixedNow);

        $this->timezoneHelper->method('getNow')
            ->willReturn($this->fixedNow);
    }

    // ========================================
    // Relative Date Tests (today, tomorrow, yesterday)
    // ========================================

    public function testParsesToday(): void
    {
        $result = $this->parser->parse('do this today');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-23', $result->date->format('Y-m-d'));
        $this->assertEquals('today', $result->originalText);
        $this->assertFalse($result->hasTime);
    }

    public function testParsesTodayUpperCase(): void
    {
        $result = $this->parser->parse('do this TODAY');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-23', $result->date->format('Y-m-d'));
    }

    public function testParsesTomorrow(): void
    {
        $result = $this->parser->parse('finish tomorrow');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-24', $result->date->format('Y-m-d'));
        $this->assertEquals('tomorrow', $result->originalText);
        $this->assertFalse($result->hasTime);
    }

    public function testParsesYesterday(): void
    {
        $result = $this->parser->parse('was due yesterday');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-22', $result->date->format('Y-m-d'));
        $this->assertEquals('yesterday', $result->originalText);
        $this->assertFalse($result->hasTime);
    }

    public function testRelativeDatePositionTracking(): void
    {
        $result = $this->parser->parse('do this today please');

        $this->assertNotNull($result);
        $this->assertEquals(8, $result->startPosition);
        $this->assertEquals(13, $result->endPosition);
    }

    // ========================================
    // Day Offset Tests (in X days, next week)
    // ========================================

    public function testParsesInThreeDays(): void
    {
        $result = $this->parser->parse('due in 3 days');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-26', $result->date->format('Y-m-d'));
        $this->assertEquals('in 3 days', $result->originalText);
        $this->assertFalse($result->hasTime);
    }

    public function testParsesInOneDay(): void
    {
        $result = $this->parser->parse('in 1 day');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-24', $result->date->format('Y-m-d'));
    }

    public function testParsesInADay(): void
    {
        $result = $this->parser->parse('finish in a day');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-24', $result->date->format('Y-m-d'));
        $this->assertEquals('in a day', $result->originalText);
    }

    public function testParsesInAWeek(): void
    {
        $result = $this->parser->parse('in a week');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-30', $result->date->format('Y-m-d'));
    }

    public function testParsesInTwoWeeks(): void
    {
        $result = $this->parser->parse('in 2 weeks');

        $this->assertNotNull($result);
        $this->assertEquals('2026-02-06', $result->date->format('Y-m-d'));
    }

    public function testParsesNextWeek(): void
    {
        $result = $this->parser->parse('next week');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-30', $result->date->format('Y-m-d'));
        $this->assertEquals('next week', $result->originalText);
    }

    public function testParsesInAMonth(): void
    {
        $result = $this->parser->parse('in a month');

        $this->assertNotNull($result);
        $this->assertEquals('2026-02-23', $result->date->format('Y-m-d'));
    }

    public function testParsesNextMonth(): void
    {
        $result = $this->parser->parse('next month');

        $this->assertNotNull($result);
        $this->assertEquals('2026-02-23', $result->date->format('Y-m-d'));
    }

    public function testParsesInThreeMonths(): void
    {
        $result = $this->parser->parse('in 3 months');

        $this->assertNotNull($result);
        $this->assertEquals('2026-04-23', $result->date->format('Y-m-d'));
    }

    // ========================================
    // Day Name Tests (Monday, next Tuesday)
    // ========================================

    public function testParsesMonday(): void
    {
        // Fixed date is Friday 2026-01-23
        // Next Monday is 2026-01-26
        $result = $this->parser->parse('on Monday');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-26', $result->date->format('Y-m-d'));
        $this->assertEquals('Monday', $result->originalText);
    }

    public function testParsesTuesday(): void
    {
        $result = $this->parser->parse('meeting tuesday');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-27', $result->date->format('Y-m-d'));
    }

    public function testParsesFriday(): void
    {
        // Today is Friday, so "friday" should be next Friday
        $result = $this->parser->parse('due friday');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-30', $result->date->format('Y-m-d'));
    }

    public function testParsesNextMonday(): void
    {
        $result = $this->parser->parse('next Monday');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-26', $result->date->format('Y-m-d'));
        $this->assertEquals('next Monday', $result->originalText);
    }

    public function testParsesThisFriday(): void
    {
        // Today is Friday, "this friday" = today
        $result = $this->parser->parse('this friday');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-23', $result->date->format('Y-m-d'));
        $this->assertEquals('this friday', $result->originalText);
    }

    public function testParsesThisMonday(): void
    {
        // Today is Friday, "this monday" = next Monday (same week behavior)
        $result = $this->parser->parse('this monday');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-26', $result->date->format('Y-m-d'));
    }

    public function testParsesAbbreviatedDayNames(): void
    {
        $result = $this->parser->parse('on mon');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-26', $result->date->format('Y-m-d'));
    }

    public function testParsesWednesdayAbbreviated(): void
    {
        $result = $this->parser->parse('wed');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-28', $result->date->format('Y-m-d'));
    }

    public function testParsesSundayWhenTodayIsFriday(): void
    {
        $result = $this->parser->parse('sunday');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-25', $result->date->format('Y-m-d'));
    }

    public function testParsesSaturdayWhenTodayIsFriday(): void
    {
        $result = $this->parser->parse('saturday');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-24', $result->date->format('Y-m-d'));
    }

    // ========================================
    // Month-Day Format Tests (Jan 23, 23rd January)
    // ========================================

    public function testParsesJan23(): void
    {
        $result = $this->parser->parse('due Jan 23');

        $this->assertNotNull($result);
        $this->assertEquals('2027-01-23', $result->date->format('Y-m-d')); // Next year since today is Jan 23
        $this->assertEquals('Jan 23', $result->originalText);
    }

    public function testParsesJanuary24(): void
    {
        $result = $this->parser->parse('January 24');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-24', $result->date->format('Y-m-d'));
    }

    public function testParsesJan23rd(): void
    {
        $result = $this->parser->parse('Jan 23rd');

        $this->assertNotNull($result);
        $this->assertEquals('2027-01-23', $result->date->format('Y-m-d'));
    }

    public function testParsesFebruary1st(): void
    {
        $result = $this->parser->parse('February 1st');

        $this->assertNotNull($result);
        $this->assertEquals('2026-02-01', $result->date->format('Y-m-d'));
    }

    public function testParsesMarch22nd(): void
    {
        $result = $this->parser->parse('March 22nd');

        $this->assertNotNull($result);
        $this->assertEquals('2026-03-22', $result->date->format('Y-m-d'));
    }

    public function testParsesDecember25th(): void
    {
        $result = $this->parser->parse('Dec 25th');

        $this->assertNotNull($result);
        $this->assertEquals('2026-12-25', $result->date->format('Y-m-d'));
    }

    public function testParses23Jan(): void
    {
        $result = $this->parser->parse('23 Jan');

        $this->assertNotNull($result);
        $this->assertEquals('2027-01-23', $result->date->format('Y-m-d'));
        $this->assertEquals('23 Jan', $result->originalText);
    }

    public function testParses23rdJanuary(): void
    {
        $result = $this->parser->parse('23rd January');

        $this->assertNotNull($result);
        $this->assertEquals('2027-01-23', $result->date->format('Y-m-d'));
    }

    public function testParses1stFebruary(): void
    {
        $result = $this->parser->parse('1st February');

        $this->assertNotNull($result);
        $this->assertEquals('2026-02-01', $result->date->format('Y-m-d'));
    }

    // ========================================
    // Numeric Format Tests with Date Format Settings
    // ========================================

    public function testParsesNumericMDYFormat(): void
    {
        $this->parser->setDateFormat('MDY');
        $result = $this->parser->parse('due 1/25');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-25', $result->date->format('Y-m-d')); // Jan 25
    }

    public function testParsesNumericDMYFormat(): void
    {
        $this->parser->setDateFormat('DMY');
        $result = $this->parser->parse('due 25/1');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-25', $result->date->format('Y-m-d')); // 25 Jan
    }

    public function testParsesNumericWithHyphenMDY(): void
    {
        $this->parser->setDateFormat('MDY');
        $result = $this->parser->parse('01-25-2026');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-25', $result->date->format('Y-m-d'));
    }

    public function testParsesNumericWithHyphenDMY(): void
    {
        $this->parser->setDateFormat('DMY');
        $result = $this->parser->parse('25-01-2026');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-25', $result->date->format('Y-m-d'));
    }

    public function testParsesNumericYMDFormat(): void
    {
        $this->parser->setDateFormat('YMD');
        $result = $this->parser->parse('2026-01-25');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-25', $result->date->format('Y-m-d'));
    }

    public function testParsesNumericWithYear(): void
    {
        $this->parser->setDateFormat('MDY');
        $result = $this->parser->parse('1/25/2027');

        $this->assertNotNull($result);
        $this->assertEquals('2027-01-25', $result->date->format('Y-m-d'));
    }

    public function testNumericDateRollsToNextYearIfPassed(): void
    {
        $this->parser->setDateFormat('MDY');
        // Jan 1 has passed relative to Jan 23
        $result = $this->parser->parse('1/1');

        $this->assertNotNull($result);
        $this->assertEquals('2027-01-01', $result->date->format('Y-m-d'));
    }

    public function testAmbiguousDateUsesMDYDefault(): void
    {
        // 1/2 with MDY = January 2nd
        $this->parser->setDateFormat('MDY');
        $result = $this->parser->parse('1/2');

        $this->assertNotNull($result);
        $this->assertEquals('2027-01-02', $result->date->format('Y-m-d')); // Jan 2 (next year)
    }

    public function testAmbiguousDateUsesDMY(): void
    {
        // 1/2 with DMY = February 1st
        $this->parser->setDateFormat('DMY');
        $result = $this->parser->parse('1/2');

        $this->assertNotNull($result);
        $this->assertEquals('2026-02-01', $result->date->format('Y-m-d')); // Feb 1 (this year)
    }

    // ========================================
    // ISO Format Tests
    // ========================================

    public function testParsesIsoDate(): void
    {
        $result = $this->parser->parse('2026-01-23');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-23', $result->date->format('Y-m-d'));
        $this->assertEquals('2026-01-23', $result->originalText);
    }

    public function testParsesIsoDateInText(): void
    {
        $result = $this->parser->parse('due by 2026-02-15 please');

        $this->assertNotNull($result);
        $this->assertEquals('2026-02-15', $result->date->format('Y-m-d'));
        $this->assertEquals(7, $result->startPosition);
        $this->assertEquals(17, $result->endPosition);
    }

    public function testParsesIsoDateFutureYear(): void
    {
        $result = $this->parser->parse('2030-12-31');

        $this->assertNotNull($result);
        $this->assertEquals('2030-12-31', $result->date->format('Y-m-d'));
    }

    // ========================================
    // Time Parsing Tests
    // ========================================

    public function testParsesAtTime12HourPm(): void
    {
        $result = $this->parser->parse('tomorrow at 2pm');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-24', $result->date->format('Y-m-d'));
        $this->assertEquals('14:00', $result->time);
        $this->assertEquals('14', $result->date->format('H'));
        $this->assertTrue($result->hasTime);
    }

    public function testParsesAtTime12HourAm(): void
    {
        $result = $this->parser->parse('tomorrow at 9am');

        $this->assertNotNull($result);
        $this->assertEquals('09:00', $result->time);
        $this->assertEquals('09', $result->date->format('H'));
        $this->assertTrue($result->hasTime);
    }

    public function testParsesAt12pm(): void
    {
        $result = $this->parser->parse('tomorrow at 12pm');

        $this->assertNotNull($result);
        $this->assertEquals('12:00', $result->time);
        $this->assertEquals('12', $result->date->format('H'));
        $this->assertTrue($result->hasTime);
    }

    public function testParsesAt12am(): void
    {
        $result = $this->parser->parse('tomorrow at 12am');

        $this->assertNotNull($result);
        $this->assertEquals('00:00', $result->time);
        $this->assertEquals('00', $result->date->format('H'));
        $this->assertTrue($result->hasTime);
    }

    public function testParsesTimeWithMinutes(): void
    {
        $result = $this->parser->parse('tomorrow at 2:30pm');

        $this->assertNotNull($result);
        $this->assertEquals('14:30', $result->time);
        $this->assertEquals('14', $result->date->format('H'));
        $this->assertEquals('30', $result->date->format('i'));
        $this->assertTrue($result->hasTime);
    }

    public function testParsesTime24Hour(): void
    {
        $result = $this->parser->parse('tomorrow at 14:00');

        $this->assertNotNull($result);
        $this->assertEquals('14:00', $result->time);
        $this->assertTrue($result->hasTime);
    }

    public function testParsesCombinedDayNameAndTime(): void
    {
        $result = $this->parser->parse('next Monday at 9am');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-26', $result->date->format('Y-m-d'));
        $this->assertEquals('09:00', $result->time);
        $this->assertTrue($result->hasTime);
    }

    public function testDateWithoutTimeHasTimeFalse(): void
    {
        $result = $this->parser->parse('tomorrow');

        $this->assertNotNull($result);
        $this->assertNull($result->time);
        $this->assertFalse($result->hasTime);
    }

    public function testTimeIncludedInOriginalText(): void
    {
        $result = $this->parser->parse('tomorrow at 2pm please');

        $this->assertNotNull($result);
        $this->assertStringContainsString('at 2pm', $result->originalText);
    }

    // ========================================
    // Position Tracking Tests
    // ========================================

    public function testPositionTrackingAtStart(): void
    {
        $result = $this->parser->parse('tomorrow is the deadline');

        $this->assertNotNull($result);
        $this->assertEquals(0, $result->startPosition);
        $this->assertEquals(8, $result->endPosition);
    }

    public function testPositionTrackingInMiddle(): void
    {
        $result = $this->parser->parse('the deadline is tomorrow at noon');

        $this->assertNotNull($result);
        $this->assertEquals(16, $result->startPosition);
    }

    public function testPositionTrackingAtEnd(): void
    {
        $result = $this->parser->parse('due today');

        $this->assertNotNull($result);
        $this->assertEquals(4, $result->startPosition);
        $this->assertEquals(9, $result->endPosition);
    }

    public function testPositionTrackingWithTime(): void
    {
        $result = $this->parser->parse('meeting tomorrow at 3pm');

        $this->assertNotNull($result);
        $this->assertEquals(8, $result->startPosition);
        $this->assertEquals(23, $result->endPosition); // Includes "at 3pm"
    }

    // ========================================
    // Edge Cases and Invalid Input Tests
    // ========================================

    public function testReturnsNullForNoDatePattern(): void
    {
        $result = $this->parser->parse('buy groceries');

        $this->assertNull($result);
    }

    public function testReturnsNullForEmptyString(): void
    {
        $result = $this->parser->parse('');

        $this->assertNull($result);
    }

    public function testReturnsNullForOnlyWhitespace(): void
    {
        $result = $this->parser->parse('   ');

        $this->assertNull($result);
    }

    public function testParsesFirstDatePatternInInput(): void
    {
        $result = $this->parser->parse('tomorrow and next week');

        $this->assertNotNull($result);
        $this->assertEquals('tomorrow', $result->originalText);
        $this->assertEquals('2026-01-24', $result->date->format('Y-m-d'));
    }

    public function testDoesNotMatchPartialWords(): void
    {
        // "stoday" should not match "today"
        $result = $this->parser->parse('stoday is not a word');

        $this->assertNull($result);
    }

    public function testHandlesCaseMixedInput(): void
    {
        $result = $this->parser->parse('ToMoRrOw');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-24', $result->date->format('Y-m-d'));
    }

    // ========================================
    // Timezone Tests
    // ========================================

    public function testSetUserTimezoneReturnsself(): void
    {
        $result = $this->parser->setUserTimezone('America/New_York');

        $this->assertSame($this->parser, $result);
    }

    public function testSetStartOfWeekReturnsSelf(): void
    {
        $result = $this->parser->setStartOfWeek(1);

        $this->assertSame($this->parser, $result);
    }

    public function testSetDateFormatReturnsSelf(): void
    {
        $result = $this->parser->setDateFormat('DMY');

        $this->assertSame($this->parser, $result);
    }

    public function testTimezoneHelperIsUsedForStartOfDay(): void
    {
        // The mock is already set up to return fixedNow
        $result = $this->parser->parse('today');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-23', $result->date->format('Y-m-d'));
    }

    // ========================================
    // DateParseResult Value Object Tests
    // ========================================

    public function testDateParseResultToArray(): void
    {
        $result = $this->parser->parse('tomorrow at 2pm');

        $this->assertNotNull($result);
        $array = $result->toArray();

        $this->assertArrayHasKey('date', $array);
        $this->assertArrayHasKey('time', $array);
        $this->assertArrayHasKey('originalText', $array);
        $this->assertArrayHasKey('startPosition', $array);
        $this->assertArrayHasKey('endPosition', $array);
        $this->assertArrayHasKey('hasTime', $array);
    }

    public function testDateParseResultFromArray(): void
    {
        $data = [
            'date' => '2026-01-24T14:00:00+00:00',
            'time' => '14:00',
            'originalText' => 'tomorrow at 2pm',
            'startPosition' => 0,
            'endPosition' => 15,
            'hasTime' => true,
        ];

        $result = DateParseResult::fromArray($data);

        $this->assertEquals('2026-01-24', $result->date->format('Y-m-d'));
        $this->assertEquals('14:00', $result->time);
        $this->assertEquals('tomorrow at 2pm', $result->originalText);
        $this->assertEquals(0, $result->startPosition);
        $this->assertEquals(15, $result->endPosition);
        $this->assertTrue($result->hasTime);
    }

    public function testDateParseResultRoundTrip(): void
    {
        $result = $this->parser->parse('tomorrow at 2pm');
        $this->assertNotNull($result);

        $array = $result->toArray();
        $restored = DateParseResult::fromArray($array);

        $this->assertEquals($result->originalText, $restored->originalText);
        $this->assertEquals($result->time, $restored->time);
        $this->assertEquals($result->startPosition, $restored->startPosition);
        $this->assertEquals($result->endPosition, $restored->endPosition);
        $this->assertEquals($result->hasTime, $restored->hasTime);
    }

    public function testDateParseResultFromArrayWithNullDate(): void
    {
        $data = [
            'date' => null,
            'time' => null,
            'originalText' => 'invalid',
            'startPosition' => 0,
            'endPosition' => 7,
            'hasTime' => false,
        ];

        $result = DateParseResult::fromArray($data);

        $this->assertNull($result->date);
        $this->assertNull($result->time);
    }

    public function testDateParseResultFromArrayThrowsOnMissingKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required key "originalText"');

        DateParseResult::fromArray([
            'date' => '2026-01-24T00:00:00+00:00',
            'startPosition' => 0,
            'endPosition' => 5,
            'hasTime' => false,
        ]);
    }

    public function testDateParseResultIsReadonly(): void
    {
        $result = $this->parser->parse('tomorrow');
        $this->assertNotNull($result);

        $reflection = new \ReflectionClass($result);
        $this->assertTrue($reflection->isReadOnly());
    }

    // ========================================
    // Complex Input Tests
    // ========================================

    public function testComplexSentenceWithDate(): void
    {
        $result = $this->parser->parse('Please schedule a meeting for next Monday at 3pm');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-26', $result->date->format('Y-m-d'));
        $this->assertEquals('15:00', $result->time);
        $this->assertTrue($result->hasTime);
    }

    public function testMultipleDatesReturnsFirst(): void
    {
        $result = $this->parser->parse('today or tomorrow');

        $this->assertNotNull($result);
        $this->assertEquals('today', $result->originalText);
    }

    public function testDateInEmailStyle(): void
    {
        $result = $this->parser->parse('Deadline: Jan 30');

        $this->assertNotNull($result);
        $this->assertEquals('2026-01-30', $result->date->format('Y-m-d'));
    }

    // ========================================
    // All Month Names Tests
    // ========================================

    /**
     * @dataProvider monthNameProvider
     */
    public function testAllMonthNames(string $month, int $expectedMonth): void
    {
        $result = $this->parser->parse("$month 15");

        $this->assertNotNull($result);
        $this->assertEquals($expectedMonth, (int) $result->date->format('m'));
    }

    public static function monthNameProvider(): array
    {
        return [
            ['January', 1],
            ['jan', 1],
            ['February', 2],
            ['feb', 2],
            ['March', 3],
            ['mar', 3],
            ['April', 4],
            ['apr', 4],
            ['May', 5],
            ['June', 6],
            ['jun', 6],
            ['July', 7],
            ['jul', 7],
            ['August', 8],
            ['aug', 8],
            ['September', 9],
            ['sep', 9],
            ['sept', 9],
            ['October', 10],
            ['oct', 10],
            ['November', 11],
            ['nov', 11],
            ['December', 12],
            ['dec', 12],
        ];
    }

    // ========================================
    // All Day Names Tests
    // ========================================

    /**
     * @dataProvider dayNameProvider
     */
    public function testAllDayNames(string $day): void
    {
        $result = $this->parser->parse($day);

        $this->assertNotNull($result);
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->date);
    }

    public static function dayNameProvider(): array
    {
        return [
            ['sunday'],
            ['sun'],
            ['monday'],
            ['mon'],
            ['tuesday'],
            ['tue'],
            ['tues'],
            ['wednesday'],
            ['wed'],
            ['thursday'],
            ['thu'],
            ['thur'],
            ['thurs'],
            ['friday'],
            ['fri'],
            ['saturday'],
            ['sat'],
        ];
    }

    // ========================================
    // Ordinal Suffix Tests
    // ========================================

    /**
     * @dataProvider ordinalSuffixProvider
     */
    public function testOrdinalSuffixes(string $input, int $expectedDay): void
    {
        $result = $this->parser->parse($input);

        $this->assertNotNull($result);
        $this->assertEquals($expectedDay, (int) $result->date->format('d'));
    }

    public static function ordinalSuffixProvider(): array
    {
        return [
            ['Feb 1st', 1],
            ['Feb 2nd', 2],
            ['Feb 3rd', 3],
            ['Feb 4th', 4],
            ['Feb 11th', 11],
            ['Feb 12th', 12],
            ['Feb 13th', 13],
            ['Feb 21st', 21],
            ['Feb 22nd', 22],
            ['Feb 23rd', 23],
        ];
    }
}
