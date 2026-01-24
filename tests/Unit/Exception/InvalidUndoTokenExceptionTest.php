<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Exception\InvalidUndoTokenException;
use PHPUnit\Framework\TestCase;

class InvalidUndoTokenExceptionTest extends TestCase
{
    public function testExpiredFactory(): void
    {
        $exception = InvalidUndoTokenException::expired();

        $this->assertSame('Invalid or expired undo token', $exception->getMessage());
        $this->assertSame(400, $exception->getStatusCode());
        $this->assertSame('INVALID_UNDO_TOKEN', $exception->errorCode);
    }

    public function testWrongEntityTypeFactory(): void
    {
        $exception = InvalidUndoTokenException::wrongEntityType('task', 'project');

        $this->assertSame('Undo token is for a project, not a task', $exception->getMessage());
        $this->assertSame(400, $exception->getStatusCode());
        $this->assertSame('INVALID_UNDO_TOKEN', $exception->errorCode);
    }

    public function testWrongActionTypeFactory(): void
    {
        $exception = InvalidUndoTokenException::wrongActionType('delete');

        $this->assertSame('Undo token is not for a delete operation', $exception->getMessage());
        $this->assertSame(400, $exception->getStatusCode());
        $this->assertSame('INVALID_UNDO_TOKEN', $exception->errorCode);
    }

    public function testUnknownActionFactory(): void
    {
        $exception = InvalidUndoTokenException::unknownAction('invalid_action');

        $this->assertSame('Unknown undo action type: invalid_action', $exception->getMessage());
        $this->assertSame(400, $exception->getStatusCode());
        $this->assertSame('INVALID_UNDO_TOKEN', $exception->errorCode);
    }
}
