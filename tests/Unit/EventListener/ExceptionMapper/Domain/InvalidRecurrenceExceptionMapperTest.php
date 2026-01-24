<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\ExceptionMapper\Domain;

use App\EventListener\ExceptionMapper\Domain\InvalidRecurrenceExceptionMapper;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\Exception\InvalidRecurrenceException;
use PHPUnit\Framework\TestCase;

class InvalidRecurrenceExceptionMapperTest extends TestCase
{
    private InvalidRecurrenceExceptionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new InvalidRecurrenceExceptionMapper();
    }

    public function testCanHandleReturnsTrueForInvalidRecurrenceException(): void
    {
        $exception = new InvalidRecurrenceException('Invalid rule');
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsTrueForForRecurrenceType(): void
    {
        $exception = InvalidRecurrenceException::forRecurrenceType('invalid');
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsFalseForOtherExceptions(): void
    {
        $this->assertFalse($this->mapper->canHandle(new \Exception()));
        $this->assertFalse($this->mapper->canHandle(new \RuntimeException()));
        $this->assertFalse($this->mapper->canHandle(new \InvalidArgumentException()));
    }

    public function testMapReturnsCorrectMappingWithReasonOnly(): void
    {
        $exception = new InvalidRecurrenceException('Missing end date');

        $mapping = $this->mapper->map($exception);

        $this->assertInstanceOf(ExceptionMapping::class, $mapping);
        $this->assertSame('INVALID_RECURRENCE', $mapping->errorCode);
        $this->assertSame('Missing end date', $mapping->message);
        $this->assertSame(400, $mapping->statusCode);
        $this->assertSame(['reason' => 'Missing end date'], $mapping->details);
    }

    public function testMapIncludesInvalidValueWhenPresent(): void
    {
        $exception = new InvalidRecurrenceException(
            reason: 'Invalid recurrence rule',
            invalidValue: 'FREQ=INVALID',
        );

        $mapping = $this->mapper->map($exception);

        $this->assertSame('INVALID_RECURRENCE', $mapping->errorCode);
        $this->assertSame(400, $mapping->statusCode);
        $this->assertSame([
            'reason' => 'Invalid recurrence rule',
            'invalidValue' => 'FREQ=INVALID',
        ], $mapping->details);
    }

    public function testMapIncludesValidValuesWhenPresent(): void
    {
        $exception = new InvalidRecurrenceException(
            reason: 'Invalid recurrence type',
            invalidValue: null,
            validValues: ['daily', 'weekly', 'monthly'],
        );

        $mapping = $this->mapper->map($exception);

        $this->assertSame('INVALID_RECURRENCE', $mapping->errorCode);
        $this->assertSame([
            'reason' => 'Invalid recurrence type',
            'validValues' => ['daily', 'weekly', 'monthly'],
        ], $mapping->details);
    }

    public function testMapIncludesBothInvalidValueAndValidValues(): void
    {
        $exception = InvalidRecurrenceException::forRecurrenceType('yearly');

        $mapping = $this->mapper->map($exception);

        $this->assertSame('INVALID_RECURRENCE', $mapping->errorCode);
        $this->assertSame(400, $mapping->statusCode);
        $this->assertArrayHasKey('reason', $mapping->details);
        $this->assertSame('yearly', $mapping->details['invalidValue']);
        $this->assertSame(['absolute', 'relative'], $mapping->details['validValues']);
    }

    public function testMapForRecurrenceRule(): void
    {
        $exception = InvalidRecurrenceException::forRecurrenceRule('FREQ=WEEKLY;INVALID');

        $mapping = $this->mapper->map($exception);

        $this->assertSame('INVALID_RECURRENCE', $mapping->errorCode);
        $this->assertSame('FREQ=WEEKLY;INVALID', $mapping->details['invalidValue']);
        $this->assertArrayNotHasKey('validValues', $mapping->details);
    }

    public function testMapForMissingConfiguration(): void
    {
        $exception = InvalidRecurrenceException::missingConfiguration('recurrence_end_date');

        $mapping = $this->mapper->map($exception);

        $this->assertSame('INVALID_RECURRENCE', $mapping->errorCode);
        $this->assertSame(['reason' => 'Recurrence configuration requires recurrence_end_date to be set'], $mapping->details);
    }

    public function testPriorityIs100(): void
    {
        $this->assertSame(100, InvalidRecurrenceExceptionMapper::getPriority());
    }
}
