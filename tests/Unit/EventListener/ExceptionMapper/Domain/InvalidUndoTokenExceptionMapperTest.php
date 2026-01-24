<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\ExceptionMapper\Domain;

use App\EventListener\ExceptionMapper\Domain\InvalidUndoTokenExceptionMapper;
use App\Exception\InvalidUndoTokenException;
use PHPUnit\Framework\TestCase;

class InvalidUndoTokenExceptionMapperTest extends TestCase
{
    private InvalidUndoTokenExceptionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new InvalidUndoTokenExceptionMapper();
    }

    public function testGetPriority(): void
    {
        $this->assertSame(100, InvalidUndoTokenExceptionMapper::getPriority());
    }

    public function testCanHandleReturnsTrueForInvalidUndoTokenException(): void
    {
        $exception = InvalidUndoTokenException::expired();
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsFalseForOtherExceptions(): void
    {
        $exception = new \InvalidArgumentException('test');
        $this->assertFalse($this->mapper->canHandle($exception));
    }

    public function testMapReturnsCorrectMappingForExpired(): void
    {
        $exception = InvalidUndoTokenException::expired();
        $mapping = $this->mapper->map($exception);

        $this->assertSame('INVALID_UNDO_TOKEN', $mapping->errorCode);
        $this->assertSame('Invalid or expired undo token', $mapping->message);
        $this->assertSame(400, $mapping->statusCode);
    }

    public function testMapReturnsCorrectMappingForWrongEntityType(): void
    {
        $exception = InvalidUndoTokenException::wrongEntityType('task', 'project');
        $mapping = $this->mapper->map($exception);

        $this->assertSame('INVALID_UNDO_TOKEN', $mapping->errorCode);
        $this->assertSame('Undo token is for a project, not a task', $mapping->message);
        $this->assertSame(400, $mapping->statusCode);
    }

    public function testMapReturnsCorrectMappingForWrongActionType(): void
    {
        $exception = InvalidUndoTokenException::wrongActionType('delete');
        $mapping = $this->mapper->map($exception);

        $this->assertSame('INVALID_UNDO_TOKEN', $mapping->errorCode);
        $this->assertSame('Undo token is not for a delete operation', $mapping->message);
        $this->assertSame(400, $mapping->statusCode);
    }

    public function testMapReturnsCorrectMappingForUnknownAction(): void
    {
        $exception = InvalidUndoTokenException::unknownAction('foo');
        $mapping = $this->mapper->map($exception);

        $this->assertSame('INVALID_UNDO_TOKEN', $mapping->errorCode);
        $this->assertSame('Unknown undo action type: foo', $mapping->message);
        $this->assertSame(400, $mapping->statusCode);
    }
}
