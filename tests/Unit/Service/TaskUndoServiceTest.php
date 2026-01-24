<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Enum\UndoAction;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidStateException;
use App\Exception\ValidationException;
use App\Repository\TaskRepository;
use App\Service\OwnershipChecker;
use App\Service\TaskStateService;
use App\Service\TaskUndoService;
use App\Service\UndoService;
use App\Tests\Unit\UnitTestCase;
use App\ValueObject\UndoToken;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class TaskUndoServiceTest extends UnitTestCase
{
    private TaskUndoService $service;
    private UndoService&MockObject $undoService;
    private TaskRepository&MockObject $taskRepository;
    private TaskStateService&MockObject $taskStateService;
    private EntityManagerInterface&MockObject $entityManager;
    private OwnershipChecker&MockObject $ownershipChecker;

    protected function setUp(): void
    {
        $this->undoService = $this->createMock(UndoService::class);
        $this->taskRepository = $this->createMock(TaskRepository::class);
        $this->taskStateService = $this->createMock(TaskStateService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->ownershipChecker = $this->createMock(OwnershipChecker::class);

        $this->service = new TaskUndoService(
            $this->undoService,
            $this->taskRepository,
            $this->taskStateService,
            $this->entityManager,
            $this->ownershipChecker,
        );
    }

    public function testCreateUpdateUndoTokenSuccess(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-123', $user);
        $previousState = ['title' => 'Old Title'];

        $undoToken = new UndoToken(
            token: 'undo-token-123',
            userId: 'user-123',
            action: UndoAction::UPDATE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: $previousState,
            expiresAt: new \DateTimeImmutable('+60 seconds'),
        );

        $this->undoService
            ->expects($this->once())
            ->method('createUndoToken')
            ->with(
                'user-123',
                UndoAction::UPDATE->value,
                'task',
                'task-123',
                $previousState
            )
            ->willReturn($undoToken);

        $result = $this->service->createUpdateUndoToken($task, $previousState);

        $this->assertSame('undo-token-123', $result);
    }

    public function testCreateUpdateUndoTokenThrowsOnMissingOwner(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        // No owner set

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('owner');

        $this->service->createUpdateUndoToken($task, []);
    }

    public function testCreateDeleteUndoTokenSuccess(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-123', $user);
        $serializedState = ['title' => 'Test Task'];

        $undoToken = new UndoToken(
            token: 'undo-token-123',
            userId: 'user-123',
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: $serializedState,
            expiresAt: new \DateTimeImmutable('+60 seconds'),
        );

        $this->taskStateService
            ->expects($this->once())
            ->method('serializeTaskState')
            ->with($task)
            ->willReturn($serializedState);

        $this->undoService
            ->expects($this->once())
            ->method('createUndoToken')
            ->willReturn($undoToken);

        $result = $this->service->createDeleteUndoToken($task);

        $this->assertSame($undoToken, $result);
    }

    public function testCreateStatusChangeUndoTokenSuccess(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-123', $user);
        $previousState = ['status' => Task::STATUS_PENDING];

        $undoToken = new UndoToken(
            token: 'undo-token-123',
            userId: 'user-123',
            action: UndoAction::STATUS_CHANGE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: $previousState,
            expiresAt: new \DateTimeImmutable('+60 seconds'),
        );

        $this->undoService
            ->expects($this->once())
            ->method('createUndoToken')
            ->with(
                'user-123',
                UndoAction::STATUS_CHANGE->value,
                'task',
                'task-123',
                $previousState
            )
            ->willReturn($undoToken);

        $result = $this->service->createStatusChangeUndoToken($task, $previousState);

        $this->assertSame('undo-token-123', $result);
    }

    public function testUndoWithInvalidToken(): void
    {
        $user = $this->createUserWithId('user-123');

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', 'invalid-token')
            ->willReturn(null);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid or expired undo token');

        $this->service->undo($user, 'invalid-token');
    }

    public function testUndoWithNonTaskToken(): void
    {
        $user = $this->createUserWithId('user-123');

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::DELETE->value,
            entityType: 'project', // Not a task
            entityId: 'project-123',
            previousState: [],
            expiresAt: new \DateTimeImmutable('+60 seconds'),
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Token is not for a task');

        $this->service->undo($user, 'token-123');
    }

    public function testUndoDeleteRestoresTask(): void
    {
        $user = $this->createUserWithId('user-123');
        $previousState = ['title' => 'Deleted Task'];
        $restoredTask = $this->createTaskWithId('task-123', $user, 'Deleted Task');

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: $previousState,
            expiresAt: new \DateTimeImmutable('+60 seconds'),
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->taskStateService
            ->expects($this->once())
            ->method('restoreTaskFromState')
            ->with($user, $previousState)
            ->willReturn($restoredTask);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($restoredTask);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->undo($user, 'token-123');

        $this->assertSame($restoredTask, $result);
    }

    public function testUndoUpdateRestoresTask(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-123', $user, 'Current Title');
        $previousState = ['title' => 'Previous Title'];

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::UPDATE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: $previousState,
            expiresAt: new \DateTimeImmutable('+60 seconds'),
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->taskRepository
            ->expects($this->once())
            ->method('find')
            ->with('task-123')
            ->willReturn($task);

        $this->ownershipChecker
            ->expects($this->once())
            ->method('checkOwnership')
            ->with($task);

        $this->taskStateService
            ->expects($this->once())
            ->method('applyStateToTask')
            ->with($task, $previousState);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->undo($user, 'token-123');

        $this->assertSame($task, $result);
    }

    public function testUndoUpdateThrowsOnMissingTask(): void
    {
        $user = $this->createUserWithId('user-123');

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::UPDATE->value,
            entityType: 'task',
            entityId: 'non-existent-task',
            previousState: [],
            expiresAt: new \DateTimeImmutable('+60 seconds'),
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->taskRepository
            ->expects($this->once())
            ->method('find')
            ->with('non-existent-task')
            ->willReturn(null);

        $this->expectException(EntityNotFoundException::class);

        $this->service->undo($user, 'token-123');
    }

    public function testUndoDeleteValidatesActionType(): void
    {
        $user = $this->createUserWithId('user-123');

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::UPDATE->value, // Not DELETE
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
            expiresAt: new \DateTimeImmutable('+60 seconds'),
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Token is not for a delete operation');

        $this->service->undoDelete($user, 'token-123');
    }

    public function testUndoUpdateValidatesActionType(): void
    {
        $user = $this->createUserWithId('user-123');

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::DELETE->value, // Not UPDATE
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
            expiresAt: new \DateTimeImmutable('+60 seconds'),
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Token is not for an update operation');

        $this->service->undoUpdate($user, 'token-123');
    }

    public function testUndoUpdateAcceptsStatusChangeAction(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-123', $user);
        $previousState = ['status' => Task::STATUS_PENDING];

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::STATUS_CHANGE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: $previousState,
            expiresAt: new \DateTimeImmutable('+60 seconds'),
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->taskRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($task);

        $this->ownershipChecker
            ->expects($this->once())
            ->method('checkOwnership');

        $this->taskStateService
            ->expects($this->once())
            ->method('applyStateToTask');

        $result = $this->service->undoUpdate($user, 'token-123');

        $this->assertSame($task, $result);
    }
}
