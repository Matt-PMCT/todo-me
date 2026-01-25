<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Task;
use App\Repository\TaskRepository;
use App\Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for TaskRepository.
 *
 * Tests query building logic including:
 * - Owner scoping (multi-tenant isolation)
 * - Filter combinations (status, priority, date range)
 * - Tag filtering
 * - Search functionality
 * - Pagination
 */
class TaskRepositoryTest extends IntegrationTestCase
{
    private TaskRepository $taskRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taskRepository = static::getContainer()->get(TaskRepository::class);
    }

    // ========================================
    // Owner Scoping Tests
    // ========================================

    public function testFindByOwnerReturnsOnlyOwnedTasks(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $this->createTask($user1, 'User 1 Task');
        $this->createTask($user2, 'User 2 Task');

        $results = $this->taskRepository->findByOwner($user1);

        $this->assertCount(1, $results);
        $this->assertEquals('User 1 Task', $results[0]->getTitle());
    }

    public function testFindByOwnerAndIdReturnsOnlyOwnedTask(): void
    {
        $user1 = $this->createUser('user1-id@example.com');
        $user2 = $this->createUser('user2-id@example.com');

        $task1 = $this->createTask($user1, 'User 1 Task');
        $task2 = $this->createTask($user2, 'User 2 Task');

        // User 1 can find their own task
        $found = $this->taskRepository->findOneByOwnerAndId($user1, $task1->getId());
        $this->assertNotNull($found);
        $this->assertEquals('User 1 Task', $found->getTitle());

        // User 1 cannot find User 2's task
        $notFound = $this->taskRepository->findOneByOwnerAndId($user1, $task2->getId());
        $this->assertNull($notFound);
    }

    // ========================================
    // Status Filter Tests
    // ========================================

    public function testFindByOwnerWithStatusFilter(): void
    {
        $user = $this->createUser('status-filter@example.com');
        $this->createTask($user, 'Pending Task', Task::STATUS_PENDING);
        $this->createTask($user, 'Completed Task', Task::STATUS_COMPLETED);
        $this->createTask($user, 'In Progress Task', Task::STATUS_IN_PROGRESS);

        $results = $this->taskRepository->findByOwner($user, Task::STATUS_PENDING);

        $this->assertCount(1, $results);
        $this->assertEquals('Pending Task', $results[0]->getTitle());
    }

    public function testFilteredQueryBuilderWithStatusFilter(): void
    {
        $user = $this->createUser('qb-status@example.com');
        $this->createTask($user, 'Pending', Task::STATUS_PENDING);
        $this->createTask($user, 'Completed', Task::STATUS_COMPLETED);

        $qb = $this->taskRepository->createFilteredQueryBuilder($user, ['status' => Task::STATUS_COMPLETED]);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('Completed', $results[0]->getTitle());
    }

    // ========================================
    // Priority Filter Tests
    // ========================================

    public function testFilteredQueryBuilderWithPriorityFilter(): void
    {
        $user = $this->createUser('priority-filter@example.com');
        $this->createTask($user, 'Low Priority', priority: 1);
        $this->createTask($user, 'High Priority', priority: 4);
        $this->createTask($user, 'Medium Priority', priority: 2);

        $qb = $this->taskRepository->createFilteredQueryBuilder($user, ['priority' => 4]);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('High Priority', $results[0]->getTitle());
    }

    // ========================================
    // Date Range Filter Tests
    // ========================================

    public function testFilteredQueryBuilderWithDueDateRange(): void
    {
        $user = $this->createUser('date-range@example.com');
        $this->createTask($user, 'Past Due', dueDate: new \DateTimeImmutable('-7 days'));
        $this->createTask($user, 'Due Soon', dueDate: new \DateTimeImmutable('+3 days'));
        $this->createTask($user, 'Far Future', dueDate: new \DateTimeImmutable('+30 days'));

        $qb = $this->taskRepository->createFilteredQueryBuilder($user, [
            'dueAfter' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'dueBefore' => (new \DateTimeImmutable('+14 days'))->format('Y-m-d'),
        ]);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('Due Soon', $results[0]->getTitle());
    }

    public function testFindOverdueByOwner(): void
    {
        $user = $this->createUser('overdue@example.com');
        $this->createTask($user, 'Past Due', dueDate: new \DateTimeImmutable('-1 day'));
        $this->createTask($user, 'Future Due', dueDate: new \DateTimeImmutable('+7 days'));
        $this->createTask($user, 'Completed Past', status: Task::STATUS_COMPLETED, dueDate: new \DateTimeImmutable('-1 day'));

        $results = $this->taskRepository->findOverdueByOwner($user);

        $this->assertCount(1, $results);
        $this->assertEquals('Past Due', $results[0]->getTitle());
    }

    public function testFindDueSoonByOwner(): void
    {
        $user = $this->createUser('due-soon@example.com');
        $this->createTask($user, 'Due in 3 days', dueDate: new \DateTimeImmutable('+3 days'));
        $this->createTask($user, 'Due in 10 days', dueDate: new \DateTimeImmutable('+10 days'));
        $this->createTask($user, 'Overdue', dueDate: new \DateTimeImmutable('-1 day'));

        $results = $this->taskRepository->findDueSoonByOwner($user, 7);

        $this->assertCount(1, $results);
        $this->assertEquals('Due in 3 days', $results[0]->getTitle());
    }

    // ========================================
    // Project Filter Tests
    // ========================================

    public function testFilteredQueryBuilderWithProjectFilter(): void
    {
        $user = $this->createUser('project-filter@example.com');
        $project = $this->createProject($user, 'Work');

        $this->createTask($user, 'Project Task', project: $project);
        $this->createTask($user, 'No Project Task');

        $qb = $this->taskRepository->createFilteredQueryBuilder($user, [
            'projectId' => $project->getId(),
        ]);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('Project Task', $results[0]->getTitle());
    }

    // ========================================
    // Tag Filter Tests
    // ========================================

    public function testFilteredQueryBuilderWithTagFilter(): void
    {
        $user = $this->createUser('tag-filter@example.com');
        $urgentTag = $this->createTag($user, 'urgent');
        $homeTag = $this->createTag($user, 'home');

        $task1 = $this->createTask($user, 'Urgent Task');
        $task1->addTag($urgentTag);

        $task2 = $this->createTask($user, 'Home Task');
        $task2->addTag($homeTag);

        $this->entityManager->flush();

        $qb = $this->taskRepository->createFilteredQueryBuilder($user, [
            'tagIds' => [$urgentTag->getId()],
        ]);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('Urgent Task', $results[0]->getTitle());
    }

    // ========================================
    // Combined Filters Tests
    // ========================================

    public function testFilteredQueryBuilderWithMultipleFilters(): void
    {
        $user = $this->createUser('multi-filter@example.com');
        $project = $this->createProject($user, 'Work');

        $this->createTask($user, 'Match', Task::STATUS_PENDING, 4, $project);
        $this->createTask($user, 'Wrong Status', Task::STATUS_COMPLETED, 4, $project);
        $this->createTask($user, 'Wrong Priority', Task::STATUS_PENDING, 1, $project);
        $this->createTask($user, 'Wrong Project', Task::STATUS_PENDING, 4);

        $qb = $this->taskRepository->createFilteredQueryBuilder($user, [
            'status' => Task::STATUS_PENDING,
            'priority' => 4,
            'projectId' => $project->getId(),
        ]);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('Match', $results[0]->getTitle());
    }

    // ========================================
    // Search Tests
    // ========================================

    public function testFilteredQueryBuilderWithSearch(): void
    {
        $user = $this->createUser('search@example.com');

        $this->createTask($user, 'Buy groceries');
        $this->createTask($user, 'Call mom');
        $task3 = $this->createTask($user, 'Work meeting');
        $task3->setDescription('Discuss grocery budget');
        $this->entityManager->flush();

        $qb = $this->taskRepository->createFilteredQueryBuilder($user, [
            'search' => 'grocer',
        ]);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(2, $results);
    }

    public function testFilteredQueryBuilderSearchIsCaseInsensitive(): void
    {
        $user = $this->createUser('case-search@example.com');

        $this->createTask($user, 'Buy GROCERIES');
        $this->createTask($user, 'groceries shopping');

        $qb = $this->taskRepository->createFilteredQueryBuilder($user, [
            'search' => 'Groceries',
        ]);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(2, $results);
    }

    // ========================================
    // Pagination Tests
    // ========================================

    public function testFindByOwnerPaginatedQueryBuilder(): void
    {
        $user = $this->createUser('pagination@example.com');

        for ($i = 1; $i <= 25; $i++) {
            $task = $this->createTask($user, "Task $i");
            $task->setPosition($i);
        }
        $this->entityManager->flush();

        $qb = $this->taskRepository->findByOwnerPaginatedQueryBuilder($user);
        $qb->setFirstResult(0)->setMaxResults(10);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(10, $results);
    }

    public function testPaginationWithOffset(): void
    {
        $user = $this->createUser('pagination-offset@example.com');

        for ($i = 1; $i <= 25; $i++) {
            $task = $this->createTask($user, "Task $i");
            $task->setPosition($i);
        }
        $this->entityManager->flush();

        $qb = $this->taskRepository->findByOwnerPaginatedQueryBuilder($user);
        $qb->setFirstResult(10)->setMaxResults(10);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(10, $results);
    }

    public function testPaginationWithEmptyResults(): void
    {
        $user = $this->createUser('pagination-empty@example.com');

        $qb = $this->taskRepository->findByOwnerPaginatedQueryBuilder($user);
        $qb->setFirstResult(0)->setMaxResults(10);
        $results = $qb->getQuery()->getResult();

        $this->assertEmpty($results);
    }

    public function testPaginationLastPage(): void
    {
        $user = $this->createUser('pagination-last@example.com');

        for ($i = 1; $i <= 25; $i++) {
            $task = $this->createTask($user, "Task $i");
            $task->setPosition($i);
        }
        $this->entityManager->flush();

        $qb = $this->taskRepository->findByOwnerPaginatedQueryBuilder($user);
        $qb->setFirstResult(20)->setMaxResults(10);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(5, $results);
    }

    // ========================================
    // Position Tests
    // ========================================

    public function testGetMaxPosition(): void
    {
        $user = $this->createUser('max-position@example.com');

        $task1 = $this->createTask($user, 'Task 1');
        $task1->setPosition(5);
        $task2 = $this->createTask($user, 'Task 2');
        $task2->setPosition(10);
        $task3 = $this->createTask($user, 'Task 3');
        $task3->setPosition(3);
        $this->entityManager->flush();

        $maxPosition = $this->taskRepository->getMaxPosition($user);

        $this->assertEquals(10, $maxPosition);
    }

    public function testGetMaxPositionReturnsNegativeOneForNoTasks(): void
    {
        $user = $this->createUser('no-tasks@example.com');

        $maxPosition = $this->taskRepository->getMaxPosition($user);

        $this->assertEquals(-1, $maxPosition);
    }

    public function testGetMaxPositionWithProject(): void
    {
        $user = $this->createUser('max-pos-project@example.com');
        $project = $this->createProject($user, 'Work');

        $task1 = $this->createTask($user, 'Project Task', project: $project);
        $task1->setPosition(5);
        $task2 = $this->createTask($user, 'No Project Task');
        $task2->setPosition(100);
        $this->entityManager->flush();

        $maxPosition = $this->taskRepository->getMaxPosition($user, $project);

        $this->assertEquals(5, $maxPosition);
    }

    // ========================================
    // Reorder Tests
    // ========================================

    public function testReorderTasks(): void
    {
        $user = $this->createUser('reorder@example.com');

        $task1 = $this->createTask($user, 'Task 1');
        $task1->setPosition(0);
        $task2 = $this->createTask($user, 'Task 2');
        $task2->setPosition(1);
        $task3 = $this->createTask($user, 'Task 3');
        $task3->setPosition(2);
        $this->entityManager->flush();

        // Reorder to: Task 3, Task 1, Task 2
        $this->taskRepository->reorderTasks($user, [
            $task3->getId(),
            $task1->getId(),
            $task2->getId(),
        ]);

        $this->entityManager->clear();

        $reorderedTask1 = $this->taskRepository->find($task1->getId());
        $reorderedTask2 = $this->taskRepository->find($task2->getId());
        $reorderedTask3 = $this->taskRepository->find($task3->getId());

        $this->assertEquals(1, $reorderedTask1->getPosition());
        $this->assertEquals(2, $reorderedTask2->getPosition());
        $this->assertEquals(0, $reorderedTask3->getPosition());
    }

    // ========================================
    // Batch Find Tests
    // ========================================

    public function testFindByOwnerAndIds(): void
    {
        $user = $this->createUser('batch-find@example.com');

        $task1 = $this->createTask($user, 'Task 1');
        $task2 = $this->createTask($user, 'Task 2');
        $task3 = $this->createTask($user, 'Task 3');

        $results = $this->taskRepository->findByOwnerAndIds($user, [
            $task1->getId(),
            $task3->getId(),
        ]);

        $this->assertCount(2, $results);
        $titles = array_map(fn ($t) => $t->getTitle(), $results);
        $this->assertContains('Task 1', $titles);
        $this->assertContains('Task 3', $titles);
    }

    public function testFindByOwnerAndIdsWithEmptyArray(): void
    {
        $user = $this->createUser('batch-empty@example.com');
        $this->createTask($user, 'Task 1');

        $results = $this->taskRepository->findByOwnerAndIds($user, []);

        $this->assertEmpty($results);
    }
}
