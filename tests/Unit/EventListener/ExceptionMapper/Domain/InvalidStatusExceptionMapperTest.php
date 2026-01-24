<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\ExceptionMapper\Domain;

use App\EventListener\ExceptionMapper\Domain\InvalidStatusExceptionMapper;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\Exception\InvalidStatusException;
use PHPUnit\Framework\TestCase;

class InvalidStatusExceptionMapperTest extends TestCase
{
    private InvalidStatusExceptionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new InvalidStatusExceptionMapper();
    }

    public function testCanHandleReturnsTrueForInvalidStatusException(): void
    {
        $exception = new InvalidStatusException('invalid', ['pending', 'completed']);
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsFalseForOtherExceptions(): void
    {
        $this->assertFalse($this->mapper->canHandle(new \Exception()));
        $this->assertFalse($this->mapper->canHandle(new \RuntimeException()));
    }

    public function testMapReturnsCorrectMapping(): void
    {
        $exception = new InvalidStatusException('unknown', ['pending', 'completed']);

        $mapping = $this->mapper->map($exception);

        $this->assertInstanceOf(ExceptionMapping::class, $mapping);
        $this->assertSame('INVALID_STATUS', $mapping->errorCode);
        $this->assertSame(400, $mapping->statusCode);
        $this->assertSame('unknown', $mapping->details['invalidStatus']);
        $this->assertSame(['pending', 'completed'], $mapping->details['validStatuses']);
    }

    public function testPriorityIs100(): void
    {
        $this->assertSame(100, InvalidStatusExceptionMapper::getPriority());
    }
}
