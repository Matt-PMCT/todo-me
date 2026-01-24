<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\CreateTaskRequest;
use App\DTO\UpdateTaskRequest;
use App\Entity\Task;
use App\Exception\EntityNotFoundException;
use App\Exception\ForbiddenException;
use App\Interface\OwnershipCheckerInterface;
use App\Interface\TaskStateServiceInterface;
use App\Interface\TaskUndoServiceInterface;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;
use App\Service\Parser\NaturalLanguageParserService;
use App\Service\Recurrence\NextDateCalculator;
use App\Service\Recurrence\RecurrenceRuleParser;
use App\Service\TaskService;
use App\Service\ValidationHelper;
use App\Tests\Unit\UnitTestCase;
use App\ValueObject\UndoToken;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\Validation;

/**
 * Unit tests for TaskService.
 *
 * Uses real ValidationHelper to improve test reliability. Infrastructure
 * dependencies (repositories, EntityManager) remain mocked. TaskStateService
 * and TaskUndoService are mocked as they have their own dedicated tests.
 */
class TaskServiceTest extends UnitTestCase
{
    private TaskRepository&MockObject $taskRepository;
    private ProjectRepository&MockObject $projectRepository;
    private TagRepository&MockObject $tagRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private ValidationHelper $validationHelper;
    private OwnershipCheckerInterface&MockObject $ownershipChecker;
    private NaturalLanguageParserService&MockObject $naturalLanguageParser;
    private TaskStateServiceInterface&MockObject $taskStateService;
    private TaskUndoServiceInterface&MockObject $taskUndoService;
    private RecurrenceRuleParser $recurrenceRuleParser;
    private NextDateCalculator $nextDateCalculator;
    private TaskService $taskService;

