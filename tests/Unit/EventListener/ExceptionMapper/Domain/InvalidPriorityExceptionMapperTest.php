<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\ExceptionMapper\Domain;

use App\EventListener\ExceptionMapper\Domain\InvalidPriorityExceptionMapper;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\Exception\InvalidPriorityException;
use PHPUnit\Framework\TestCase;

class InvalidPriorityExceptionMapperTest extends TestCase
{
    private InvalidPriorityExceptionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new InvalidPriorityExceptionMapper();
    }

    public function testCanHandleReturnsTrueForInvalidPriorityException(): void
    {
        $exception = new InvalidPriorityException(10);
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsTrueForForTaskPriority(): void
    {
        $exception = InvalidPriorityException::forTaskPriority(-5);
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsFalseForOtherExceptions(): void
    {
        $this->assertFalse($this->mapper->canHandle(new \Exception()));
        $this->assertFalse($this->mapper->canHandle(new \RuntimeException()));
        $this->assertFalse($this->mapper->canHandle(new \InvalidArgumentException()));
    }

    public function testMapReturnsCorrectMapping(): void
    {
        $exception = new InvalidPriorityException(10, 0, 4);

        $mapping = $this->mapper->map($exception);

        $this->assertInstanceOf(ExceptionMapping::class, $mapping);
        $this->assertSame('INVALID_PRIORITY', $mapping->errorCode);
        $this->assertSame('Invalid priority 10. Priority must be between 0 and 4', $mapping->message);
        $this->assertSame(400, $mapping->statusCode);
        $this->assertSame([
            'invalidPriority' => 10,
            'minPriority' => 0,
            'maxPriority' => 4,
        ], $mapping->details);
    }

    public function testMapWithNegativePriority(): void
    {
        $exception = new InvalidPriorityException(-1, 0, 4);

        $mapping = $this->mapper->map($exception);

        $this->assertSame('INVALID_PRIORITY', $mapping->errorCode);
        $this->assertSame(400, $mapping->statusCode);
        $this->assertSame([
            'invalidPriority' => -1,
            'minPriority' => 0,
            'maxPriority' => 4,
        ], $mapping->details);
    }

    public function testMapWithCustomRange(): void
    {
        $exception = new InvalidPriorityException(100, 1, 10);

        $mapping = $this->mapper->map($exception);

        $this->assertSame('Invalid priority 100. Priority must be between 1 and 10', $mapping->message);
        $this->assertSame([
            'invalidPriority' => 100,
            'minPriority' => 1,
            'maxPriority' => 10,
        ], $mapping->details);
    }

    public function testPriorityIs100(): void
    {
        $this->assertSame(100, InvalidPriorityExceptionMapper::getPriority());
    }
}
