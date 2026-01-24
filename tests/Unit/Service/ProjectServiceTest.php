<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\CreateProjectRequest;
use App\DTO\MoveProjectRequest;
use App\DTO\ProjectSettingsRequest;
use App\DTO\UpdateProjectRequest;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\UndoAction;
use App\Exception\BatchSizeLimitExceededException;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidUndoTokenException;
use App\Exception\ProjectMoveToDescendantException;
use App\Interface\OwnershipCheckerInterface;
use App\Repository\ProjectRepository;
use App\Service\ProjectCacheService;
use App\Service\ProjectService;
use App\Service\ProjectStateService;
use App\Service\ProjectUndoService;
use App\Service\UndoService;
use App\Service\ValidationHelper;
use App\Tests\Unit\UnitTestCase;
use App\Transformer\ProjectTreeTransformer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use App\ValueObject\UndoToken;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class ProjectServiceTest extends UnitTestCase
{
    private ProjectRepository&MockObject $projectRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private UndoService&MockObject $undoService;
    private ValidationHelper&MockObject $validationHelper;
    private OwnershipCheckerInterface&MockObject $ownershipChecker;
    private ArrayAdapter $cache;
    private ProjectStateService $projectStateService;
    private ProjectUndoService $projectUndoService;
    private ProjectCacheService $projectCacheService;
    private ProjectTreeTransformer $projectTreeTransformer;
    private ProjectService $projectService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->undoService = $this->createMock(UndoService::class);
        $this->validationHelper = $this->createMock(ValidationHelper::class);
        $this->ownershipChecker = $this->createMock(OwnershipCheckerInterface::class);

        // Use actual services - ProjectStateService, ProjectUndoService, ProjectCacheService and ProjectTreeTransformer are final
        // Use ArrayAdapter which implements both CacheInterface and AdapterInterface
        $this->cache = new ArrayAdapter();
        $logger = $this->createMock(LoggerInterface::class);
        $this->projectCacheService = new ProjectCacheService($this->cache, $logger);
        $this->projectTreeTransformer = new ProjectTreeTransformer();

        // Create ProjectStateService with the mocked repository
        $this->projectStateService = new ProjectStateService($this->projectRepository, $logger);

        // Create ProjectUndoService with mocked dependencies
        $this->projectUndoService = new ProjectUndoService(
            $this->undoService,
            $this->projectRepository,
            $this->projectStateService,
            $this->entityManager,
            $this->projectCacheService,
        );

        $this->projectService = new ProjectService(
            $this->projectRepository,
            $this->entityManager,
            $this->validationHelper,
            $this->ownershipChecker,
            $this->projectStateService,
            $this->projectUndoService,
            $this->projectCacheService,
            $this->projectTreeTransformer,
        );
    }

    // ========================================
    // Create Project Tests
    // ========================================

    public function testCreateProjectWithMinimalData(): void
    {
        $user = $this->createUserWithId();
        $dto = new CreateProjectRequest(name: 'Test Project');

        $this->validationHelper->expects($this->once())
            ->method('validate')
            ->with($dto);

        $this->projectRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Project::class), true);

        $project = $this->projectService->create($user, $dto);

        $this->assertEquals('Test Project', $project->getName());
        $this->assertNull($project->getDescription());
        $this->assertSame($user, $project->getOwner());
        $this->assertFalse($project->isArchived());
    }

    public function testCreateProjectWithDescription(): void
    {
        $user = $this->createUserWithId();
        $dto = new CreateProjectRequest(
            name: 'Full Project',
            description: 'A project with a description',
        );

        $this->validationHelper->expects($this->once())
            ->method('validate')
            ->with($dto);

        $this->projectRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Project::class), true);

        $project = $this->projectService->create($user, $dto);

        $this->assertEquals('Full Project', $project->getName());
        $this->assertEquals('A project with a description', $project->getDescription());
        $this->assertSame($user, $project->getOwner());
    }

    public function testCreateProjectAssignsOwner(): void
    {
        $user = $this->createUserWithId('owner-123');
        $dto = new CreateProjectRequest(name: 'Owned Project');

        $this->validationHelper->method('validate');
        $this->projectRepository->method('save');

        $project = $this->projectService->create($user, $dto);

        $this->assertSame($user, $project->getOwner());
    }

    // ========================================
    // Update Project Tests
    // ========================================

    public function testUpdateProjectCreatesUndoToken(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user, 'Original Name');

        $dto = new UpdateProjectRequest(name: 'Updated Name');

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'project-123',
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
                'project',
                'project-123',
                $this->isType('array')
            )
            ->willReturn($undoToken);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->projectService->update($project, $dto);

        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('undoToken', $result);
        $this->assertSame($undoToken, $result['undoToken']);
    }

    public function testUpdateProjectModifiesName(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user, 'Original Name');

        $dto = new UpdateProjectRequest(name: 'Updated Name');

        $this->validationHelper->method('validate');
        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');

        $result = $this->projectService->update($project, $dto);

        $this->assertEquals('Updated Name', $result['project']->getName());
    }

    public function testUpdateProjectModifiesDescription(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);
        $project->setDescription('Original Description');

        $dto = new UpdateProjectRequest(description: 'New Description');

        $this->validationHelper->method('validate');
        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');

        $result = $this->projectService->update($project, $dto);

        $this->assertEquals('New Description', $result['project']->getDescription());
    }

    public function testUpdateProjectPreservesUnchangedFields(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user, 'Original Name');
        $project->setDescription('Original Description');

        $dto = new UpdateProjectRequest(name: 'Updated Name');

        $this->validationHelper->method('validate');
        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');

        $result = $this->projectService->update($project, $dto);

        $this->assertEquals('Updated Name', $result['project']->getName());
        $this->assertEquals('Original Description', $result['project']->getDescription());
    }

    // ========================================
    // Delete Project Tests
    // ========================================

    public function testDeleteProjectCreatesUndoToken(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: [],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('createUndoToken')
            ->with(
                'user-123',
                UndoAction::DELETE->value,
                'project',
                'project-123',
                $this->isType('array')
            )
            ->willReturn($undoToken);

        $this->entityManager->expects($this->once())
            ->method('flush');

        // Cache invalidation happens automatically via the real ArrayAdapter

        $result = $this->projectService->delete($project);

        $this->assertSame($undoToken, $result);
    }

    public function testDeleteProjectSoftDeletesProject(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);

        $this->assertFalse($project->isDeleted());

        $this->undoService->method('createUndoToken');
        $this->entityManager->expects($this->once())
            ->method('flush');
        // Cache invalidation happens automatically via the real ArrayAdapter

        $this->projectService->delete($project);

        $this->assertTrue($project->isDeleted());
        $this->assertNotNull($project->getDeletedAt());
    }

    /**
     * Note: Cascade deletion of tasks is handled by Doctrine ORM mapping.
     * The service only stores the project state for undo, not its tasks.
     */
    public function testDeleteProjectStoresProjectStateForUndo(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');
        $project->setDescription('Test Description');

        $capturedPreviousState = null;

        $this->undoService->expects($this->once())
            ->method('createUndoToken')
            ->willReturnCallback(function ($userId, $action, $entityType, $entityId, $previousState) use (&$capturedPreviousState) {
                $capturedPreviousState = $previousState;
                return UndoToken::create($action, $entityType, $entityId, $previousState, $userId);
            });

        $this->entityManager->method('flush');
        // Cache invalidation happens automatically via the real ArrayAdapter

        $this->projectService->delete($project);

        // Verify all serialized state fields
        $this->assertArrayHasKey('name', $capturedPreviousState);
        $this->assertArrayHasKey('description', $capturedPreviousState);
        $this->assertArrayHasKey('isArchived', $capturedPreviousState);
        $this->assertArrayHasKey('deletedAt', $capturedPreviousState);
        $this->assertArrayHasKey('parentId', $capturedPreviousState);
        $this->assertArrayHasKey('position', $capturedPreviousState);
        $this->assertArrayHasKey('showChildrenTasks', $capturedPreviousState);
        $this->assertEquals('Test Project', $capturedPreviousState['name']);
        $this->assertEquals('Test Description', $capturedPreviousState['description']);
    }

    // ========================================
    // Archive Project Tests
    // ========================================

    public function testArchiveProjectCreatesUndoToken(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);

        $undoToken = UndoToken::create(
            action: UndoAction::ARCHIVE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: ['isArchived' => false, 'archivedAt' => null],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('createUndoToken')
            ->with(
                'user-123',
                UndoAction::ARCHIVE->value,
                'project',
                'project-123',
                ['isArchived' => false, 'archivedAt' => null]
            )
            ->willReturn($undoToken);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->projectService->archive($project);

        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('undoToken', $result);
        $this->assertTrue($result['project']->isArchived());
    }

    public function testArchiveProjectSetsIsArchivedToTrue(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);

        $this->assertFalse($project->isArchived());

        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');

        $result = $this->projectService->archive($project);

        $this->assertTrue($result['project']->isArchived());
    }

    // ========================================
    // Unarchive Project Tests
    // ========================================

    public function testUnarchiveProjectCreatesUndoToken(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);
        $project->setIsArchived(true);

        $undoToken = UndoToken::create(
            action: UndoAction::ARCHIVE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: ['isArchived' => true, 'archivedAt' => null],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('createUndoToken')
            ->with(
                'user-123',
                UndoAction::ARCHIVE->value,
                'project',
                'project-123',
                ['isArchived' => true, 'archivedAt' => null]
            )
            ->willReturn($undoToken);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->projectService->unarchive($project);

        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('undoToken', $result);
        $this->assertFalse($result['project']->isArchived());
    }

    public function testUnarchiveProjectSetsIsArchivedToFalse(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);
        $project->setIsArchived(true);

        $this->assertTrue($project->isArchived());

        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');

        $result = $this->projectService->unarchive($project);

        $this->assertFalse($result['project']->isArchived());
    }

    // ========================================
    // Undo Archive Tests
    // ========================================

    public function testUndoArchiveRestoresPreviousState(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);
        $project->setIsArchived(true);

        $undoToken = UndoToken::create(
            action: UndoAction::ARCHIVE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: ['isArchived' => false],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->projectRepository->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'project-123')
            ->willReturn($project);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->projectService->undoArchive($user, $undoToken->token);

        $this->assertFalse($result->isArchived());
    }

    public function testUndoArchiveWithInvalidTokenThrowsException(): void
    {
        $user = $this->createUserWithId();

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', 'invalid-token')
            ->willReturn(null);

        $this->expectException(InvalidUndoTokenException::class);
        $this->projectService->undoArchive($user, 'invalid-token');
    }

    public function testUndoArchiveWithWrongActionTypeThrowsException(): void
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

        $this->expectException(InvalidUndoTokenException::class);
        $this->projectService->undoArchive($user, $undoToken->token);
    }

    // ========================================
    // Undo Delete Tests
    // ========================================

    public function testUndoDeleteRestoresProject(): void
    {
        $user = $this->createUserWithId();

        // Create a soft-deleted project
        $project = $this->createProjectWithId('project-123', $user, 'Deleted Project');
        $project->setDescription('Project description');
        $project->softDelete();
        $this->assertTrue($project->isDeleted());

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: [
                'name' => 'Deleted Project',
                'description' => 'Project description',
                'isArchived' => false,
                'deletedAt' => null,
                'parentId' => null,
                'position' => 0,
                'showChildrenTasks' => true,
            ],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->projectRepository->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'project-123', includeDeleted: true)
            ->willReturn($project);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->projectService->undoDelete($user, $undoToken->token);

        $this->assertFalse($result->isDeleted());
        $this->assertEquals('Deleted Project', $result->getName());
        $this->assertEquals('Project description', $result->getDescription());
        $this->assertSame($user, $result->getOwner());
    }

    public function testUndoDeleteWithInvalidTokenThrowsException(): void
    {
        $user = $this->createUserWithId();

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', 'invalid-token')
            ->willReturn(null);

        $this->expectException(InvalidUndoTokenException::class);
        $this->projectService->undoDelete($user, 'invalid-token');
    }

    public function testUndoDeleteWithWrongActionTypeThrowsException(): void
    {
        $user = $this->createUserWithId();

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: [],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->expectException(InvalidUndoTokenException::class);
        $this->projectService->undoDelete($user, $undoToken->token);
    }

    // ========================================
    // Undo Update Tests
    // ========================================

    public function testUndoUpdateRestoresPreviousState(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user, 'Current Name');

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: [
                'name' => 'Previous Name',
                'description' => 'Previous Description',
            ],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->projectRepository->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'project-123')
            ->willReturn($project);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->projectService->undoUpdate($user, $undoToken->token);

        $this->assertEquals('Previous Name', $result->getName());
        $this->assertEquals('Previous Description', $result->getDescription());
    }

    public function testUndoUpdateWithMissingProjectThrowsException(): void
    {
        $user = $this->createUserWithId();

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'non-existent',
            previousState: [],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->projectRepository->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'non-existent')
            ->willReturn(null);

        $this->expectException(EntityNotFoundException::class);
        $this->projectService->undoUpdate($user, $undoToken->token);
    }

    // ========================================
    // Generic Undo Tests
    // ========================================

    public function testUndoHandlesUpdateAction(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: ['name' => 'Previous Name'],
            userId: 'user-123',
        );

        // Uses consume-then-validate pattern - no getUndoToken call
        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->projectRepository->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'project-123')
            ->willReturn($project);

        $this->entityManager->method('flush');

        $result = $this->projectService->undo($user, $undoToken->token);

        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertEquals(UndoAction::UPDATE->value, $result['action']);
    }

    public function testUndoHandlesArchiveAction(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);
        $project->setIsArchived(true);

        $undoToken = UndoToken::create(
            action: UndoAction::ARCHIVE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: ['isArchived' => false],
            userId: 'user-123',
        );

        // Uses consume-then-validate pattern - no getUndoToken call
        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->projectRepository->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'project-123')
            ->willReturn($project);

        $this->entityManager->method('flush');

        $result = $this->projectService->undo($user, $undoToken->token);

        $this->assertFalse($result['project']->isArchived());
        $this->assertEquals(UndoAction::ARCHIVE->value, $result['action']);
    }

    public function testUndoHandlesDeleteAction(): void
    {
        $user = $this->createUserWithId();

        // Create a soft-deleted project
        $project = $this->createProjectWithId('project-123', $user, 'Deleted Project');
        $project->softDelete();

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: [
                'name' => 'Deleted Project',
                'description' => null,
                'isArchived' => false,
                'deletedAt' => null,
                'parentId' => null,
                'position' => 0,
                'showChildrenTasks' => true,
            ],
            userId: 'user-123',
        );

        // Uses consume-then-validate pattern - no getUndoToken call
        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->projectRepository->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'project-123', includeDeleted: true)
            ->willReturn($project);

        $this->entityManager->method('flush');

        $result = $this->projectService->undo($user, $undoToken->token);

        $this->assertEquals('Deleted Project', $result['project']->getName());
        $this->assertEquals(UndoAction::DELETE->value, $result['action']);
        // No warning with soft-delete restoration since tasks remain associated
        $this->assertNull($result['warning']);
    }

    public function testUndoWithInvalidTokenThrowsException(): void
    {
        $user = $this->createUserWithId();

        // Uses consumeUndoToken directly instead of peek-then-consume
        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', 'invalid')
            ->willReturn(null);

        $this->expectException(InvalidUndoTokenException::class);
        $this->projectService->undo($user, 'invalid');
    }

    public function testUndoWithWrongEntityTypeThrowsException(): void
    {
        $user = $this->createUserWithId();

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
            userId: 'user-123',
        );

        // With consume-then-validate, the token is consumed first
        // Note: Token is already consumed and cannot be reused after this
        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->expectException(InvalidUndoTokenException::class);
        $this->projectService->undo($user, $undoToken->token);
    }

    // ========================================
    // Find By ID Tests
    // ========================================

    public function testFindByIdOrFailReturnsProjectWhenFound(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);

        $this->projectRepository->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'project-123')
            ->willReturn($project);

        $result = $this->projectService->findByIdOrFail('project-123', $user);

        $this->assertSame($project, $result);
    }

    public function testFindByIdOrFailThrowsExceptionWhenNotFound(): void
    {
        $user = $this->createUserWithId();

        $this->projectRepository->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'non-existent')
            ->willReturn(null);

        $this->expectException(EntityNotFoundException::class);
        $this->projectService->findByIdOrFail('non-existent', $user);
    }

    // ========================================
    // Task Counts Tests
    // ========================================

    public function testGetTaskCountsReturnsCorrectCounts(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);

        $this->projectRepository->expects($this->once())
            ->method('countTasksByProject')
            ->with($project)
            ->willReturn(10);

        $this->projectRepository->expects($this->once())
            ->method('countCompletedTasksByProject')
            ->with($project)
            ->willReturn(5);

        $result = $this->projectService->getTaskCounts($project);

        $this->assertEquals(['total' => 10, 'completed' => 5], $result);
    }

    // ========================================
    // Move Project Tests
    // ========================================

    public function testMoveProjectToNewParentSuccessfully(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);
        $newParent = $this->createProjectWithId('parent-456', $user);

        $dto = MoveProjectRequest::fromArray(['parentId' => 'parent-456']);

        $this->validationHelper->method('validate');

        $this->projectRepository->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'parent-456')
            ->willReturn($newParent);

        $this->projectRepository->method('getMaxPositionInParent')
            ->willReturn(0);

        $this->projectRepository->method('normalizePositions');

        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');
        $this->entityManager->method('beginTransaction');
        $this->entityManager->method('commit');

        $result = $this->projectService->move($project, $dto);

        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('undoToken', $result);
        $this->assertSame($newParent, $result['project']->getParent());
    }

    public function testMoveProjectToRootSuccessfully(): void
    {
        $user = $this->createUserWithId();
        $parent = $this->createProjectWithId('parent-456', $user);
        $project = $this->createProjectWithId('project-123', $user);
        $project->setParent($parent);

        $dto = MoveProjectRequest::fromArray(['parentId' => null]);

        $this->validationHelper->method('validate');

        $this->projectRepository->method('getMaxPositionInParent')
            ->willReturn(0);

        $this->projectRepository->method('normalizePositions');

        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');
        $this->entityManager->method('beginTransaction');
        $this->entityManager->method('commit');

        $result = $this->projectService->move($project, $dto);

        $this->assertNull($result['project']->getParent());
    }

    public function testMoveProjectToDescendantThrowsException(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);
        $child = $this->createProjectWithId('child-456', $user);
        $child->setParent($project);

        $dto = MoveProjectRequest::fromArray(['parentId' => 'child-456']);

        $this->validationHelper->method('validate');

        $this->projectRepository->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'child-456')
            ->willReturn($child);

        $this->projectRepository->method('getDescendantIds')
            ->with($project)
            ->willReturn(['child-456']);

        $this->entityManager->method('beginTransaction');
        $this->entityManager->expects($this->once())
            ->method('rollback');

        $this->expectException(ProjectMoveToDescendantException::class);
        $this->projectService->move($project, $dto);
    }

    // ========================================
    // Reorder Project Tests
    // ========================================

    public function testReorderProjectUpdatesPosition(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);
        $project->setPosition(0);

        $this->undoService->method('createUndoToken');
        $this->entityManager->method('flush');
        $this->projectRepository->method('normalizePositions');

        $result = $this->projectService->reorder($project, 5);

        $this->assertArrayHasKey('project', $result);
        $this->assertArrayHasKey('undoToken', $result);
        $this->assertEquals(5, $result['project']->getPosition());
    }

    public function testReorderProjectCreatesUndoToken(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);
        $project->setPosition(0);

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: ['position' => 0],
            userId: 'user-123',
        );

        $this->undoService->expects($this->once())
            ->method('createUndoToken')
            ->willReturn($undoToken);

        $this->entityManager->method('flush');
        $this->projectRepository->method('normalizePositions');

        $result = $this->projectService->reorder($project, 5);

        $this->assertSame($undoToken, $result['undoToken']);
    }

    // ========================================
    // Batch Reorder Tests
    // ========================================

    public function testBatchReorderMultipleProjects(): void
    {
        $user = $this->createUserWithId();
        $project1 = $this->createProjectWithId('project-1', $user);
        $project2 = $this->createProjectWithId('project-2', $user);
        $project3 = $this->createProjectWithId('project-3', $user);

        $projectIds = ['project-3', 'project-1', 'project-2'];

        $this->projectRepository->method('findOneByOwnerAndId')
            ->willReturnCallback(function ($u, $id) use ($project1, $project2, $project3) {
                return match ($id) {
                    'project-1' => $project1,
                    'project-2' => $project2,
                    'project-3' => $project3,
                    default => null,
                };
            });

        $this->entityManager->expects($this->once())
            ->method('beginTransaction');
        $this->entityManager->expects($this->once())
            ->method('flush');
        $this->entityManager->expects($this->once())
            ->method('commit');

        $this->projectService->batchReorder($user, null, $projectIds);

        $this->assertEquals(0, $project3->getPosition());
        $this->assertEquals(1, $project1->getPosition());
        $this->assertEquals(2, $project2->getPosition());
    }

    public function testBatchReorderExceedsSizeLimitThrowsException(): void
    {
        $user = $this->createUserWithId();
        $projectIds = array_fill(0, 1001, 'project-id');

        $this->expectException(BatchSizeLimitExceededException::class);

        $this->projectService->batchReorder($user, null, $projectIds);
    }

    public function testBatchReorderWithInvalidParentThrowsException(): void
    {
        $user = $this->createUserWithId();

        $this->projectRepository->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'invalid-parent')
            ->willReturn(null);

        $this->expectException(EntityNotFoundException::class);

        $this->projectService->batchReorder($user, 'invalid-parent', ['project-1']);
    }

    public function testBatchReorderWithProjectNotInParentThrowsException(): void
    {
        $user = $this->createUserWithId();
        $parent = $this->createProjectWithId('parent-123', $user);
        $project = $this->createProjectWithId('project-1', $user);
        // project has no parent, but we're trying to reorder it under 'parent-123'

        $this->projectRepository->method('findOneByOwnerAndId')
            ->willReturnCallback(function ($u, $id) use ($parent, $project) {
                return match ($id) {
                    'parent-123' => $parent,
                    'project-1' => $project,
                    default => null,
                };
            });

        $this->entityManager->method('beginTransaction');
        $this->entityManager->expects($this->once())
            ->method('rollback');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not have parent');

        $this->projectService->batchReorder($user, 'parent-123', ['project-1']);
    }

    // ========================================
    // Update Settings Tests
    // ========================================

    public function testUpdateSettingsShowChildrenTasks(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);
        $project->setShowChildrenTasks(true);

        $dto = ProjectSettingsRequest::fromArray(['showChildrenTasks' => false]);

        $this->validationHelper->method('validate');
        $this->entityManager->method('flush');

        $result = $this->projectService->updateSettings($project, $dto);

        $this->assertFalse($result->isShowChildrenTasks());
    }

    public function testUpdateSettingsNoChangeWhenNull(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);
        $project->setShowChildrenTasks(true);

        $dto = ProjectSettingsRequest::fromArray([]);

        $this->validationHelper->method('validate');
        $this->entityManager->method('flush');

        $result = $this->projectService->updateSettings($project, $dto);

        $this->assertTrue($result->isShowChildrenTasks());
    }

    // ========================================
    // Get Tree Tests
    // ========================================

    public function testGetTreeReturnsTreeStructure(): void
    {
        $user = $this->createUserWithId();
        $project1 = $this->createProjectWithId('project-1', $user, 'Root Project');
        $project2 = $this->createProjectWithId('project-2', $user, 'Another Root');

        // Cache is fresh (ArrayAdapter is empty), so repository will be called
        $this->projectRepository->method('getTreeByUser')
            ->with($user, false)
            ->willReturn([$project1, $project2]);

        $result = $this->projectService->getTree($user, false, false);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetTreeUsesCacheWhenAvailable(): void
    {
        $user = $this->createUserWithId();
        $project1 = $this->createProjectWithId('project-1', $user, 'Cached Project');
        $cachedTree = [
            ['id' => 'project-1', 'name' => 'Cached Project', 'children' => []],
        ];

        // Pre-populate the cache with a value
        $this->projectCacheService->set($user->getId(), false, false, $cachedTree);

        // Repository should not be called when cache hits
        $this->projectRepository->expects($this->never())
            ->method('getTreeByUser');

        $result = $this->projectService->getTree($user, false, false);

        $this->assertEquals($cachedTree, $result);
    }

    public function testGetTreeIncludesArchivedWhenRequested(): void
    {
        $user = $this->createUserWithId();
        $project1 = $this->createProjectWithId('project-1', $user, 'Active Project');
        $archivedProject = $this->createProjectWithId('project-2', $user, 'Archived Project');
        $archivedProject->setIsArchived(true);

        // Cache is fresh (ArrayAdapter is empty), so repository will be called
        $this->projectRepository->expects($this->once())
            ->method('getTreeByUser')
            ->with($user, true)
            ->willReturn([$project1, $archivedProject]);

        $result = $this->projectService->getTree($user, true, false);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    // ========================================
    // Archive With Options Tests
    // ========================================

    public function testArchiveWithOptionsCascadeArchivesDescendants(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);
        $child1 = $this->createProjectWithId('child-1', $user);
        $child2 = $this->createProjectWithId('child-2', $user);
        $child1->setParent($project);
        $child2->setParent($project);

        $this->projectRepository->method('findAllDescendants')
            ->with($project)
            ->willReturn([$child1, $child2]);

        $this->undoService->method('createUndoToken');
        $this->entityManager->method('beginTransaction');
        $this->entityManager->method('flush');
        $this->entityManager->method('commit');

        $result = $this->projectService->archiveWithOptions($project, cascade: true);

        $this->assertTrue($result['project']->isArchived());
        $this->assertTrue($child1->isArchived());
        $this->assertTrue($child2->isArchived());
        $this->assertContains('child-1', $result['affectedProjects']);
        $this->assertContains('child-2', $result['affectedProjects']);
    }

    public function testArchiveWithOptionsPromoteChildrenMovesChildren(): void
    {
        $user = $this->createUserWithId();
        $parent = $this->createProjectWithId('parent-123', $user);
        $project = $this->createProjectWithId('project-123', $user);
        $project->setParent($parent);
        $child = $this->createProjectWithId('child-456', $user);
        $child->setParent($project);

        $this->projectRepository->method('findAllDescendants')
            ->willReturn([]);

        $this->projectRepository->method('findChildrenByParent')
            ->with($project, true)
            ->willReturn([$child]);

        $this->undoService->method('createUndoToken');
        $this->entityManager->method('beginTransaction');
        $this->entityManager->method('flush');
        $this->entityManager->method('commit');

        $result = $this->projectService->archiveWithOptions($project, cascade: false, promoteChildren: true);

        $this->assertTrue($result['project']->isArchived());
        $this->assertSame($parent, $child->getParent());
        $this->assertContains('child-456', $result['affectedProjects']);
    }

    // ========================================
    // Unarchive With Options Tests
    // ========================================

    public function testUnarchiveWithOptionsCascadeUnarchivesDescendants(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);
        $project->setIsArchived(true);
        $child1 = $this->createProjectWithId('child-1', $user);
        $child1->setParent($project);
        $child1->setIsArchived(true);
        $child2 = $this->createProjectWithId('child-2', $user);
        $child2->setParent($project);
        $child2->setIsArchived(true);

        $this->projectRepository->method('findAllDescendants')
            ->with($project)
            ->willReturn([$child1, $child2]);

        $this->undoService->method('createUndoToken');
        $this->entityManager->method('beginTransaction');
        $this->entityManager->method('flush');
        $this->entityManager->method('commit');

        $result = $this->projectService->unarchiveWithOptions($project, cascade: true);

        $this->assertFalse($result['project']->isArchived());
        $this->assertFalse($child1->isArchived());
        $this->assertFalse($child2->isArchived());
        $this->assertContains('child-1', $result['affectedProjects']);
        $this->assertContains('child-2', $result['affectedProjects']);
    }

    public function testUnarchiveWithOptionsWithoutCascadeOnlyUnarchivesProject(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);
        $project->setIsArchived(true);
        $child = $this->createProjectWithId('child-456', $user);
        $child->setParent($project);
        $child->setIsArchived(true);

        $this->projectRepository->method('findAllDescendants')
            ->willReturn([]);

        $this->undoService->method('createUndoToken');
        $this->entityManager->method('beginTransaction');
        $this->entityManager->method('flush');
        $this->entityManager->method('commit');

        $result = $this->projectService->unarchiveWithOptions($project, cascade: false);

        $this->assertFalse($result['project']->isArchived());
        // child should still be archived since cascade is false
        $this->assertTrue($child->isArchived());
        $this->assertEmpty($result['affectedProjects']);
    }

    // ========================================
    // Get Archived Projects Tests
    // ========================================

    public function testGetArchivedProjectsReturnsOnlyArchived(): void
    {
        $user = $this->createUserWithId();
        $archivedProject = $this->createProjectWithId('archived-123', $user);
        $archivedProject->setIsArchived(true);

        $this->projectRepository->expects($this->once())
            ->method('findArchivedByOwner')
            ->with($user)
            ->willReturn([$archivedProject]);

        $result = $this->projectService->getArchivedProjects($user);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]->isArchived());
    }

    public function testGetArchivedProjectsReturnsEmptyArrayWhenNoArchived(): void
    {
        $user = $this->createUserWithId();

        $this->projectRepository->expects($this->once())
            ->method('findArchivedByOwner')
            ->with($user)
            ->willReturn([]);

        $result = $this->projectService->getArchivedProjects($user);

        $this->assertEmpty($result);
    }
}
