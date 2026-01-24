<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Enum\UndoAction;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidStateException;
use App\Exception\InvalidUndoTokenException;
use App\Repository\ProjectRepository;
use App\Service\ProjectStateService;
use App\Service\ProjectUndoService;
use App\Service\UndoService;
use App\Tests\Unit\UnitTestCase;
use App\ValueObject\UndoToken;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class ProjectUndoServiceTest extends UnitTestCase
{
    private ProjectUndoService $service;
    private UndoService&MockObject $undoService;
    private ProjectRepository&MockObject $projectRepository;
    private ProjectStateService&MockObject $projectStateService;
    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->undoService = $this->createMock(UndoService::class);
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->projectStateService = $this->createMock(ProjectStateService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new ProjectUndoService(
            $this->undoService,
            $this->projectRepository,
            $this->projectStateService,
            $this->entityManager,
        );
    }

    public function testCreateUpdateUndoTokenSuccess(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user);
        $previousState = ['name' => 'Old Name'];

        $undoToken = new UndoToken(
            token: 'undo-token-123',
            userId: 'user-123',
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            expiresAt: new \DateTimeImmutable('+60 seconds'),
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
        $serializedState = ['name' => 'Test Project'];

        $undoToken = new UndoToken(
            token: 'undo-token-123',
            userId: 'user-123',
            action: UndoAction::DELETE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $serializedState,
            expiresAt: new \DateTimeImmutable('+60 seconds'),
        );

        $this->projectStateService
            ->expects($this->once())
            ->method('serializeProjectState')
            ->with($project)
            ->willReturn($serializedState);

        $this->undoService
            ->expects($this->once())
            ->method('createUndoToken')
            ->willReturn($undoToken);

        $result = $this->service->createDeleteUndoToken($project);

        $this->assertSame($undoToken, $result);
    }

    public function testCreateArchiveUndoTokenSuccess(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user);
        $previousState = ['isArchived' => false];

        $undoToken = new UndoToken(
            token: 'undo-token-123',
            userId: 'user-123',
            action: UndoAction::ARCHIVE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            expiresAt: new \DateTimeImmutable('+60 seconds'),
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

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::DELETE->value,
            entityType: 'task', // Not a project
            entityId: 'task-123',
            previousState: [],
            expiresAt: new \DateTimeImmutable('+60 seconds'),
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

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::DELETE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: ['name' => 'Deleted Project'],
            expiresAt: new \DateTimeImmutable('+60 seconds'),
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

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            expiresAt: new \DateTimeImmutable('+60 seconds'),
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

        $this->projectStateService
            ->expects($this->once())
            ->method('applyStateToProject')
            ->with($project, $previousState);

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

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::ARCHIVE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            expiresAt: new \DateTimeImmutable('+60 seconds'),
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

        $this->projectStateService
            ->expects($this->once())
            ->method('applyStateToProject')
            ->with($project, $previousState);

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

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'non-existent-project',
            previousState: [],
            expiresAt: new \DateTimeImmutable('+60 seconds'),
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

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::UPDATE->value, // Not DELETE
            entityType: 'project',
            entityId: 'project-123',
            previousState: [],
            expiresAt: new \DateTimeImmutable('+60 seconds'),
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

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::DELETE->value, // Not UPDATE
            entityType: 'project',
            entityId: 'project-123',
            previousState: [],
            expiresAt: new \DateTimeImmutable('+60 seconds'),
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

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::DELETE->value, // Not ARCHIVE
            entityType: 'project',
            entityId: 'project-123',
            previousState: [],
            expiresAt: new \DateTimeImmutable('+60 seconds'),
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

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: 'unknown_action',
            entityType: 'project',
            entityId: 'project-123',
            previousState: [],
            expiresAt: new \DateTimeImmutable('+60 seconds'),
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

        $undoToken = new UndoToken(
            token: 'token-123',
            userId: 'user-123',
            action: UndoAction::ARCHIVE->value,
            entityType: 'project',
            entityId: 'project-123',
            previousState: $previousState,
            expiresAt: new \DateTimeImmutable('+60 seconds'),
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
}
