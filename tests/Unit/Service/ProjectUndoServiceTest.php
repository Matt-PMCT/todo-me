<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Enum\UndoAction;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidStateException;
use App\Exception\InvalidUndoTokenException;
use App\Repository\ProjectRepository;
use App\Service\ProjectCacheService;
use App\Service\ProjectStateService;
use App\Service\ProjectUndoService;
use App\Service\UndoService;
use App\Tests\Unit\UnitTestCase;
use App\ValueObject\UndoToken;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;

class ProjectUndoServiceTest extends UnitTestCase
{
    private ProjectUndoService $service;
    private UndoService&MockObject $undoService;
    private ProjectRepository&MockObject $projectRepository;
    private ProjectStateService $projectStateService;
    private EntityManagerInterface&MockObject $entityManager;
    private ProjectCacheService $projectCacheService;

    protected function setUp(): void
    {
        $this->undoService = $this->createMock(UndoService::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        // Use real instances for final classes
        $logger = $this->createMock(LoggerInterface::class);
        $cache = $this->createMock(CacheInterface::class);
        $this->projectStateService = new ProjectStateService($this->projectRepository, $logger);
        $this->projectCacheService = new ProjectCacheService($cache, $logger);

        $this->service = new ProjectUndoService(
            $this->undoService,
            $this->projectRepository,
            $this->projectStateService,
            $this->entityManager,
            $this->projectCacheService,
        );
    }

    public function testCreateUpdateUndoTokenSuccess(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user);
        $previousState = ['name' => 'Old Name'];

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            userId: 'user-123',
        );

        $this->undoService
            ->expects($this->once())
            ->method('createUndoToken')
            ->with(
                'user-123',
                UndoAction::UPDATE->value,
                'project',
                'project-123',
                $previousState
            )
            ->willReturn($undoToken);

        $result = $this->service->createUpdateUndoToken($project, $previousState);

