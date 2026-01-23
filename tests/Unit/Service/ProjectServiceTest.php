<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\CreateProjectRequest;
use App\DTO\UpdateProjectRequest;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\UndoAction;
use App\Exception\EntityNotFoundException;
use App\Repository\ProjectRepository;
use App\Service\OwnershipChecker;
use App\Service\ProjectService;
use App\Service\UndoService;
use App\Service\ValidationHelper;
use App\Tests\Unit\UnitTestCase;
use App\ValueObject\UndoToken;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class ProjectServiceTest extends UnitTestCase
{
    private ProjectRepository&MockObject $projectRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private UndoService&MockObject $undoService;
    private ValidationHelper&MockObject $validationHelper;
    private OwnershipChecker&MockObject $ownershipChecker;
    private ProjectService $projectService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->undoService = $this->createMock(UndoService::class);
        $this->validationHelper = $this->createMock(ValidationHelper::class);
        $this->ownershipChecker = $this->createMock(OwnershipChecker::class);

        $this->projectService = new ProjectService(
            $this->projectRepository,
            $this->entityManager,
            $this->undoService,
            $this->validationHelper,
            $this->ownershipChecker,
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

        $this->projectRepository->expects($this->once())
            ->method('remove')
            ->with($project, true);

        $result = $this->projectService->delete($project);

        $this->assertSame($undoToken, $result);
    }

    public function testDeleteProjectRemovesFromDatabase(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user);

        $this->undoService->method('createUndoToken');

        $this->projectRepository->expects($this->once())
            ->method('remove')
            ->with($project, true);

        $this->projectService->delete($project);
    }

    /**
     * Note: Cascade deletion of tasks is handled by Doctrine ORM mapping.
     * The service only stores the project state for undo, not its tasks.
     */
    public function testDeleteProjectStoresOnlyProjectStateForUndo(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');
        $project->setDescription('Test Description');

        $capturedPreviousState = null;

        $this->undoService->expects($this->once())
            ->method('createUndoToken')
            ->willReturnCallback(function ($userId, $action, $entityType, $entityId, $previousState) use (&$capturedPreviousState) {
                $capturedPreviousState = $previousState;
                return UndoToken::create($action, $entityType, $entityId, $previousState);
            });

        $this->projectRepository->method('remove');

        $this->projectService->delete($project);

        $this->assertArrayHasKey('name', $capturedPreviousState);
        $this->assertArrayHasKey('description', $capturedPreviousState);
        $this->assertArrayHasKey('isArchived', $capturedPreviousState);
        $this->assertEquals('Test Project', $capturedPreviousState['name']);
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
            previousState: ['isArchived' => false],
        );

        $this->undoService->expects($this->once())
            ->method('createUndoToken')
            ->with(
                'user-123',
                UndoAction::ARCHIVE->value,
                'project',
                'project-123',
                ['isArchived' => false]
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
            previousState: ['isArchived' => true],
        );

        $this->undoService->expects($this->once())
            ->method('createUndoToken')
            ->with(
                'user-123',
                UndoAction::ARCHIVE->value,
                'project',
                'project-123',
                ['isArchived' => true]
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

        $this->expectException(\InvalidArgumentException::class);
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
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->expectException(\InvalidArgumentException::class);
        $this->projectService->undoArchive($user, $undoToken->token);
    }

    // ========================================
    // Undo Delete Tests
    // ========================================

    public function testUndoDeleteRestoresProject(): void
    {
        $user = $this->createUserWithId();

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: [
                'name' => 'Deleted Project',
                'description' => 'Project description',
                'isArchived' => false,
            ],
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->projectRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Project::class), true);

        $project = $this->projectService->undoDelete($user, $undoToken->token);

        $this->assertEquals('Deleted Project', $project->getName());
        $this->assertEquals('Project description', $project->getDescription());
        $this->assertFalse($project->isArchived());
        $this->assertSame($user, $project->getOwner());
    }

    public function testUndoDeleteWithInvalidTokenThrowsException(): void
    {
        $user = $this->createUserWithId();

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', 'invalid-token')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
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
        );

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->expectException(\InvalidArgumentException::class);
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
        );

        $this->undoService->expects($this->once())
            ->method('getUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

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
        );

        $this->undoService->expects($this->once())
            ->method('getUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

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

        $undoToken = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: [
                'name' => 'Deleted Project',
                'description' => null,
                'isArchived' => false,
            ],
        );

        $this->undoService->expects($this->once())
            ->method('getUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->undoService->expects($this->once())
            ->method('consumeUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->projectRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Project::class), true);

        $result = $this->projectService->undo($user, $undoToken->token);

        $this->assertEquals('Deleted Project', $result['project']->getName());
        $this->assertEquals(UndoAction::DELETE->value, $result['action']);
        $this->assertNotNull($result['warning']);
    }

    public function testUndoWithInvalidTokenThrowsException(): void
    {
        $user = $this->createUserWithId();

        $this->undoService->expects($this->once())
            ->method('getUndoToken')
            ->with('user-123', 'invalid')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
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
        );

        $this->undoService->expects($this->once())
            ->method('getUndoToken')
            ->with('user-123', $undoToken->token)
            ->willReturn($undoToken);

        $this->expectException(\InvalidArgumentException::class);
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
}
