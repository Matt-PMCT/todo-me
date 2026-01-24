<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\RecurrenceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecurrenceType::class)]
final class RecurrenceTypeTest extends TestCase
{
    public function testAbsoluteValue(): void
    {
        self::assertSame('absolute', RecurrenceType::ABSOLUTE->value);
    }

    public function testRelativeValue(): void
    {
        self::assertSame('relative', RecurrenceType::RELATIVE->value);
    }

    public function testAbsoluteLabel(): void
    {
        self::assertSame('Absolute (from schedule)', RecurrenceType::ABSOLUTE->getLabel());
    }

    public function testRelativeLabel(): void
    {
        self::assertSame('Relative (from completion)', RecurrenceType::RELATIVE->getLabel());
    }

    public function testAbsoluteDescription(): void
    {
        self::assertSame(
            'Next occurrence calculated from the original schedule',
            RecurrenceType::ABSOLUTE->getDescription()
        );
    }

    public function testRelativeDescription(): void
    {
        self::assertSame(
            'Next occurrence calculated from when the task is completed',
            RecurrenceType::RELATIVE->getDescription()
        );
    }

    #[DataProvider('validTypesProvider')]
    public function testIsValidReturnsTrueForValidTypes(string $type): void
    {
        self::assertTrue(RecurrenceType::isValid($type));
    }

    public static function validTypesProvider(): iterable
    {
        yield 'absolute' => ['absolute'];
        yield 'relative' => ['relative'];
    }

    #[DataProvider('invalidTypesProvider')]
    public function testIsValidReturnsFalseForInvalidTypes(string $type): void
    {
        self::assertFalse(RecurrenceType::isValid($type));
    }

    public static function invalidTypesProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'invalid' => ['invalid'];
        yield 'uppercase' => ['ABSOLUTE'];
        yield 'mixed case' => ['Relative'];
    }

    public function testValues(): void
    {
        $values = RecurrenceType::values();

        self::assertCount(2, $values);
        self::assertContains('absolute', $values);
        self::assertContains('relative', $values);
    }

    public function testTryFromValidValue(): void
    {
        self::assertSame(RecurrenceType::ABSOLUTE, RecurrenceType::tryFrom('absolute'));
        self::assertSame(RecurrenceType::RELATIVE, RecurrenceType::tryFrom('relative'));
    }

    public function testTryFromInvalidValue(): void
    {
        self::assertNull(RecurrenceType::tryFrom('invalid'));
    }
}
