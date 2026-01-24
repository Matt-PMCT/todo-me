<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Service\ProjectStateService;
use App\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class ProjectStateServiceTest extends UnitTestCase
{
    private ProjectRepository&MockObject $projectRepository;
    private ProjectStateService $service;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $logger = $this->createMock(LoggerInterface::class);
        $this->service = new ProjectStateService($this->projectRepository, $logger);
    }

    public function testSerializeProjectStateWithMinimalProject(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');

        $state = $this->service->serializeProjectState($project);

        $this->assertSame('Test Project', $state['name']);
        $this->assertNull($state['description']);
        $this->assertFalse($state['isArchived']);
        $this->assertNull($state['archivedAt']);
        $this->assertNull($state['deletedAt']);
    }

    public function testSerializeProjectStateWithFullProject(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');
        $project->setDescription('Test description');
        $project->setIsArchived(true);
        $project->setArchivedAt(new \DateTimeImmutable('2024-06-15T10:30:00+00:00'));

        $state = $this->service->serializeProjectState($project);

        $this->assertSame('Test Project', $state['name']);
        $this->assertSame('Test description', $state['description']);
        $this->assertTrue($state['isArchived']);
        $this->assertStringContainsString('2024-06-15', $state['archivedAt']);
        $this->assertNull($state['deletedAt']);
    }

    public function testSerializeProjectStateWithDeletedAt(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');
        $project->softDelete();

        $state = $this->service->serializeProjectState($project);

        $this->assertNotNull($state['deletedAt']);
    }

    public function testSerializeArchiveState(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');
        $project->setIsArchived(true);
        $project->setArchivedAt(new \DateTimeImmutable('2024-06-15T10:30:00+00:00'));

        $state = $this->service->serializeArchiveState($project);

        $this->assertArrayHasKey('isArchived', $state);
        $this->assertArrayHasKey('archivedAt', $state);
        $this->assertArrayNotHasKey('name', $state);
        $this->assertArrayNotHasKey('description', $state);
        $this->assertTrue($state['isArchived']);
        $this->assertStringContainsString('2024-06-15', $state['archivedAt']);
    }

    public function testSerializeArchiveStateWithUnarchived(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');

        $state = $this->service->serializeArchiveState($project);

        $this->assertFalse($state['isArchived']);
        $this->assertNull($state['archivedAt']);
    }

    public function testApplyStateToProjectUpdatesName(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Original Name');

        $this->service->applyStateToProject($project, ['name' => 'Updated Name']);

        $this->assertSame('Updated Name', $project->getName());
    }

    public function testApplyStateToProjectUpdatesDescription(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');

        $this->service->applyStateToProject($project, ['description' => 'Updated description']);

        $this->assertSame('Updated description', $project->getDescription());
    }

    public function testApplyStateToProjectClearsDescription(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');
        $project->setDescription('Original description');

        $this->service->applyStateToProject($project, ['description' => null]);

        $this->assertNull($project->getDescription());
    }

    public function testApplyStateToProjectUpdatesArchiveStatus(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');

        $this->service->applyStateToProject($project, [
            'isArchived' => true,
            'archivedAt' => '2024-06-15T10:30:00+00:00',
        ]);

        $this->assertTrue($project->isArchived());
        $this->assertNotNull($project->getArchivedAt());
        $this->assertSame('2024-06-15', $project->getArchivedAt()->format('Y-m-d'));
    }

    public function testApplyStateToProjectClearsArchiveStatus(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');
        $project->setIsArchived(true);
        $project->setArchivedAt(new \DateTimeImmutable());

        $this->service->applyStateToProject($project, [
            'isArchived' => false,
            'archivedAt' => null,
        ]);

        $this->assertFalse($project->isArchived());
        $this->assertNull($project->getArchivedAt());
    }

    public function testApplyStateToProjectRestoresDeletedAt(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');

        $this->service->applyStateToProject($project, [
            'deletedAt' => '2024-06-15T10:30:00+00:00',
        ]);

        $this->assertNotNull($project->getDeletedAt());
        $this->assertSame('2024-06-15', $project->getDeletedAt()->format('Y-m-d'));
    }

    public function testApplyStateToProjectClearsDeletedAt(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Test Project');
        $project->softDelete();

        $this->service->applyStateToProject($project, ['deletedAt' => null]);

        $this->assertNull($project->getDeletedAt());
    }

    public function testApplyStateToProjectIgnoresMissingKeys(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user, 'Original Name');
        $project->setDescription('Original description');

        // Only update name, leave description unchanged
        $this->service->applyStateToProject($project, ['name' => 'Updated Name']);

        $this->assertSame('Updated Name', $project->getName());
        $this->assertSame('Original description', $project->getDescription());
    }
}
