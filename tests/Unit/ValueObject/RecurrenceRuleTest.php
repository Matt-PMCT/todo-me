<?php

declare(strict_types=1);

namespace App\Tests\Unit\ValueObject;

use App\Enum\RecurrenceType;
use App\ValueObject\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecurrenceRule::class)]
final class RecurrenceRuleTest extends TestCase
{
    public function testCreateWithMinimalData(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every day',
            type: RecurrenceType::ABSOLUTE,
            interval: 'day',
        );

        self::assertSame('every day', $rule->originalText);
        self::assertSame(RecurrenceType::ABSOLUTE, $rule->type);
        self::assertSame('day', $rule->interval);
        self::assertSame(1, $rule->count);
        self::assertSame([], $rule->days);
        self::assertNull($rule->dayOfMonth);
        self::assertNull($rule->monthOfYear);
        self::assertNull($rule->time);
        self::assertNull($rule->endDate);
    }

    public function testCreateWithFullData(): void
    {
        $endDate = new \DateTimeImmutable('2026-12-31');
        $rule = RecurrenceRule::create(
            originalText: 'every 2 weeks on Mon, Wed at 10:00 until Dec 31',
            type: RecurrenceType::RELATIVE,
            interval: 'week',
            count: 2,
            days: [1, 3],
            dayOfMonth: null,
            monthOfYear: null,
            time: '10:00',
            endDate: $endDate,
        );

        self::assertSame('every 2 weeks on Mon, Wed at 10:00 until Dec 31', $rule->originalText);
        self::assertSame(RecurrenceType::RELATIVE, $rule->type);
        self::assertSame('week', $rule->interval);
        self::assertSame(2, $rule->count);
        self::assertSame([1, 3], $rule->days);
        self::assertNull($rule->dayOfMonth);
        self::assertNull($rule->monthOfYear);
        self::assertSame('10:00', $rule->time);
        self::assertEquals($endDate, $rule->endDate);
    }

    public function testIsAbsolute(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every day',
            type: RecurrenceType::ABSOLUTE,
            interval: 'day',
        );

        self::assertTrue($rule->isAbsolute());
        self::assertFalse($rule->isRelative());
    }

    public function testIsRelative(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every! day',
            type: RecurrenceType::RELATIVE,
            interval: 'day',
        );

        self::assertTrue($rule->isRelative());
        self::assertFalse($rule->isAbsolute());
    }

    public function testToArray(): void
    {
        $endDate = new \DateTimeImmutable('2026-12-31');
        $rule = RecurrenceRule::create(
            originalText: 'every month on the 15th',
            type: RecurrenceType::ABSOLUTE,
            interval: 'month',
            count: 1,
            days: [],
            dayOfMonth: 15,
            monthOfYear: null,
            time: '14:00',
            endDate: $endDate,
        );

        $array = $rule->toArray();

        self::assertSame('every month on the 15th', $array['originalText']);
        self::assertSame('absolute', $array['type']);
        self::assertSame('month', $array['interval']);
        self::assertSame(1, $array['count']);
        self::assertSame([], $array['days']);
        self::assertSame(15, $array['dayOfMonth']);
        self::assertNull($array['monthOfYear']);
        self::assertSame('14:00', $array['time']);
        self::assertSame('2026-12-31', $array['endDate']);
    }

    public function testFromArray(): void
    {
        $data = [
            'originalText' => 'every week',
            'type' => 'absolute',
            'interval' => 'week',
            'count' => 1,
            'days' => [1, 2, 3, 4, 5],
            'dayOfMonth' => null,
            'monthOfYear' => null,
            'time' => null,
            'endDate' => '2026-06-30',
        ];

        $rule = RecurrenceRule::fromArray($data);

        self::assertSame('every week', $rule->originalText);
        self::assertSame(RecurrenceType::ABSOLUTE, $rule->type);
        self::assertSame('week', $rule->interval);
        self::assertSame(1, $rule->count);
        self::assertSame([1, 2, 3, 4, 5], $rule->days);
        self::assertNull($rule->dayOfMonth);
        self::assertNull($rule->monthOfYear);
        self::assertNull($rule->time);
        self::assertNotNull($rule->endDate);
        self::assertSame('2026-06-30', $rule->endDate->format('Y-m-d'));
    }

    public function testFromArrayWithMinimalData(): void
    {
        $data = [
            'originalText' => 'daily',
            'type' => 'relative',
            'interval' => 'day',
        ];

        $rule = RecurrenceRule::fromArray($data);

        self::assertSame('daily', $rule->originalText);
        self::assertSame(RecurrenceType::RELATIVE, $rule->type);
        self::assertSame('day', $rule->interval);
        self::assertSame(1, $rule->count);
        self::assertSame([], $rule->days);
        self::assertNull($rule->endDate);
    }

    #[DataProvider('missingRequiredKeyProvider')]
    public function testFromArrayThrowsOnMissingRequiredKey(array $data, string $missingKey): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Missing required key "%s"', $missingKey));

        RecurrenceRule::fromArray($data);
    }

    public static function missingRequiredKeyProvider(): iterable
    {
        yield 'missing originalText' => [
            ['type' => 'absolute', 'interval' => 'day'],
            'originalText',
        ];
        yield 'missing type' => [
            ['originalText' => 'daily', 'interval' => 'day'],
            'type',
        ];
        yield 'missing interval' => [
            ['originalText' => 'daily', 'type' => 'absolute'],
            'interval',
        ];
    }

    public function testFromArrayThrowsOnInvalidType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid recurrence type "invalid"');

        RecurrenceRule::fromArray([
            'originalText' => 'daily',
            'type' => 'invalid',
            'interval' => 'day',
        ]);
    }

    public function testToJson(): void
    {
        $rule = RecurrenceRule::create(
            originalText: 'every day',
            type: RecurrenceType::ABSOLUTE,
            interval: 'day',
        );

        $json = $rule->toJson();
        $decoded = json_decode($json, true);

        self::assertSame('every day', $decoded['originalText']);
        self::assertSame('absolute', $decoded['type']);
        self::assertSame('day', $decoded['interval']);
    }

    public function testFromJson(): void
    {
        $json = '{"originalText":"every week","type":"relative","interval":"week","count":2,"days":[1,3],"dayOfMonth":null,"monthOfYear":null,"time":"09:00","endDate":null}';

        $rule = RecurrenceRule::fromJson($json);

        self::assertSame('every week', $rule->originalText);
        self::assertSame(RecurrenceType::RELATIVE, $rule->type);
        self::assertSame('week', $rule->interval);
        self::assertSame(2, $rule->count);
        self::assertSame([1, 3], $rule->days);
        self::assertSame('09:00', $rule->time);
    }

    public function testRoundTrip(): void
    {
        $endDate = new \DateTimeImmutable('2026-12-31');
        $original = RecurrenceRule::create(
            originalText: 'every 2 weeks on Mon, Fri at 15:00 until Dec 31',
            type: RecurrenceType::ABSOLUTE,
            interval: 'week',
            count: 2,
            days: [1, 5],
            dayOfMonth: null,
            monthOfYear: null,
            time: '15:00',
            endDate: $endDate,
        );

        $json = $original->toJson();
        $restored = RecurrenceRule::fromJson($json);

        self::assertSame($original->originalText, $restored->originalText);
        self::assertSame($original->type, $restored->type);
        self::assertSame($original->interval, $restored->interval);
        self::assertSame($original->count, $restored->count);
        self::assertSame($original->days, $restored->days);
        self::assertSame($original->dayOfMonth, $restored->dayOfMonth);
        self::assertSame($original->monthOfYear, $restored->monthOfYear);
        self::assertSame($original->time, $restored->time);
        self::assertEquals(
            $original->endDate?->format('Y-m-d'),
            $restored->endDate?->format('Y-m-d')
        );
    }

    #[DataProvider('descriptionProvider')]
    public function testGetDescription(array $params, string $expectedContains): void
    {
        $rule = RecurrenceRule::create(...$params);
        $description = $rule->getDescription();

        self::assertStringContainsString($expectedContains, $description);
    }

    public static function descriptionProvider(): iterable
    {
        yield 'daily absolute' => [
            [
                'originalText' => 'every day',
                'type' => RecurrenceType::ABSOLUTE,
                'interval' => 'day',
            ],
            'Daily',
        ];
        yield 'weekly' => [
            [
                'originalText' => 'every week',
                'type' => RecurrenceType::ABSOLUTE,
                'interval' => 'week',
            ],
            'Weekly',
        ];
        yield 'monthly' => [
            [
                'originalText' => 'every month',
                'type' => RecurrenceType::ABSOLUTE,
                'interval' => 'month',
            ],
            'Monthly',
        ];
        yield 'yearly' => [
            [
                'originalText' => 'every year',
                'type' => RecurrenceType::ABSOLUTE,
                'interval' => 'year',
            ],
            'Yearly',
        ];
        yield 'every 2 weeks' => [
            [
                'originalText' => 'every 2 weeks',
                'type' => RecurrenceType::ABSOLUTE,
                'interval' => 'week',
                'count' => 2,
            ],
            'Every 2 weeks',
        ];
        yield 'with days' => [
            [
                'originalText' => 'every Mon, Wed',
                'type' => RecurrenceType::ABSOLUTE,
                'interval' => 'week',
                'days' => [1, 3],
            ],
            'on Mon, Wed',
        ];
        yield 'with day of month' => [
            [
                'originalText' => 'every 15th',
                'type' => RecurrenceType::ABSOLUTE,
                'interval' => 'month',
                'dayOfMonth' => 15,
            ],
            'on the 15th',
        ];
        yield 'last day of month' => [
            [
                'originalText' => 'every last day',
                'type' => RecurrenceType::ABSOLUTE,
                'interval' => 'month',
                'dayOfMonth' => -1,
            ],
            'on the last day',
        ];
        yield 'with time' => [
            [
                'originalText' => 'every day at 10:00',
                'type' => RecurrenceType::ABSOLUTE,
                'interval' => 'day',
                'time' => '10:00',
            ],
            'at 10:00',
        ];
        yield 'relative type' => [
            [
                'originalText' => 'every! day',
                'type' => RecurrenceType::RELATIVE,
                'interval' => 'day',
            ],
            '(from completion)',
        ];
    }

    public function testOrdinalSuffixes(): void
    {
        $testCases = [
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            4 => '4th',
            11 => '11th',
            12 => '12th',
            13 => '13th',
            21 => '21st',
            22 => '22nd',
            23 => '23rd',
            31 => '31st',
        ];

        foreach ($testCases as $day => $expected) {
            $rule = RecurrenceRule::create(
                originalText: "every {$expected}",
                type: RecurrenceType::ABSOLUTE,
                interval: 'month',
                dayOfMonth: $day,
            );

            self::assertStringContainsString($expected, $rule->getDescription());
        }
    }
}