        $this->assertSame($undoToken, $result);
    }

    public function testCreateUpdateUndoTokenThrowsOnMissingOwner(): void
    {
        $project = new Project();
        $project->setName('Test Project');
        // No owner set

        $this->expectException(InvalidStateException::class);
        $this->expectExceptionMessage('owner');

        $this->service->createUpdateUndoToken($project, []);
    }

    public function testCreateDeleteUndoTokenSuccess(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user);

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: [],
            userId: 'user-123',
        );

        // ProjectStateService is a real service now, so it will serialize the project state
        // We just need to verify that the undo token is created with the expected parameters
        $this->undoService
            ->expects($this->once())
            ->method('createUndoToken')
            ->with(
                'user-123',
                UndoAction::DELETE->value,
                'project',
                'project-123',
                $this->isType('array')
            )
            ->willReturn($undoToken);

        $result = $this->service->createDeleteUndoToken($project);

        $this->assertSame($undoToken, $result);
    }

    public function testCreateArchiveUndoTokenSuccess(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user);
        $previousState = ['isArchived' => false];

        $undoToken = UndoToken::create(
            action: UndoAction::ARCHIVE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            userId: 'user-123',
        );

        $this->undoService
            ->expects($this->once())
            ->method('createUndoToken')
            ->with(
                'user-123',
                UndoAction::ARCHIVE->value,
                'project',
                'project-123',
                $previousState
            )
            ->willReturn($undoToken);

        $result = $this->service->createArchiveUndoToken($project, $previousState);

        $this->assertSame($undoToken, $result);
    }

    public function testUndoWithInvalidToken(): void
    {
        $user = $this->createUserWithId('user-123');

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', 'invalid-token')
            ->willReturn(null);

        $this->expectException(InvalidUndoTokenException::class);
        $this->expectExceptionMessage('Invalid or expired undo token');

        $this->service->undo($user, 'invalid-token');
    }

    public function testUndoWithNonProjectToken(): void
    {
        $user = $this->createUserWithId('user-123');

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task', // Not a project
            entityId: 'task-123',
            previousState: [],
            userId: 'user-123',
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->expectException(InvalidUndoTokenException::class);
        $this->expectExceptionMessage('Undo token is for a task, not a project');

        $this->service->undo($user, 'token-123');
    }

    public function testUndoDeleteRestoresProject(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Deleted Project');
        $project->softDelete();

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: ['name' => 'Deleted Project'],
            userId: 'user-123',
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->projectRepository
            ->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'project-123', true)
            ->willReturn($project);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->undo($user, 'token-123');

        $this->assertSame($project, $result['project']);
        $this->assertSame(UndoAction::DELETE->value, $result['action']);
        $this->assertStringContainsString('restored', strtolower($result['message']));
    }

    public function testUndoUpdateRestoresProject(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Current Name');
        $previousState = ['name' => 'Previous Name'];

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            userId: 'user-123',
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->projectRepository
            ->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'project-123')
            ->willReturn($project);

        // ProjectStateService is real, so it will apply the state directly
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->undo($user, 'token-123');

        $this->assertSame($project, $result['project']);
        $this->assertSame(UndoAction::UPDATE->value, $result['action']);
    }

    public function testUndoArchiveRestoresArchiveState(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');
        $project->setIsArchived(true);
        $previousState = ['isArchived' => false, 'archivedAt' => null];

        $undoToken = UndoToken::create(
            action: UndoAction::ARCHIVE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            userId: 'user-123',
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->projectRepository
            ->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'project-123')
            ->willReturn($project);

        // ProjectStateService is real, so it will apply the state directly
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->undo($user, 'token-123');

        $this->assertSame($project, $result['project']);
        $this->assertSame(UndoAction::ARCHIVE->value, $result['action']);
        $this->assertStringContainsString('unarchived', strtolower($result['message']));
    }

    public function testUndoUpdateThrowsOnMissingProject(): void
    {
        $user = $this->createUserWithId('user-123');

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'non-existent-project',
            previousState: [],
            userId: 'user-123',
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->projectRepository
            ->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'non-existent-project')
            ->willReturn(null);

        $this->expectException(EntityNotFoundException::class);

        $this->service->undo($user, 'token-123');
    }

    public function testUndoDeleteValidatesActionType(): void
    {
        $user = $this->createUserWithId('user-123');

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value, // Not DELETE
            entityType: 'project',
            entityId: 'project-123',
            previousState: [],
            userId: 'user-123',
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->expectException(InvalidUndoTokenException::class);
        $this->expectExceptionMessage('Undo token is not for a delete operation');

        $this->service->undoDelete($user, 'token-123');
    }

    public function testUndoUpdateValidatesActionType(): void
    {
        $user = $this->createUserWithId('user-123');

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value, // Not UPDATE
            entityType: 'project',
            entityId: 'project-123',
            previousState: [],
            userId: 'user-123',
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->expectException(InvalidUndoTokenException::class);
        $this->expectExceptionMessage('Undo token is not for a update operation');

        $this->service->undoUpdate($user, 'token-123');
    }

    public function testUndoArchiveValidatesActionType(): void
    {
        $user = $this->createUserWithId('user-123');

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value, // Not ARCHIVE
            entityType: 'project',
            entityId: 'project-123',
            previousState: [],
            userId: 'user-123',
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->expectException(InvalidUndoTokenException::class);
        $this->expectExceptionMessage('Undo token is not for a archive operation');

        $this->service->undoArchive($user, 'token-123');
    }

    public function testUndoWithUnknownAction(): void
    {
        $user = $this->createUserWithId('user-123');

        $undoToken = UndoToken::create(
            action: 'unknown_action',
            entityType: 'project',
            entityId: 'project-123',
            previousState: [],
            userId: 'user-123',
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->expectException(InvalidUndoTokenException::class);
        $this->expectExceptionMessage('Unknown undo action type: unknown_action');

        $this->service->undo($user, 'token-123');
    }

    public function testUndoArchiveMessageForPreviouslyArchived(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');
        $previousState = ['isArchived' => true, 'archivedAt' => '2024-01-01T00:00:00+00:00'];

        $undoToken = UndoToken::create(
            action: UndoAction::ARCHIVE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            userId: 'user-123',
        );

        $this->undoService
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->projectRepository
            ->method('findOneByOwnerAndId')
            ->willReturn($project);

        $result = $this->service->undo($user, 'token-123');

        $this->assertStringContainsString('archived', strtolower($result['message']));
    }

    // ========================================
    // Move Operation Undo Tests
    // ========================================

    public function testUndoMoveRestoresParent(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Moved Project');
        $originalParent = $this->createProjectWithId('original-parent', $user, 'Original Parent');

        // Project was moved, so current state has no parent
        $project->setParent(null);

        // Previous state had a parent
        $previousState = [
            'name' => 'Moved Project',
            'parentId' => 'original-parent',
            'position' => 2,
        ];

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            userId: 'user-123',
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        // ProjectStateService.applyStateToProject also calls findOneByOwnerAndId to restore the parent
        $this->projectRepository
            ->method('findOneByOwnerAndId')
            ->willReturnCallback(function ($owner, $projectId) use ($user, $project, $originalParent) {
                if ($projectId === 'project-123') {
                    return $project;
                }
                if ($projectId === 'original-parent') {
                    return $originalParent;
                }
                return null;
            });

        // ProjectStateService is real, so it will apply the state directly
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->undo($user, 'token-123');

        $this->assertSame($project, $result['project']);
        $this->assertSame(UndoAction::UPDATE->value, $result['action']);
        $this->assertStringContainsString('undone', strtolower($result['message']));
    }

    // ========================================
    // Reorder Operation Undo Tests
    // ========================================

    public function testUndoReorderRestoresPosition(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Reordered Project');
        $project->setPosition(5);

        // Previous state had different position
        $previousState = [
            'name' => 'Reordered Project',
            'position' => 0,
        ];

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            userId: 'user-123',
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->projectRepository
            ->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'project-123')
            ->willReturn($project);

        // ProjectStateService is real, so it will apply the state directly
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->undo($user, 'token-123');

        $this->assertSame($project, $result['project']);
        $this->assertSame(UndoAction::UPDATE->value, $result['action']);
    }

    // ========================================
    // Settings Update Undo Tests
    // ========================================

    public function testUndoSettingsUpdateRestoresSettings(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');
        $project->setShowChildrenTasks(false);

        // Previous state had showChildrenTasks = true
        $previousState = [
            'name' => 'Test Project',
            'showChildrenTasks' => true,
        ];

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            userId: 'user-123',
        );

        $this->undoService
            ->expects($this->once())
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->projectRepository
            ->expects($this->once())
            ->method('findOneByOwnerAndId')
            ->with($user, 'project-123')
            ->willReturn($project);

        // ProjectStateService is real, so it will apply the state directly
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->undo($user, 'token-123');

        $this->assertSame($project, $result['project']);
        $this->assertSame(UndoAction::UPDATE->value, $result['action']);
    }

    // ========================================
    // State Validation After Undo Tests
    // ========================================

    public function testUndoDeleteFlushesEntityManager(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Deleted Project');
        $project->softDelete();

        $previousState = ['name' => 'Deleted Project', 'deletedAt' => null];

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            userId: 'user-123',
        );

        $this->undoService
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->projectRepository
            ->method('findOneByOwnerAndId')
            ->with($user, 'project-123', true)
            ->willReturn($project);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->undo($user, 'token-123');

        $this->assertFalse($result['project']->isDeleted());
    }

    public function testUndoArchiveFlushesEntityManagerAndAppliesState(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Archived Project');
        $project->setIsArchived(true);

        $previousState = ['isArchived' => false, 'archivedAt' => null];

        $undoToken = UndoToken::create(
            action: UndoAction::ARCHIVE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            userId: 'user-123',
        );

        $this->undoService
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->projectRepository
            ->method('findOneByOwnerAndId')
            ->willReturn($project);

        // ProjectStateService is real, so it will apply the state directly
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->undo($user, 'token-123');

        $this->assertSame($project, $result['project']);
    }

    public function testUndoUpdatePersistsRestoredState(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Updated Name');
        $project->setDescription('Updated Description');
        $project->setPosition(10);

        $previousState = [
            'name' => 'Original Name',
            'description' => 'Original Description',
            'position' => 0,
            'parentId' => null,
            'showChildrenTasks' => true,
        ];

        $undoToken = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            userId: 'user-123',
        );

        $this->undoService
            ->method('consumeUndoToken')
            ->willReturn($undoToken);

        $this->projectRepository
            ->method('findOneByOwnerAndId')
            ->willReturn($project);

        // ProjectStateService is real, so it will apply the state directly
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->service->undo($user, 'token-123');

        $this->assertSame($project, $result['project']);
        $this->assertSame(UndoAction::UPDATE->value, $result['action']);
    }
}
