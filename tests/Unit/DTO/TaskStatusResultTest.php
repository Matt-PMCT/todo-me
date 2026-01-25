<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\TaskStatusResult;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaskStatusResult::class)]
final class TaskStatusResultTest extends TestCase
{
    private Task $task;
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();

        $this->task = $this->createMock(Task::class);
        $this->task->method('getId')->willReturn('550e8400-e29b-41d4-a716-446655440000');
        $this->task->method('getOwner')->willReturn($this->user);
        $this->task->method('getTitle')->willReturn('Test task');
        $this->task->method('getDescription')->willReturn(null);
        $this->task->method('getStatus')->willReturn(Task::STATUS_COMPLETED);
        $this->task->method('getPriority')->willReturn(2);
        $this->task->method('getDueDate')->willReturn(null);
        $this->task->method('getPosition')->willReturn(0);
        $this->task->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2026-01-15T10:00:00+00:00'));
        $this->task->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2026-01-15T10:00:00+00:00'));
        $this->task->method('getCompletedAt')->willReturn(new \DateTimeImmutable('2026-01-15T10:00:00+00:00'));
        $this->task->method('getProject')->willReturn(null);
        $this->task->method('getTags')->willReturn(new ArrayCollection());
        $this->task->method('isRecurring')->willReturn(false);
        $this->task->method('getRecurrenceRule')->willReturn(null);
        $this->task->method('getRecurrenceType')->willReturn(null);
        $this->task->method('getRecurrenceEndDate')->willReturn(null);
        $this->task->method('getOriginalTask')->willReturn(null);
    }

    public function testConstructorWithTask(): void
    {
        $result = new TaskStatusResult($this->task);

        self::assertSame($this->task, $result->task);
        self::assertNull($result->nextTask);
        self::assertNull($result->undoToken);
    }

    public function testConstructorWithNextTask(): void
    {
        $nextTask = $this->createMock(Task::class);

        $result = new TaskStatusResult($this->task, $nextTask);

        self::assertSame($this->task, $result->task);
        self::assertSame($nextTask, $result->nextTask);
    }

    public function testConstructorWithUndoToken(): void
    {
        $result = new TaskStatusResult($this->task, null, 'abc123');

        self::assertSame('abc123', $result->undoToken);
    }

    public function testHasNextTaskReturnsFalseWhenNoNextTask(): void
    {
        $result = new TaskStatusResult($this->task);

        self::assertFalse($result->hasNextTask());
    }

    public function testHasNextTaskReturnsTrueWhenNextTaskExists(): void
    {
        $nextTask = $this->createMock(Task::class);

        $result = new TaskStatusResult($this->task, $nextTask);

        self::assertTrue($result->hasNextTask());
    }

    public function testToArrayWithoutNextTask(): void
    {
        $result = new TaskStatusResult($this->task, null, 'token123');

        $array = $result->toArray();

        self::assertSame('Test task', $array['title']);
        self::assertSame(Task::STATUS_COMPLETED, $array['status']);
        self::assertSame('token123', $array['undoToken']);
        self::assertArrayNotHasKey('nextTask', $array);
    }

    public function testToArrayWithNextTask(): void
    {
        $nextTask = $this->createMock(Task::class);
        $nextTask->method('getId')->willReturn('650e8400-e29b-41d4-a716-446655440001');
        $nextTask->method('getOwner')->willReturn($this->user);
        $nextTask->method('getTitle')->willReturn('Next recurring task');
        $nextTask->method('getDescription')->willReturn(null);
        $nextTask->method('getStatus')->willReturn(Task::STATUS_PENDING);
        $nextTask->method('getPriority')->willReturn(2);
        $nextTask->method('getDueDate')->willReturn(null);
        $nextTask->method('getPosition')->willReturn(0);
        $nextTask->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2026-01-16T10:00:00+00:00'));
        $nextTask->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2026-01-16T10:00:00+00:00'));
        $nextTask->method('getCompletedAt')->willReturn(null);
        $nextTask->method('getProject')->willReturn(null);
        $nextTask->method('getTags')->willReturn(new ArrayCollection());
        $nextTask->method('isRecurring')->willReturn(true);
        $nextTask->method('getRecurrenceRule')->willReturn('every day');
        $nextTask->method('getRecurrenceType')->willReturn('absolute');
        $nextTask->method('getRecurrenceEndDate')->willReturn(null);
        $nextTask->method('getOriginalTask')->willReturn(null);

        $result = new TaskStatusResult($this->task, $nextTask, 'token123');

        $array = $result->toArray();

        self::assertSame('Test task', $array['title']);
        self::assertArrayHasKey('nextTask', $array);
        self::assertSame('Next recurring task', $array['nextTask']['title']);
        self::assertSame(Task::STATUS_PENDING, $array['nextTask']['status']);
    }
}