    protected function setUp(): void
    {
        parent::setUp();

        // Infrastructure mocks
        $this->taskRepository = $this->createMock(TaskRepository::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        // Use REAL ValidationHelper with real Symfony Validator
        // This improves test reliability - validation changes are now caught
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
        $this->validationHelper = new ValidationHelper($validator);

        // OwnershipChecker requires Security which needs request context - keep mocked
        $this->ownershipChecker = $this->createMock(OwnershipCheckerInterface::class);

        // NaturalLanguageParser has complex dependencies - keep mocked
        $this->naturalLanguageParser = $this->createMock(NaturalLanguageParserService::class);

        // Mock interfaces for final services (TaskStateService and TaskUndoService)
        $this->taskStateService = $this->createMock(TaskStateServiceInterface::class);
        $this->taskUndoService = $this->createMock(TaskUndoServiceInterface::class);

        // Recurrence services - use real instances (they have no external dependencies)
        $this->recurrenceRuleParser = new RecurrenceRuleParser();
        $this->nextDateCalculator = new NextDateCalculator();

        $this->taskService = new TaskService(
            $this->taskRepository,
            $this->projectRepository,
            $this->tagRepository,
            $this->entityManager,
            $this->validationHelper,
            $this->ownershipChecker,
            $this->naturalLanguageParser,
            $this->taskStateService,
            $this->taskUndoService,
            $this->recurrenceRuleParser,
            $this->nextDateCalculator,
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
        $projectId = $this->generateUuid();
        $project = $this->createProjectWithId($projectId, $user);

        $dto = new CreateTaskRequest(
            title: 'Full Task',
            description: 'A complete task with all fields',
            status: Task::STATUS_IN_PROGRESS,
            priority: 4,
            dueDate: '2024-12-31',
            projectId: $projectId,
        );

        $this->projectRepository->expects($this->once())
            ->method('find')
            ->with($projectId)
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
        $nonExistentId = $this->generateUuid();
        $dto = new CreateTaskRequest(
            title: 'Task',
            projectId: $nonExistentId,
        );

        $this->projectRepository->expects($this->once())
            ->method('find')
            ->with($nonExistentId)
            ->willReturn(null);

        $this->expectException(EntityNotFoundException::class);
        $this->taskService->create($user, $dto);
    }

    public function testCreateTaskWithUnownedProjectThrowsForbiddenException(): void
    {
        $user = $this->createUserWithId();
        $otherUser = $this->createUserWithId('other-user', 'other@example.com');
        $projectId = $this->generateUuid();
        $project = $this->createProjectWithId($projectId, $otherUser);

        $dto = new CreateTaskRequest(
            title: 'Task',
            projectId: $projectId,
        );

        $this->projectRepository->expects($this->once())
            ->method('find')
            ->with($projectId)
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

        $this->taskStateService->expects($this->once())
            ->method('serializeTaskState')
            ->with($task)
            ->willReturn(['title' => 'Original Title']);

        $this->taskUndoService->expects($this->once())
            ->method('createUpdateUndoToken')
            ->with($task, ['title' => 'Original Title'])
            ->willReturn('undo-token-123');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->taskService->update($task, $dto);

        $this->assertArrayHasKey('task', $result);
        $this->assertArrayHasKey('undoToken', $result);
        $this->assertEquals('undo-token-123', $result['undoToken']);
    }

    public function testUpdateTaskModifiesTitle(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user, 'Original Title');

        $dto = new UpdateTaskRequest(title: 'Updated Title');

        $this->taskStateService->method('serializeTaskState')->willReturn([]);
        $this->taskUndoService->method('createUpdateUndoToken')->willReturn(null);
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

        $this->taskStateService->method('serializeTaskState')->willReturn([]);
        $this->taskUndoService->method('createUpdateUndoToken')->willReturn(null);
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

        $this->taskStateService->method('serializeTaskState')->willReturn([]);
        $this->taskUndoService->method('createUpdateUndoToken')->willReturn(null);
        $this->entityManager->method('flush');

        $result = $this->taskService->update($task, $dto);

        $this->assertNull($result['task']->getDescription());
    }

    public function testUpdateTaskValidatesStatus(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $dto = new UpdateTaskRequest(status: Task::STATUS_COMPLETED);

        $this->taskStateService->method('serializeTaskState')->willReturn([]);
        $this->taskUndoService->method('createUpdateUndoToken')->willReturn(null);
        $this->entityManager->method('flush');

        $result = $this->taskService->update($task, $dto);

        $this->assertEquals(Task::STATUS_COMPLETED, $result['task']->getStatus());
    }

    public function testUpdateTaskValidatesPriority(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $dto = new UpdateTaskRequest(priority: 4);

        $this->taskStateService->method('serializeTaskState')->willReturn([]);
        $this->taskUndoService->method('createUpdateUndoToken')->willReturn(null);
        $this->entityManager->method('flush');

        $result = $this->taskService->update($task, $dto);

        $this->assertEquals(4, $result['task']->getPriority());
    }

    // ========================================
    // Delete Task Tests
    // ========================================

    public function testDeleteTaskCreatesUndoToken(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $undoToken = UndoToken::create(
            action: 'delete',
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
            userId: 'user-123',
        );

        $this->taskUndoService->expects($this->once())
            ->method('createDeleteUndoToken')
            ->with($task)
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

        $this->taskUndoService->method('createDeleteUndoToken')->willReturn(null);

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

        $this->taskStateService->method('serializeStatusState')->willReturn([]);
        $this->taskUndoService->method('createStatusChangeUndoToken')->willReturn(null);
        $this->entityManager->method('flush');

        // Real validation helper is used - will validate the status
        $result = $this->taskService->changeStatus($task, Task::STATUS_COMPLETED);

        $this->assertEquals(Task::STATUS_COMPLETED, $result->task->getStatus());
    }

    public function testChangeStatusCreatesUndoToken(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $this->taskStateService->expects($this->once())
            ->method('serializeStatusState')
            ->with($task)
            ->willReturn(['status' => Task::STATUS_PENDING]);

        $this->taskUndoService->expects($this->once())
            ->method('createStatusChangeUndoToken')
            ->with($task, ['status' => Task::STATUS_PENDING])
            ->willReturn('undo-token-status');

        $this->entityManager->method('flush');

        $result = $this->taskService->changeStatus($task, Task::STATUS_COMPLETED);

        $this->assertNotNull($result->undoToken);
        $this->assertEquals('undo-token-status', $result->undoToken);
    }

    public function testChangeStatusSetsCompletedAtWhenChangingToCompleted(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $this->taskStateService->method('serializeStatusState')->willReturn([]);
        $this->taskUndoService->method('createStatusChangeUndoToken')->willReturn(null);
        $this->entityManager->method('flush');

        $result = $this->taskService->changeStatus($task, Task::STATUS_COMPLETED);

        $this->assertEquals(Task::STATUS_COMPLETED, $result->task->getStatus());
        $this->assertNotNull($result->task->getCompletedAt());
    }

    // ========================================
    // Undo Tests (Delegated to TaskUndoService)
    // ========================================

    public function testUndoDeleteDelegatesToTaskUndoService(): void
    {
        $user = $this->createUserWithId();
        $restoredTask = $this->createTaskWithId('task-123', $user, 'Restored Task');

        $this->taskUndoService->expects($this->once())
            ->method('undoDelete')
            ->with($user, 'undo-token')
            ->willReturn($restoredTask);

        $result = $this->taskService->undoDelete($user, 'undo-token');

        $this->assertSame($restoredTask, $result);
    }

    public function testUndoUpdateDelegatesToTaskUndoService(): void
    {
        $user = $this->createUserWithId();
        $restoredTask = $this->createTaskWithId('task-123', $user, 'Restored Task');

        $this->taskUndoService->expects($this->once())
            ->method('undoUpdate')
            ->with($user, 'undo-token')
            ->willReturn($restoredTask);

        $result = $this->taskService->undoUpdate($user, 'undo-token');

        $this->assertSame($restoredTask, $result);
    }

    public function testUndoDelegatesToTaskUndoService(): void
    {
        $user = $this->createUserWithId();
        $restoredTask = $this->createTaskWithId('task-123', $user, 'Restored Task');

        $this->taskUndoService->expects($this->once())
            ->method('undo')
            ->with($user, 'undo-token')
            ->willReturn($restoredTask);

        $result = $this->taskService->undo($user, 'undo-token');

        $this->assertSame($restoredTask, $result);
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
}
