<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\DTO\TaskFilterRequest;
use App\DTO\TaskSortRequest;
use App\Entity\Task;
use App\Repository\TaskRepository;
use App\Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for TaskRepository advanced filtering methods.
 *
 * Tests the createAdvancedFilteredQueryBuilder and view-specific methods.
 */
class TaskFilterRepositoryTest extends IntegrationTestCase
{
    private TaskRepository $taskRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taskRepository = static::getContainer()->get(TaskRepository::class);
    }

    // ========================================
    // createAdvancedFilteredQueryBuilder Tests
    // ========================================

    public function testAdvancedFilterByStatuses(): void
    {
        $user = $this->createUser('status-filter@example.com');
        $this->createTask($user, 'Pending Task', Task::STATUS_PENDING);
        $this->createTask($user, 'In Progress Task', Task::STATUS_IN_PROGRESS);
        $this->createTask($user, 'Completed Task', Task::STATUS_COMPLETED);

        $filterRequest = new TaskFilterRequest(
            statuses: [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS]
        );
        $sortRequest = new TaskSortRequest();

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(2, $results);
        $titles = array_map(fn(Task $t) => $t->getTitle(), $results);
        $this->assertContains('Pending Task', $titles);
        $this->assertContains('In Progress Task', $titles);
        $this->assertNotContains('Completed Task', $titles);
    }

    public function testAdvancedFilterByPriorityRange(): void
    {
        $user = $this->createUser('priority-range@example.com');
        $this->createTask($user, 'Low Priority', Task::STATUS_PENDING, 1);
        $this->createTask($user, 'Medium Priority', Task::STATUS_PENDING, 2);
        $this->createTask($user, 'High Priority', Task::STATUS_PENDING, 3);
        $this->createTask($user, 'Urgent Priority', Task::STATUS_PENDING, 4);

        $filterRequest = new TaskFilterRequest(
            priorityMin: 2,
            priorityMax: 3
        );
        $sortRequest = new TaskSortRequest();

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(2, $results);
        $titles = array_map(fn(Task $t) => $t->getTitle(), $results);
        $this->assertContains('Medium Priority', $titles);
        $this->assertContains('High Priority', $titles);
    }

    public function testAdvancedFilterByProjectIds(): void
    {
        $user = $this->createUser('project-filter@example.com');
        $project1 = $this->createProject($user, 'Project 1');
        $project2 = $this->createProject($user, 'Project 2');

        $this->createTask($user, 'Project 1 Task', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, $project1);
        $this->createTask($user, 'Project 2 Task', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, $project2);
        $this->createTask($user, 'No Project Task', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null);

        $filterRequest = new TaskFilterRequest(
            projectIds: [$project1->getId()]
        );
        $sortRequest = new TaskSortRequest();

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('Project 1 Task', $results[0]->getTitle());
    }

    public function testAdvancedFilterByProjectIdsWithChildren(): void
    {
        $user = $this->createUser('project-children@example.com');
        $parentProject = $this->createProject($user, 'Parent Project');
        $childProject = $this->createProject($user, 'Child Project', false, $parentProject);

        $this->createTask($user, 'Parent Task', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, $parentProject);
        $this->createTask($user, 'Child Task', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, $childProject);
        $this->createTask($user, 'Other Task', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null);

        $filterRequest = new TaskFilterRequest(
            projectIds: [$parentProject->getId()],
            includeChildProjects: true
        );
        $sortRequest = new TaskSortRequest();

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(2, $results);
        $titles = array_map(fn(Task $t) => $t->getTitle(), $results);
        $this->assertContains('Parent Task', $titles);
        $this->assertContains('Child Task', $titles);
    }

    public function testAdvancedFilterByTagIdsOrMode(): void
    {
        $user = $this->createUser('tag-or@example.com');
        $tag1 = $this->createTag($user, 'urgent');
        $tag2 = $this->createTag($user, 'home');

        $task1 = $this->createTask($user, 'Urgent Task');
        $task1->addTag($tag1);

        $task2 = $this->createTask($user, 'Home Task');
        $task2->addTag($tag2);

        $task3 = $this->createTask($user, 'Both Tags Task');
        $task3->addTag($tag1);
        $task3->addTag($tag2);

        $this->createTask($user, 'No Tag Task');

        $this->entityManager->flush();

        $filterRequest = new TaskFilterRequest(
            tagIds: [$tag1->getId(), $tag2->getId()],
            tagMode: 'OR'
        );
        $sortRequest = new TaskSortRequest();

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(3, $results);
        $titles = array_map(fn(Task $t) => $t->getTitle(), $results);
        $this->assertContains('Urgent Task', $titles);
        $this->assertContains('Home Task', $titles);
        $this->assertContains('Both Tags Task', $titles);
        $this->assertNotContains('No Tag Task', $titles);
    }

    public function testAdvancedFilterByTagIdsAndMode(): void
    {
        $user = $this->createUser('tag-and@example.com');
        $tag1 = $this->createTag($user, 'urgent');
        $tag2 = $this->createTag($user, 'work');

        $task1 = $this->createTask($user, 'Urgent Only');
        $task1->addTag($tag1);

        $task2 = $this->createTask($user, 'Work Only');
        $task2->addTag($tag2);

        $task3 = $this->createTask($user, 'Both Tags');
        $task3->addTag($tag1);
        $task3->addTag($tag2);

        $this->entityManager->flush();

        $filterRequest = new TaskFilterRequest(
            tagIds: [$tag1->getId(), $tag2->getId()],
            tagMode: 'AND'
        );
        $sortRequest = new TaskSortRequest();

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('Both Tags', $results[0]->getTitle());
    }

    public function testAdvancedFilterByDueDateRange(): void
    {
        $user = $this->createUser('date-range@example.com');
        $this->createTask($user, 'Past Due', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('-7 days'));
        $this->createTask($user, 'Due Soon', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('+3 days'));
        $this->createTask($user, 'Far Future', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('+30 days'));

        $filterRequest = new TaskFilterRequest(
            dueAfter: (new \DateTimeImmutable('today'))->format('Y-m-d'),
            dueBefore: (new \DateTimeImmutable('+14 days'))->format('Y-m-d')
        );
        $sortRequest = new TaskSortRequest();

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('Due Soon', $results[0]->getTitle());
    }

    public function testAdvancedFilterByHasNoDueDate(): void
    {
        $user = $this->createUser('no-due-date@example.com');
        $this->createTask($user, 'With Due Date', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('+1 week'));
        $this->createTask($user, 'Without Due Date', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, null);

        $filterRequest = new TaskFilterRequest(
            hasNoDueDate: true
        );
        $sortRequest = new TaskSortRequest();

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('Without Due Date', $results[0]->getTitle());
    }

    public function testAdvancedFilterByHasDueDate(): void
    {
        $user = $this->createUser('has-due-date@example.com');
        $this->createTask($user, 'With Due Date', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('+1 week'));
        $this->createTask($user, 'Without Due Date', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, null);

        $filterRequest = new TaskFilterRequest(
            hasNoDueDate: false
        );
        $sortRequest = new TaskSortRequest();

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('With Due Date', $results[0]->getTitle());
    }

    public function testAdvancedFilterBySearch(): void
    {
        $user = $this->createUser('search-filter@example.com');
        $this->createTask($user, 'Buy groceries');
        $this->createTask($user, 'Call mom');
        $task3 = $this->createTask($user, 'Work meeting');
        $task3->setDescription('Discuss grocery budget');
        $this->entityManager->flush();

        $filterRequest = new TaskFilterRequest(
            search: 'grocer'
        );
        $sortRequest = new TaskSortRequest();

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(2, $results);
        $titles = array_map(fn(Task $t) => $t->getTitle(), $results);
        $this->assertContains('Buy groceries', $titles);
        $this->assertContains('Work meeting', $titles);
    }

    public function testAdvancedFilterExcludesCompleted(): void
    {
        $user = $this->createUser('exclude-completed@example.com');
        $this->createTask($user, 'Pending Task', Task::STATUS_PENDING);
        $this->createTask($user, 'Completed Task', Task::STATUS_COMPLETED);

        $filterRequest = new TaskFilterRequest(
            includeCompleted: false
        );
        $sortRequest = new TaskSortRequest();

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('Pending Task', $results[0]->getTitle());
    }

    public function testAdvancedFilterIncludesCompletedByDefault(): void
    {
        $user = $this->createUser('include-completed@example.com');
        $this->createTask($user, 'Pending Task', Task::STATUS_PENDING);
        $this->createTask($user, 'Completed Task', Task::STATUS_COMPLETED);

        $filterRequest = new TaskFilterRequest(); // includeCompleted defaults to true
        $sortRequest = new TaskSortRequest();

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(2, $results);
    }

    public function testAdvancedFilterCombinesMultipleFilters(): void
    {
        $user = $this->createUser('combined-filter@example.com');
        $project = $this->createProject($user, 'Work');
        $tag = $this->createTag($user, 'urgent');

        // Should match: pending, high priority, in project, has tag, due soon
        $match = $this->createTask($user, 'Match Task', Task::STATUS_PENDING, 4, $project, new \DateTimeImmutable('+2 days'));
        $match->addTag($tag);

        // Should not match: wrong status
        $this->createTask($user, 'Completed', Task::STATUS_COMPLETED, 4, $project, new \DateTimeImmutable('+2 days'));

        // Should not match: wrong priority
        $this->createTask($user, 'Low Priority', Task::STATUS_PENDING, 1, $project, new \DateTimeImmutable('+2 days'));

        // Should not match: no project
        $this->createTask($user, 'No Project', Task::STATUS_PENDING, 4, null, new \DateTimeImmutable('+2 days'));

        $this->entityManager->flush();

        $filterRequest = new TaskFilterRequest(
            statuses: [Task::STATUS_PENDING],
            priorityMin: 3,
            projectIds: [$project->getId()],
            tagIds: [$tag->getId()],
            dueAfter: (new \DateTimeImmutable('today'))->format('Y-m-d'),
            dueBefore: (new \DateTimeImmutable('+7 days'))->format('Y-m-d')
        );
        $sortRequest = new TaskSortRequest();

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('Match Task', $results[0]->getTitle());
    }

    // ========================================
    // View-specific Methods Tests
    // ========================================

    public function testFindTodayTasksIncludesOverdue(): void
    {
        $user = $this->createUser('today-overdue@example.com');
        $this->createTask($user, 'Today Task', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('today'));
        $this->createTask($user, 'Overdue Task', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('-3 days'));
        $this->createTask($user, 'Future Task', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('+3 days'));

        $results = $this->taskRepository->findTodayTasks($user);

        $this->assertCount(2, $results);
        $titles = array_map(fn(Task $t) => $t->getTitle(), $results);
        $this->assertContains('Today Task', $titles);
        $this->assertContains('Overdue Task', $titles);
        $this->assertNotContains('Future Task', $titles);
    }

    public function testFindTodayTasksExcludesCompleted(): void
    {
        $user = $this->createUser('today-completed@example.com');
        $this->createTask($user, 'Pending Today', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('today'));
        $this->createTask($user, 'Completed Today', Task::STATUS_COMPLETED, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('today'));

        $results = $this->taskRepository->findTodayTasks($user);

        $this->assertCount(1, $results);
        $this->assertEquals('Pending Today', $results[0]->getTitle());
    }

    public function testFindUpcomingTasksWithinDays(): void
    {
        $user = $this->createUser('upcoming-days@example.com');
        $this->createTask($user, 'Due Tomorrow', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('+1 day'));
        $this->createTask($user, 'Due in Week', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('+5 days'));
        $this->createTask($user, 'Due in Month', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('+30 days'));
        $this->createTask($user, 'Due Today', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('today'));

        $results = $this->taskRepository->findUpcomingTasks($user, 7);

        $this->assertCount(2, $results);
        $titles = array_map(fn(Task $t) => $t->getTitle(), $results);
        $this->assertContains('Due Tomorrow', $titles);
        $this->assertContains('Due in Week', $titles);
        $this->assertNotContains('Due in Month', $titles);
        $this->assertNotContains('Due Today', $titles);
    }

    public function testFindUpcomingTasksExcludesCompleted(): void
    {
        $user = $this->createUser('upcoming-completed@example.com');
        $this->createTask($user, 'Pending Upcoming', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('+3 days'));
        $this->createTask($user, 'Completed Upcoming', Task::STATUS_COMPLETED, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('+3 days'));

        $results = $this->taskRepository->findUpcomingTasks($user, 7);

        $this->assertCount(1, $results);
        $this->assertEquals('Pending Upcoming', $results[0]->getTitle());
    }

    public function testFindTasksWithNoDueDateExcludesCompleted(): void
    {
        $user = $this->createUser('nodate-completed@example.com');
        $this->createTask($user, 'Pending No Date', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, null);
        $this->createTask($user, 'Completed No Date', Task::STATUS_COMPLETED, Task::PRIORITY_DEFAULT, null, null);

        $results = $this->taskRepository->findTasksWithNoDueDate($user);

        $this->assertCount(1, $results);
        $this->assertEquals('Pending No Date', $results[0]->getTitle());
    }

    public function testFindTasksWithNoDueDateExcludesTasksWithDate(): void
    {
        $user = $this->createUser('nodate-hasdate@example.com');
        $this->createTask($user, 'No Date Task', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, null);
        $this->createTask($user, 'Has Date Task', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('+1 week'));

        $results = $this->taskRepository->findTasksWithNoDueDate($user);

        $this->assertCount(1, $results);
        $this->assertEquals('No Date Task', $results[0]->getTitle());
    }

    public function testFindCompletedTasksRecentOrdering(): void
    {
        $user = $this->createUser('completed-order@example.com');

        $task1 = $this->createTask($user, 'Completed First', Task::STATUS_COMPLETED);
        $task1->setCompletedAt(new \DateTimeImmutable('-3 days'));

        $task2 = $this->createTask($user, 'Completed Second', Task::STATUS_COMPLETED);
        $task2->setCompletedAt(new \DateTimeImmutable('-1 day'));

        $task3 = $this->createTask($user, 'Completed Third', Task::STATUS_COMPLETED);
        $task3->setCompletedAt(new \DateTimeImmutable('now'));

        $this->entityManager->flush();

        $results = $this->taskRepository->findCompletedTasksRecent($user, 10);

        $this->assertCount(3, $results);
        // Should be ordered by completedAt DESC (most recent first)
        $this->assertEquals('Completed Third', $results[0]->getTitle());
        $this->assertEquals('Completed Second', $results[1]->getTitle());
        $this->assertEquals('Completed First', $results[2]->getTitle());
    }

    public function testFindCompletedTasksRecentLimitsResults(): void
    {
        $user = $this->createUser('completed-limit@example.com');

        for ($i = 1; $i <= 10; $i++) {
            $task = $this->createTask($user, "Completed Task $i", Task::STATUS_COMPLETED);
            $task->setCompletedAt(new \DateTimeImmutable("-{$i} days"));
        }
        $this->entityManager->flush();

        $results = $this->taskRepository->findCompletedTasksRecent($user, 5);

        $this->assertCount(5, $results);
    }

    // ========================================
    // Sorting Tests
    // ========================================

    public function testSortByDueDateNullsLast(): void
    {
        $user = $this->createUser('sort-due-date@example.com');
        $this->createTask($user, 'No Due Date', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, null);
        $this->createTask($user, 'Due Soon', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('+1 day'));
        $this->createTask($user, 'Due Later', Task::STATUS_PENDING, Task::PRIORITY_DEFAULT, null, new \DateTimeImmutable('+7 days'));

        $filterRequest = new TaskFilterRequest();
        $sortRequest = new TaskSortRequest(field: 'due_date', direction: 'ASC');

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(3, $results);
        // Tasks with due dates should come first, sorted by date
        $this->assertEquals('Due Soon', $results[0]->getTitle());
        $this->assertEquals('Due Later', $results[1]->getTitle());
        // Task with no due date should be last (nulls last)
        $this->assertEquals('No Due Date', $results[2]->getTitle());
    }

    public function testSortByPriority(): void
    {
        $user = $this->createUser('sort-priority@example.com');
        $this->createTask($user, 'Low Priority', Task::STATUS_PENDING, 1);
        $this->createTask($user, 'High Priority', Task::STATUS_PENDING, 4);
        $this->createTask($user, 'Medium Priority', Task::STATUS_PENDING, 2);

        $filterRequest = new TaskFilterRequest();
        $sortRequest = new TaskSortRequest(field: 'priority', direction: 'DESC');

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(3, $results);
        $this->assertEquals('High Priority', $results[0]->getTitle());
        $this->assertEquals('Medium Priority', $results[1]->getTitle());
        $this->assertEquals('Low Priority', $results[2]->getTitle());
    }

    public function testSortByTitle(): void
    {
        $user = $this->createUser('sort-title@example.com');
        $this->createTask($user, 'Charlie Task');
        $this->createTask($user, 'Alpha Task');
        $this->createTask($user, 'Beta Task');

        $filterRequest = new TaskFilterRequest();
        $sortRequest = new TaskSortRequest(field: 'title', direction: 'ASC');

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(3, $results);
        $this->assertEquals('Alpha Task', $results[0]->getTitle());
        $this->assertEquals('Beta Task', $results[1]->getTitle());
        $this->assertEquals('Charlie Task', $results[2]->getTitle());
    }

    public function testSortByCreatedAt(): void
    {
        $user = $this->createUser('sort-created@example.com');

        // Create tasks in sequence to ensure distinct createdAt
        $task1 = $this->createTask($user, 'First Created');
        usleep(1000); // Small delay to ensure different timestamps
        $task2 = $this->createTask($user, 'Second Created');
        usleep(1000);
        $task3 = $this->createTask($user, 'Third Created');

        $filterRequest = new TaskFilterRequest();
        $sortRequest = new TaskSortRequest(field: 'created_at', direction: 'DESC');

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(3, $results);
        // Most recently created first
        $this->assertEquals('Third Created', $results[0]->getTitle());
        $this->assertEquals('Second Created', $results[1]->getTitle());
        $this->assertEquals('First Created', $results[2]->getTitle());
    }

    // ========================================
    // Multi-tenant Isolation Tests
    // ========================================

    public function testAdvancedFilterOnlyReturnsOwnedTasks(): void
    {
        $user1 = $this->createUser('filter-owner1@example.com');
        $user2 = $this->createUser('filter-owner2@example.com');

        $this->createTask($user1, 'User1 Task', Task::STATUS_PENDING);
        $this->createTask($user2, 'User2 Task', Task::STATUS_PENDING);

        $filterRequest = new TaskFilterRequest();
        $sortRequest = new TaskSortRequest();

        $qb = $this->taskRepository->createAdvancedFilteredQueryBuilder($user1, $filterRequest, $sortRequest);
        $results = $qb->getQuery()->getResult();

        $this->assertCount(1, $results);
        $this->assertEquals('User1 Task', $results[0]->getTitle());
    }
}
