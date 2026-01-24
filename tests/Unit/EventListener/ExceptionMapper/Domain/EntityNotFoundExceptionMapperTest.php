<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\ExceptionMapper\Domain;

use App\EventListener\ExceptionMapper\Domain\EntityNotFoundExceptionMapper;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\Exception\EntityNotFoundException;
use PHPUnit\Framework\TestCase;

class EntityNotFoundExceptionMapperTest extends TestCase
{
    private EntityNotFoundExceptionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new EntityNotFoundExceptionMapper();
    }

    public function testCanHandleReturnsTrueForEntityNotFoundException(): void
    {
        $exception = new EntityNotFoundException('Task', 'task-123');
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsFalseForOtherExceptions(): void
    {
        $this->assertFalse($this->mapper->canHandle(new \Exception()));
        $this->assertFalse($this->mapper->canHandle(new \RuntimeException()));
    }

    public function testMapReturnsCorrectMapping(): void
    {
        $exception = new EntityNotFoundException('Task', 'task-123');

        $mapping = $this->mapper->map($exception);

        $this->assertInstanceOf(ExceptionMapping::class, $mapping);
        $this->assertSame('RESOURCE_NOT_FOUND', $mapping->errorCode);
        $this->assertSame(404, $mapping->statusCode);
        $this->assertSame('Task', $mapping->details['entityType']);
        $this->assertSame('task-123', $mapping->details['entityId']);
    }

    public function testPriorityIs100(): void
    {
        $this->assertSame(100, EntityNotFoundExceptionMapper::getPriority());
    }
}
