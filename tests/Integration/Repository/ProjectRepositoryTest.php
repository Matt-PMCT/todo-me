<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Repository\ProjectRepository;
use App\Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for ProjectRepository.
 *
 * Tests query building logic including:
 * - Owner scoping (multi-tenant isolation)
 * - Archived/deleted filtering
 * - Search functionality
 * - Pagination
 * - Path-based lookup
 */
class ProjectRepositoryTest extends IntegrationTestCase
{
    private ProjectRepository $projectRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectRepository = static::getContainer()->get(ProjectRepository::class);
    }

    // ========================================
    // Owner Scoping Tests
    // ========================================

    public function testFindByOwnerReturnsOnlyOwnedProjects(): void
    {
        $user1 = $this->createUser('user1-proj@example.com');
        $user2 = $this->createUser('user2-proj@example.com');

        $this->createProject($user1, 'User 1 Project');
        $this->createProject($user2, 'User 2 Project');

        $results = $this->projectRepository->findByOwner($user1);

        $this->assertCount(1, $results);
        $this->assertEquals('User 1 Project', $results[0]->getName());
    }

    public function testFindOneByOwnerAndIdReturnsOnlyOwnedProject(): void
    {
        $user1 = $this->createUser('owner1@example.com');
        $user2 = $this->createUser('owner2@example.com');

        $project1 = $this->createProject($user1, 'User 1 Project');
        $project2 = $this->createProject($user2, 'User 2 Project');

        $found = $this->projectRepository->findOneByOwnerAndId($user1, $project1->getId());
        $this->assertNotNull($found);
        $this->assertEquals('User 1 Project', $found->getName());

        $notFound = $this->projectRepository->findOneByOwnerAndId($user1, $project2->getId());
        $this->assertNull($notFound);
    }

    // ========================================
    // Archive Filter Tests
    // ========================================

    public function testFindByOwnerExcludesArchivedByDefault(): void
    {
        $user = $this->createUser('archive-test@example.com');
        $this->createProject($user, 'Active Project', false);
        $this->createProject($user, 'Archived Project', true);

        $results = $this->projectRepository->findByOwner($user, includeArchived: false);

        $this->assertCount(1, $results);
        $this->assertEquals('Active Project', $results[0]->getName());
    }

    public function testFindByOwnerIncludesArchivedWhenSpecified(): void
    {
        $user = $this->createUser('archive-include@example.com');
        $this->createProject($user, 'Active Project', false);
        $this->createProject($user, 'Archived Project', true);

        $results = $this->projectRepository->findByOwner($user, includeArchived: true);

        $this->assertCount(2, $results);
    }

    public function testFindActiveByOwner(): void
    {
        $user = $this->createUser('active-only@example.com');
        $this->createProject($user, 'Active 1', false);
        $this->createProject($user, 'Active 2', false);
        $this->createProject($user, 'Archived', true);

        $results = $this->projectRepository->findActiveByOwner($user);

        $this->assertCount(2, $results);
    }

    public function testFindArchivedByOwner(): void
    {
        $user = $this->createUser('archived-only@example.com');
        $this->createProject($user, 'Active', false);
        $this->createProject($user, 'Archived 1', true);
        $this->createProject($user, 'Archived 2', true);

        $results = $this->projectRepository->findArchivedByOwner($user);

        $this->assertCount(2, $results);
    }

    // ========================================
    // Soft Delete Filter Tests
    // ========================================

    public function testFindByOwnerExcludesDeletedByDefault(): void
    {
        $user = $this->createUser('deleted-test@example.com');
        $this->createProject($user, 'Active Project');
        $deletedProject = $this->createProject($user, 'Deleted Project');
        $deletedProject->softDelete();
        $this->entityManager->flush();

        $results = $this->projectRepository->findByOwner($user);

        $this->assertCount(1, $results);
        $this->assertEquals('Active Project', $results[0]->getName());
    }

    public function testFindByOwnerIncludesDeletedWhenSpecified(): void
    {
        $user = $this->createUser('deleted-include@example.com');
        $this->createProject($user, 'Active Project');
        $deletedProject = $this->createProject($user, 'Deleted Project');
        $deletedProject->softDelete();
        $this->entityManager->flush();

        $results = $this->projectRepository->findByOwner($user, includeDeleted: true);

        $this->assertCount(2, $results);
    }

    public function testFindOneByOwnerAndIdExcludesDeletedByDefault(): void
    {
        $user = $this->createUser('find-deleted@example.com');
        $deletedProject = $this->createProject($user, 'Deleted Project');
        $deletedProject->softDelete();
        $this->entityManager->flush();

        $result = $this->projectRepository->findOneByOwnerAndId($user, $deletedProject->getId());

        $this->assertNull($result);
    }

    public function testFindOneByOwnerAndIdIncludesDeletedWhenSpecified(): void
    {
        $user = $this->createUser('find-deleted2@example.com');
        $deletedProject = $this->createProject($user, 'Deleted Project');
        $deletedProject->softDelete();
        $this->entityManager->flush();

        $result = $this->projectRepository->findOneByOwnerAndId($user, $deletedProject->getId(), includeDeleted: true);

        $this->assertNotNull($result);
        $this->assertEquals('Deleted Project', $result->getName());
    }

    public function testFindDeletedByOwner(): void
    {
        $user = $this->createUser('find-all-deleted@example.com');
        $this->createProject($user, 'Active');
        $deleted1 = $this->createProject($user, 'Deleted 1');
        $deleted1->softDelete();
        $deleted2 = $this->createProject($user, 'Deleted 2');
        $deleted2->softDelete();
        $this->entityManager->flush();

        $results = $this->projectRepository->findDeletedByOwner($user);

        $this->assertCount(2, $results);
    }

    // ========================================
    // Pagination Tests
    // ========================================

    public function testFindByOwnerPaginated(): void
    {
        $user = $this->createUser('paginated@example.com');
        for ($i = 1; $i <= 15; $i++) {
            $this->createProject($user, "Project $i");
        }

        $result = $this->projectRepository->findByOwnerPaginated($user, 1, 10);

        $this->assertCount(10, $result['projects']);
        $this->assertEquals(15, $result['total']);
    }

    public function testFindByOwnerPaginatedSecondPage(): void
    {
        $user = $this->createUser('paginated2@example.com');
        for ($i = 1; $i <= 15; $i++) {
            $this->createProject($user, "Project $i");
        }

        $result = $this->projectRepository->findByOwnerPaginated($user, 2, 10);

        $this->assertCount(5, $result['projects']);
        $this->assertEquals(15, $result['total']);
    }

    public function testFindByOwnerPaginatedEmpty(): void
    {
        $user = $this->createUser('paginated-empty@example.com');

        $result = $this->projectRepository->findByOwnerPaginated($user, 1, 10);

        $this->assertEmpty($result['projects']);
        $this->assertEquals(0, $result['total']);
    }

    // ========================================
    // Search Tests
    // ========================================

    public function testFindByNameInsensitive(): void
    {
        $user = $this->createUser('name-search@example.com');
        $this->createProject($user, 'Work Project');
        $this->createProject($user, 'Personal');

        $result = $this->projectRepository->findByNameInsensitive($user, 'work project');

        $this->assertNotNull($result);
        $this->assertEquals('Work Project', $result->getName());
    }

    public function testFindByNameInsensitiveNotFound(): void
    {
        $user = $this->createUser('name-notfound@example.com');
        $this->createProject($user, 'Work Project');

        $result = $this->projectRepository->findByNameInsensitive($user, 'Nonexistent');

        $this->assertNull($result);
    }

    public function testFindByNameInsensitiveExcludesArchived(): void
    {
        $user = $this->createUser('name-archived@example.com');
        $this->createProject($user, 'Work Project', true);

        $result = $this->projectRepository->findByNameInsensitive($user, 'work project');

        $this->assertNull($result);
    }

    public function testFindByNameInsensitiveExcludesDeleted(): void
    {
        $user = $this->createUser('name-deleted@example.com');
        $project = $this->createProject($user, 'Work Project');
        $project->softDelete();
        $this->entityManager->flush();

        $result = $this->projectRepository->findByNameInsensitive($user, 'work project');

        $this->assertNull($result);
    }

    public function testSearchByNamePrefix(): void
    {
        $user = $this->createUser('prefix-search@example.com');
        $this->createProject($user, 'Work Project');
        $this->createProject($user, 'Work Meetings');
        $this->createProject($user, 'Personal');

        $results = $this->projectRepository->searchByNamePrefix($user, 'Work');

        $this->assertCount(2, $results);
    }

    public function testSearchByNamePrefixIsCaseInsensitive(): void
    {
        $user = $this->createUser('prefix-case@example.com');
        $this->createProject($user, 'WORK Project');
        $this->createProject($user, 'work stuff');

        $results = $this->projectRepository->searchByNamePrefix($user, 'work');

        $this->assertCount(2, $results);
    }

    public function testSearchByNamePrefixRespectsLimit(): void
    {
        $user = $this->createUser('prefix-limit@example.com');
        for ($i = 1; $i <= 20; $i++) {
            $this->createProject($user, "Test Project $i");
        }

        $results = $this->projectRepository->searchByNamePrefix($user, 'Test', 5);

        $this->assertCount(5, $results);
    }

    // ========================================
    // Path-Based Lookup Tests
    // ========================================

    public function testFindByPathInsensitive(): void
    {
        $user = $this->createUser('path-lookup@example.com');
        $work = $this->createProject($user, 'Work');
        $meetings = $this->createProject($user, 'Meetings', parent: $work);
        $standup = $this->createProject($user, 'Standup', parent: $meetings);

        $result = $this->projectRepository->findByPathInsensitive($user, 'work/meetings/standup');

        $this->assertNotNull($result);
        $this->assertEquals('Standup', $result->getName());
    }

    public function testFindByPathInsensitiveIsCaseInsensitive(): void
    {
        $user = $this->createUser('path-case@example.com');
        $work = $this->createProject($user, 'Work');
        $meetings = $this->createProject($user, 'Meetings', parent: $work);

        $result = $this->projectRepository->findByPathInsensitive($user, 'WORK/MEETINGS');

        $this->assertNotNull($result);
        $this->assertEquals('Meetings', $result->getName());
    }

    public function testFindByPathInsensitiveNotFound(): void
    {
        $user = $this->createUser('path-notfound@example.com');
        $work = $this->createProject($user, 'Work');

        $result = $this->projectRepository->findByPathInsensitive($user, 'work/nonexistent');

        $this->assertNull($result);
    }

    public function testFindByPathInsensitiveExcludesDeleted(): void
    {
        $user = $this->createUser('path-deleted@example.com');
        $work = $this->createProject($user, 'Work');
        $work->softDelete();
        $this->entityManager->flush();

        $result = $this->projectRepository->findByPathInsensitive($user, 'work');

        $this->assertNull($result);
    }

    // ========================================
    // Task Count Tests
    // ========================================

    public function testCountTasksByProject(): void
    {
        $user = $this->createUser('count-tasks@example.com');
        $project = $this->createProject($user, 'Work');

        $this->createTask($user, 'Task 1', project: $project);
        $this->createTask($user, 'Task 2', project: $project);
        $this->createTask($user, 'Task 3', project: $project);

        $count = $this->projectRepository->countTasksByProject($project);

        $this->assertEquals(3, $count);
    }

    public function testCountCompletedTasksByProject(): void
    {
        $user = $this->createUser('count-completed@example.com');
        $project = $this->createProject($user, 'Work');

        $this->createTask($user, 'Pending', project: $project);
        $this->createTask($user, 'Completed 1', status: 'completed', project: $project);
        $this->createTask($user, 'Completed 2', status: 'completed', project: $project);

        $count = $this->projectRepository->countCompletedTasksByProject($project);

        $this->assertEquals(2, $count);
    }

    public function testGetTaskCountsForProjects(): void
    {
        $user = $this->createUser('batch-counts@example.com');
        $project1 = $this->createProject($user, 'Project 1');
        $project2 = $this->createProject($user, 'Project 2');

        $this->createTask($user, 'P1 Pending', project: $project1);
        $this->createTask($user, 'P1 Completed', status: 'completed', project: $project1);

        $this->createTask($user, 'P2 Pending 1', project: $project2);
        $this->createTask($user, 'P2 Pending 2', project: $project2);
        $this->createTask($user, 'P2 Completed', status: 'completed', project: $project2);

        $counts = $this->projectRepository->getTaskCountsForProjects([$project1, $project2]);

        $this->assertEquals(2, $counts[$project1->getId()]['total']);
        $this->assertEquals(1, $counts[$project1->getId()]['completed']);
        $this->assertEquals(3, $counts[$project2->getId()]['total']);
        $this->assertEquals(1, $counts[$project2->getId()]['completed']);
    }

    public function testGetTaskCountsForProjectsEmpty(): void
    {
        $counts = $this->projectRepository->getTaskCountsForProjects([]);

        $this->assertEmpty($counts);
    }
}
