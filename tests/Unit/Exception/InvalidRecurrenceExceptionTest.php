<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Exception\InvalidRecurrenceException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidRecurrenceException::class)]
final class InvalidRecurrenceExceptionTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $exception = new InvalidRecurrenceException(
            message: 'Test message',
            pattern: 'every invalid',
            errorCode: 'TEST_ERROR',
        );

        self::assertSame('Test message', $exception->getMessage());
        self::assertSame('every invalid', $exception->pattern);
        self::assertSame('TEST_ERROR', $exception->errorCode);
        self::assertSame(400, $exception->getStatusCode());
    }

    public function testInvalidPattern(): void
    {
        $exception = InvalidRecurrenceException::invalidPattern('every foo bar');

        self::assertSame('Cannot parse recurrence pattern: "every foo bar"', $exception->getMessage());
        self::assertSame('every foo bar', $exception->getPattern());
        self::assertSame('INVALID_RECURRENCE_PATTERN', $exception->errorCode);
        self::assertSame(400, $exception->getStatusCode());
    }

    public function testInvalidDate(): void
    {
        $exception = InvalidRecurrenceException::invalidDate('every day until invalid-date', 'invalid-date');

        self::assertSame(
            'Invalid date "invalid-date" in recurrence pattern: "every day until invalid-date"',
            $exception->getMessage()
        );
        self::assertSame('every day until invalid-date', $exception->getPattern());
        self::assertSame('INVALID_RECURRENCE_DATE', $exception->errorCode);
        self::assertSame(400, $exception->getStatusCode());
    }

    public function testUnsupportedPatternWithoutReason(): void
    {
        $exception = InvalidRecurrenceException::unsupportedPattern('every century');

        self::assertSame('Unsupported recurrence pattern: "every century"', $exception->getMessage());
        self::assertSame('every century', $exception->getPattern());
        self::assertSame('UNSUPPORTED_RECURRENCE_PATTERN', $exception->errorCode);
    }

    public function testUnsupportedPatternWithReason(): void
    {
        $exception = InvalidRecurrenceException::unsupportedPattern(
            'every 32nd',
            'Day of month must be between 1 and 31'
        );

        self::assertSame(
            'Unsupported recurrence pattern: "every 32nd". Day of month must be between 1 and 31',
            $exception->getMessage()
        );
        self::assertSame('every 32nd', $exception->getPattern());
        self::assertSame('UNSUPPORTED_RECURRENCE_PATTERN', $exception->errorCode);
    }

    public function testRecurrenceEnded(): void
    {
        $exception = InvalidRecurrenceException::recurrenceEnded('every day until 2025-01-01');

        self::assertSame(
            'Recurrence has ended for pattern: "every day until 2025-01-01"',
            $exception->getMessage()
        );
        self::assertSame('every day until 2025-01-01', $exception->getPattern());
        self::assertSame('RECURRENCE_ENDED', $exception->errorCode);
    }

    public function testGetPattern(): void
    {
        $exception = new InvalidRecurrenceException(
            message: 'Test',
            pattern: 'test pattern',
        );

        self::assertSame('test pattern', $exception->getPattern());
    }

    public function testEmptyPatternAllowed(): void
    {
        $exception = new InvalidRecurrenceException(
            message: 'Generic error',
            pattern: '',
        );

        self::assertSame('', $exception->getPattern());
    }

    public function testDefaultErrorCode(): void
    {
        $exception = new InvalidRecurrenceException('Test message');

        self::assertSame('INVALID_RECURRENCE', $exception->errorCode);
    }

    public function testPreviousExceptionChaining(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new InvalidRecurrenceException(
            message: 'Test',
            pattern: '',
            errorCode: 'TEST',
            previous: $previous,
        );

        self::assertSame($previous, $exception->getPrevious());
    }
}
