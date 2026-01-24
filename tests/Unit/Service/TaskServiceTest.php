<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\CreateTaskRequest;
use App\DTO\UpdateTaskRequest;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\UndoAction;
use App\Exception\EntityNotFoundException;
use App\Exception\ForbiddenException;
use App\Exception\ValidationException;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;
use App\Service\OwnershipChecker;
use App\Service\Parser\NaturalLanguageParserService;
use App\Service\TaskService;
use App\Service\UndoService;
use App\Service\ValidationHelper;
use App\Tests\Unit\UnitTestCase;
use App\ValueObject\UndoToken;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class TaskServiceTest extends UnitTestCase
{
    private TaskRepository&MockObject $taskRepository;
    private ProjectRepository&MockObject $projectRepository;
    private TagRepository&MockObject $tagRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private UndoService&MockObject $undoService;
    private ValidationHelper&MockObject $validationHelper;
    private OwnershipChecker&MockObject $ownershipChecker;
    private NaturalLanguageParserService&MockObject $naturalLanguageParser;
    private TaskService $taskService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->taskRepository = $this->createMock(TaskRepository::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->undoService = $this->createMock(UndoService::class);
        $this->validationHelper = $this->createMock(ValidationHelper::class);
        $this->ownershipChecker = $this->createMock(OwnershipChecker::class);
        $this->naturalLanguageParser = $this->createMock(NaturalLanguageParserService::class);

        $this->taskService = new TaskService(
            $this->taskRepository,
            $this->projectRepository,
            $this->tagRepository,
            $this->entityManager,
            $this->undoService,
            $this->validationHelper,
            $this->ownershipChecker,
            $this->naturalLanguageParser,
        );
    }

    // ========================================
    // Create Task Tests
    // ========================================

    public function testCreateTaskWithMinimalData(): void
    {
        $user = $this->createUserWithId();
        $dto = new CreateTaskRequest(
            title: 'Test Task',
        );

        $this->validationHelper->expects($this->once())
            ->method('validate')
            ->with($dto);

        $this->validationHelper->expects($this->once())
            ->method('validateTaskStatus')
            ->with(Task::STATUS_PENDING);

        $this->validationHelper->expects($this->once())
            ->method('validateTaskPriority')
            ->with(Task::PRIORITY_DEFAULT);

        $this->taskRepository->expects($this->once())
            ->method('getMaxPosition')
            ->with($user, null)
            ->willReturn(0);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Task::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $task = $this->taskService->create($user, $dto);

        $this->assertEquals('Test Task', $task->getTitle());
        $this->assertEquals(Task::STATUS_PENDING, $task->getStatus());
        $this->assertEquals(Task::PRIORITY_DEFAULT, $task->getPriority());
        $this->assertEquals(1, $task->getPosition());
        $this->assertSame($user, $task->getOwner());
        $this->assertNull($task->getProject());
    }

    public function testCreateTaskWithAllFields(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);

        $dto = new CreateTaskRequest(
            title: 'Full Task',
            description: 'A complete task with all fields',
            status: Task::STATUS_IN_PROGRESS,
            priority: 4,
            dueDate: '2024-12-31',
            projectId: 'project-123',
        );

        $this->validationHelper->expects($this->once())
            ->method('validate')
            ->with($dto);

        $this->validationHelper->expects($this->once())
            ->method('validateTaskStatus')
            ->with(Task::STATUS_IN_PROGRESS);

        $this->validationHelper->expects($this->once())
            ->method('validateTaskPriority')
            ->with(4);

        $this->projectRepository->expects($this->once())
            ->method('find')
            ->with('project-123')
            ->willReturn($project);

        $this->ownershipChecker->expects($this->once())
            ->method('checkOwnership')
            ->with($project);

        $this->taskRepository->expects($this->once())
            ->method('getMaxPosition')
            ->with($user, $project)
            ->willReturn(5);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Task::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $task = $this->taskService->create($user, $dto);

        $this->assertEquals('Full Task', $task->getTitle());
        $this->assertEquals('A complete task with all fields', $task->getDescription());
        $this->assertEquals(Task::STATUS_IN_PROGRESS, $task->getStatus());
        $this->assertEquals(4, $task->getPriority());
        $this->assertEquals(6, $task->getPosition());
        $this->assertSame($project, $task->getProject());
    }

    public function testCreateTaskWithNonExistentProjectThrowsException(): void
    {
        $user = $this->createUserWithId();
        $dto = new CreateTaskRequest(
            title: 'Task',
            projectId: 'non-existent-project',
        );

        $this->validationHelper->method('validate');
        $this->validationHelper->method('validateTaskStatus');
        $this->validationHelper->method('validateTaskPriority');

        $this->projectRepository->expects($this->once())
            ->method('find')
            ->with('non-existent-project')
            ->willReturn(null);

        $this->expectException(EntityNotFoundException::class);
        $this->taskService->create($user, $dto);
    }

    public function testCreateTaskWithUnownedProjectThrowsForbiddenException(): void
    {
        $user = $this->createUserWithId();
        $otherUser = $this->createUserWithId('other-user', 'other@example.com');
        $project = $this->createProjectWithId('project-123', $otherUser);

        $dto = new CreateTaskRequest(
            title: 'Task',
            projectId: 'project-123',
        );

        $this->validationHelper->method('validate');
        $this->validationHelper->method('validateTaskStatus');
        $this->validationHelper->method('validateTaskPriority');

        $this->projectRepository->expects($this->once())
            ->method('find')
            ->with('project-123')
            ->willReturn($project);

        $this->ownershipChecker->expects($this->once())
            ->method('checkOwnership')
            ->with($project)
            ->willThrowException(ForbiddenException::notOwner('Project'));

        $this->expectException(ForbiddenException::class);
        $this->taskService->create($user, $dto);
    }

    public function testCreateTaskSetsPositionCorrectly(): void
    {
        $user = $this->createUserWithId();
        $dto = new CreateTaskRequest(title: 'Task');

        $this->validationHelper->method('validate');
        $this->validationHelper->method('validateTaskStatus');
        $this->validationHelper->method('validateTaskPriority');

        $this->taskRepository->expects($this->once())
            ->method('getMaxPosition')
            ->with($user, null)
            ->willReturn(10);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $task = $this->taskService->create($user, $dto);

        $this->assertEquals(11, $task->getPosition());
    }

    // ========================================
    // Update Task Tests
    // ========================================

    public function testUpdateTaskCreatesUndoToken(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $dto = new UpdateTaskRequest(title: 'Updated Title');

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
            userId: 'user-123',
        );

        $this->validationHelper->expects($this->once())
            ->method('validate')
            ->with($dto);

        $this->undoService->expects($this->once())
            ->method('createUndoToken')
            ->with(
                'user-123',
                UndoAction::UPDATE->value,
                'task',
                'task-123',
                $this->isType('array')
            )
            ->willReturn($undoToken);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->taskService->update($task, $dto);

        $this->assertArrayHasKey('task', $result);
        $this->assertArrayHasKey('undoToken', $result);
        $this->assertEquals($undoToken->token, $result['undoToken']);
    }

    public function testUpdateTaskModifiesTitle(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user, 'Original Title');

        $dto = new UpdateTaskRequest(title: 'Updated Title');

        $this->validationHelper->method('validate');
        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');

        $result = $this->taskService->update($task, $dto);

        $this->assertEquals('Updated Title', $result['task']->getTitle());
    }

    public function testUpdateTaskModifiesDescription(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);
        $task->setDescription('Original Description');

        $dto = new UpdateTaskRequest(description: 'New Description');

        $this->validationHelper->method('validate');
        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');

        $result = $this->taskService->update($task, $dto);

        $this->assertEquals('New Description', $result['task']->getDescription());
    }

    public function testUpdateTaskClearsDescription(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);
        $task->setDescription('Original Description');

        $dto = new UpdateTaskRequest(clearDescription: true);

        $this->validationHelper->method('validate');
        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');

        $result = $this->taskService->update($task, $dto);

        $this->assertNull($result['task']->getDescription());
    }

    public function testUpdateTaskValidatesStatus(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $dto = new UpdateTaskRequest(status: Task::STATUS_COMPLETED);

        $this->validationHelper->expects($this->once())
            ->method('validate')
            ->with($dto);

        $this->validationHelper->expects($this->once())
            ->method('validateTaskStatus')
            ->with(Task::STATUS_COMPLETED);

        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');

        $this->taskService->update($task, $dto);
    }

    public function testUpdateTaskValidatesPriority(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $dto = new UpdateTaskRequest(priority: 4);

        $this->validationHelper->expects($this->once())
            ->method('validate')
            ->with($dto);

        $this->validationHelper->expects($this->once())
            ->method('validateTaskPriority')
            ->with(4);

        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');

        $this->taskService->update($task, $dto);
    }

    // ========================================
    // Delete Task Tests
    // ========================================

    public function testDeleteTaskCreatesUndoToken(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('createUndoToken')
            ->with(
                'user-123',
                UndoAction::DELETE->value,
                'task',
                'task-123',
                $this->isType('array')
            )
            ->willReturn($undoToken);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($task);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->taskService->delete($task);

        $this->assertSame($undoToken, $result);
    }

    public function testDeleteTaskRemovesFromDatabase(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $this->undoService->method('createUndoToken');

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($task);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->taskService->delete($task);
    }

    // ========================================
    // Status Change Tests
    // ========================================

    public function testChangeStatusValidatesNewStatus(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $this->validationHelper->expects($this->once())
            ->method('validateTaskStatus')
            ->with(Task::STATUS_COMPLETED);

        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');

        $this->taskService->changeStatus($task, Task::STATUS_COMPLETED);
    }

    public function testChangeStatusCreatesUndoToken(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $undoToken = UndoToken::create(
            action: UndoAction::STATUS_CHANGE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
            userId: 'user-123',
        );

        $this->validationHelper->method('validateTaskStatus');

        $this->undoService->expects($this->once())
            ->method('createUndoToken')
            ->with(
                'user-123',
                UndoAction::STATUS_CHANGE->value,
                'task',
                'task-123',
                $this->isType('array')
            )
            ->willReturn($undoToken);

        $this->entityManager->method('flush');

        $result = $this->taskService->changeStatus($task, Task::STATUS_COMPLETED);

        $this->assertArrayHasKey('undoToken', $result);
        $this->assertEquals($undoToken->token, $result['undoToken']);
    }

    public function testChangeStatusSetsCompletedAtWhenChangingToCompleted(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $this->validationHelper->method('validateTaskStatus');
        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');

        $result = $this->taskService->changeStatus($task, Task::STATUS_COMPLETED);

        $this->assertEquals(Task::STATUS_COMPLETED, $result['task']->getStatus());
        $this->assertNotNull($result['task']->getCompletedAt());
    }

    // ========================================
    // Undo Delete Tests
    // ========================================

    public function testUndoDeleteRestoresTask(): void
    {
        $user = $this->createUserWithId();

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [
                'id' => 'task-123',
                'title' => 'Deleted Task',
                'description' => 'Task description',
                'status' => Task::STATUS_PENDING,
                'priority' => 3,
                'position' => 1,
                'projectId' => null,
                'tagIds' => [],
                'createdAt' => '2024-01-01T00:00:00+00:00',
                'completedAt' => null,
            ],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Task::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $task = $this->taskService->undoDelete($user, $undoToken->token);

        $this->assertEquals('Deleted Task', $task->getTitle());
        $this->assertSame($user, $task->getOwner());
    }

    public function testUndoDeleteWithInvalidTokenThrowsException(): void
    {
        $user = $this->createUserWithId();

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', 'invalid-token')
            ->willReturn(null);

        $this->expectException(ValidationException::class);
        $this->taskService->undoDelete($user, 'invalid-token');
    }

    public function testUndoDeleteWithWrongActionTypeThrowsException(): void
    {
        $user = $this->createUserWithId();

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->expectException(ValidationException::class);
        $this->taskService->undoDelete($user, $undoToken->token);
    }

    public function testUndoDeleteWithWrongEntityTypeThrowsException(): void
    {
        $user = $this->createUserWithId();

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: [],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->expectException(ValidationException::class);
        $this->taskService->undoDelete($user, $undoToken->token);
    }

    // ========================================
    // Undo Update Tests
    // ========================================

    public function testUndoUpdateRestoresPreviousState(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user, 'Current Title');

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [
                'title' => 'Previous Title',
                'description' => 'Previous Description',
                'status' => Task::STATUS_PENDING,
                'priority' => 2,
            ],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->taskRepository->expects($this->once())
            ->method('find')
            ->with('task-123')
            ->willReturn($task);

        $this->ownershipChecker->expects($this->once())
            ->method('checkOwnership')
            ->with($task);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->taskService->undoUpdate($user, $undoToken->token);

        $this->assertEquals('Previous Title', $result->getTitle());
        $this->assertEquals('Previous Description', $result->getDescription());
        $this->assertEquals(2, $result->getPriority());
    }

    public function testUndoUpdateWithMissingTaskThrowsException(): void
    {
        $user = $this->createUserWithId();

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'task',
            entityId: 'non-existent-task',
            previousState: [],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->taskRepository->expects($this->once())
            ->method('find')
            ->with('non-existent-task')
            ->willReturn(null);

        $this->expectException(EntityNotFoundException::class);
        $this->taskService->undoUpdate($user, $undoToken->token);
    }

    // ========================================
    // Find By ID Tests
    // ========================================

    public function testFindByIdOrFailReturnsTaskWhenFound(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $this->taskRepository->expects($this->once())
            ->method('find')
            ->with('task-123')
            ->willReturn($task);

        $this->ownershipChecker->expects($this->once())
            ->method('isOwner')
            ->with($task, $user)
            ->willReturn(true);

        $result = $this->taskService->findByIdOrFail('task-123', $user);

        $this->assertSame($task, $result);
    }

    public function testFindByIdOrFailThrowsExceptionWhenNotFound(): void
    {
        $user = $this->createUserWithId();

        $this->taskRepository->expects($this->once())
            ->method('find')
            ->with('non-existent')
            ->willReturn(null);

        $this->expectException(EntityNotFoundException::class);
        $this->taskService->findByIdOrFail('non-existent', $user);
    }

    public function testFindByIdOrFailThrowsForbiddenWhenNotOwned(): void
    {
        $user = $this->createUserWithId('user-1');
        $otherUser = $this->createUserWithId('user-2', 'other@example.com');
        $task = $this->createTaskWithId('task-123', $otherUser);

        $this->taskRepository->expects($this->once())
            ->method('find')
            ->with('task-123')
            ->willReturn($task);

        $this->ownershipChecker->expects($this->once())
            ->method('isOwner')
            ->with($task, $user)
            ->willReturn(false);

        $this->expectException(ForbiddenException::class);
        $this->taskService->findByIdOrFail('task-123', $user);
    }

    // ========================================
    // Generic Undo Tests
    // ========================================

    public function testUndoHandlesDeleteAction(): void
    {
        $user = $this->createUserWithId();

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [
                'id' => 'task-123',
                'title' => 'Deleted Task',
                'status' => Task::STATUS_PENDING,
                'priority' => 3,
                'position' => 1,
                'createdAt' => '2024-01-01T00:00:00+00:00',
            ],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $task = $this->taskService->undo($user, $undoToken->token);

        $this->assertEquals('Deleted Task', $task->getTitle());
    }

    public function testUndoHandlesUpdateAction(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [
                'title' => 'Previous Title',
            ],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->taskRepository->expects($this->once())
            ->method('find')
            ->with('task-123')
            ->willReturn($task);

        $this->ownershipChecker->method('checkOwnership');
        $this->entityManager->method('flush');

        $result = $this->taskService->undo($user, $undoToken->token);

        $this->assertEquals('Previous Title', $result->getTitle());
    }

    public function testUndoWithInvalidTokenThrowsException(): void
    {
        $user = $this->createUserWithId();

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', 'invalid')
            ->willReturn(null);

        $this->expectException(ValidationException::class);
        $this->taskService->undo($user, 'invalid');
    }

    public function testUndoWithWrongEntityTypeThrowsException(): void
    {
        $user = $this->createUserWithId();

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: [],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->expectException(ValidationException::class);
        $this->taskService->undo($user, $undoToken->token);
    }
}
