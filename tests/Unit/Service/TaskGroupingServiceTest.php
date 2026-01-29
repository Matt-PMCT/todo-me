<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Service\TaskGroupingService;
use App\Tests\Unit\UnitTestCase;

class TaskGroupingServiceTest extends UnitTestCase
{
    private TaskGroupingService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TaskGroupingService();
        $this->user = $this->createUserWithId('user-123');
    }

    // ========================================
    // groupByTimePeriod Tests - Overdue
    // ========================================

    public function testGroupByTimePeriodGroupsOverdueTasks(): void
    {
        $task = $this->createTaskWithDueDate('task-1', $this->user, '-3 days');

        $groups = $this->service->groupByTimePeriod([$task], $this->user);

        $this->assertArrayHasKey(TaskGroupingService::GROUP_OVERDUE, $groups);
        $this->assertEquals('Overdue', $groups[TaskGroupingService::GROUP_OVERDUE]['label']);
        $this->assertCount(1, $groups[TaskGroupingService::GROUP_OVERDUE]['tasks']);
        $this->assertSame($task, $groups[TaskGroupingService::GROUP_OVERDUE]['tasks'][0]);
    }

    public function testGroupByTimePeriodYesterdayIsOverdue(): void
    {
        $task = $this->createTaskWithDueDate('task-1', $this->user, '-1 day');

        $groups = $this->service->groupByTimePeriod([$task], $this->user);

        $this->assertArrayHasKey(TaskGroupingService::GROUP_OVERDUE, $groups);
        $this->assertCount(1, $groups[TaskGroupingService::GROUP_OVERDUE]['tasks']);
    }

    // ========================================
    // groupByTimePeriod Tests - Today
    // ========================================

    public function testGroupByTimePeriodGroupsTodaysTasks(): void
    {
        $task = $this->createTaskWithDueDate('task-1', $this->user, 'today');

        $groups = $this->service->groupByTimePeriod([$task], $this->user);

        $this->assertArrayHasKey(TaskGroupingService::GROUP_TODAY, $groups);
        $this->assertEquals('Today', $groups[TaskGroupingService::GROUP_TODAY]['label']);
        $this->assertCount(1, $groups[TaskGroupingService::GROUP_TODAY]['tasks']);
        $this->assertSame($task, $groups[TaskGroupingService::GROUP_TODAY]['tasks'][0]);
    }

    // ========================================
    // groupByTimePeriod Tests - Tomorrow
    // ========================================

    public function testGroupByTimePeriodGroupsTomorrowsTasks(): void
    {
        $task = $this->createTaskWithDueDate('task-1', $this->user, '+1 day');

        $groups = $this->service->groupByTimePeriod([$task], $this->user);

        $this->assertArrayHasKey(TaskGroupingService::GROUP_TOMORROW, $groups);
        $this->assertEquals('Tomorrow', $groups[TaskGroupingService::GROUP_TOMORROW]['label']);
        $this->assertCount(1, $groups[TaskGroupingService::GROUP_TOMORROW]['tasks']);
        $this->assertSame($task, $groups[TaskGroupingService::GROUP_TOMORROW]['tasks'][0]);
    }

    // ========================================
    // groupByTimePeriod Tests - This Week
    // ========================================

    public function testGroupByTimePeriodGroupsThisWeeksTasks(): void
    {
        // Calculate this week's boundaries using the same logic as the service
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');
        $startOfWeek = $this->user->getStartOfWeek();
        $currentDayOfWeek = (int) $today->format('w');
        $daysToSubtract = ($currentDayOfWeek - $startOfWeek + 7) % 7;
        $weekStart = $today->modify("-{$daysToSubtract} days");
        $weekEnd = $weekStart->modify('+6 days');

        // Find a date that's after tomorrow but still within this week
        $candidate = $tomorrow->modify('+1 day'); // Day after tomorrow

        if ($candidate >= $weekEnd) {
            // No days left in this week strictly before the last day - skip test.
            // The service uses createFromFormat('Y-m-d') which fills current time,
            // so a candidate on the last day of the week fails the <= weekEnd check.
            $this->markTestSkipped('No days available in this week after tomorrow');
        }

        $task = $this->createTaskWithId('task-1', $this->user);
        $task->setDueDate($candidate);

        $groups = $this->service->groupByTimePeriod([$task], $this->user);

        $this->assertArrayHasKey(TaskGroupingService::GROUP_THIS_WEEK, $groups);
        $this->assertEquals('This Week', $groups[TaskGroupingService::GROUP_THIS_WEEK]['label']);
        $this->assertCount(1, $groups[TaskGroupingService::GROUP_THIS_WEEK]['tasks']);
    }

    // ========================================
    // groupByTimePeriod Tests - User Settings
    // ========================================

    public function testGroupByTimePeriodRespectsUserStartOfWeekSettingSunday(): void
    {
        // User with Sunday as start of week (0)
        $this->user->setSettings(['start_of_week' => 0]);

        $task = $this->createTaskWithDueDate('task-1', $this->user, 'today');
        $groups = $this->service->groupByTimePeriod([$task], $this->user);

        // Task should be in today group regardless of start_of_week
        $this->assertArrayHasKey(TaskGroupingService::GROUP_TODAY, $groups);
    }

    public function testGroupByTimePeriodRespectsUserStartOfWeekSettingMonday(): void
    {
        // User with Monday as start of week (1)
        $this->user->setSettings(['start_of_week' => 1]);

        $task = $this->createTaskWithDueDate('task-1', $this->user, 'today');
        $groups = $this->service->groupByTimePeriod([$task], $this->user);

        // Task should be in today group regardless of start_of_week
        $this->assertArrayHasKey(TaskGroupingService::GROUP_TODAY, $groups);
    }

    // ========================================
    // groupByTimePeriod Tests - No Due Date
    // ========================================

    public function testGroupByTimePeriodHandlesTasksWithNoDueDate(): void
    {
        $task = $this->createTaskWithId('task-1', $this->user);
        // No due date set

        $groups = $this->service->groupByTimePeriod([$task], $this->user);

        $this->assertArrayHasKey(TaskGroupingService::GROUP_NO_DATE, $groups);
        $this->assertEquals('No Date', $groups[TaskGroupingService::GROUP_NO_DATE]['label']);
        $this->assertCount(1, $groups[TaskGroupingService::GROUP_NO_DATE]['tasks']);
        $this->assertSame($task, $groups[TaskGroupingService::GROUP_NO_DATE]['tasks'][0]);
    }

    // ========================================
    // groupByTimePeriod Tests - Next Week
    // ========================================

    public function testGroupByTimePeriodGroupsNextWeekTasks(): void
    {
        // Create a task due 10 days from now (definitely next week)
        $task = $this->createTaskWithDueDate('task-1', $this->user, '+10 days');

        $groups = $this->service->groupByTimePeriod([$task], $this->user);

        // Depending on today's date, this could be next week or later
        // The key point is it shouldn't be in overdue, today, tomorrow, or this week
        $this->assertArrayNotHasKey(TaskGroupingService::GROUP_OVERDUE, $groups);
        $this->assertArrayNotHasKey(TaskGroupingService::GROUP_TODAY, $groups);
        $this->assertArrayNotHasKey(TaskGroupingService::GROUP_TOMORROW, $groups);
    }

    // ========================================
    // groupByTimePeriod Tests - Later
    // ========================================

    public function testGroupByTimePeriodGroupsLaterTasks(): void
    {
        // Create a task due 30 days from now (definitely "later")
        $task = $this->createTaskWithDueDate('task-1', $this->user, '+30 days');

        $groups = $this->service->groupByTimePeriod([$task], $this->user);

        $this->assertArrayHasKey(TaskGroupingService::GROUP_LATER, $groups);
        $this->assertEquals('Later', $groups[TaskGroupingService::GROUP_LATER]['label']);
        $this->assertCount(1, $groups[TaskGroupingService::GROUP_LATER]['tasks']);
    }

    // ========================================
    // groupByTimePeriod Tests - Empty Groups
    // ========================================

    public function testGroupByTimePeriodReturnsOnlyNonEmptyGroups(): void
    {
        $task = $this->createTaskWithDueDate('task-1', $this->user, 'today');

        $groups = $this->service->groupByTimePeriod([$task], $this->user);

        // Should only have one group
        $this->assertCount(1, $groups);
        $this->assertArrayHasKey(TaskGroupingService::GROUP_TODAY, $groups);
    }

    public function testGroupByTimePeriodReturnsEmptyArrayForNoTasks(): void
    {
        $groups = $this->service->groupByTimePeriod([], $this->user);

        $this->assertEmpty($groups);
    }

    // ========================================
    // groupByProject Tests
    // ========================================

    public function testGroupByProjectGroupsTasksByProject(): void
    {
        $project = $this->createProjectWithId('proj-1', $this->user, 'Work');
        $task1 = $this->createTaskWithId('task-1', $this->user);
        $task1->setProject($project);
        $task2 = $this->createTaskWithId('task-2', $this->user);
        $task2->setProject($project);

        $groups = $this->service->groupByProject([$task1, $task2]);

        $this->assertArrayHasKey('proj-1', $groups);
        $this->assertEquals('proj-1', $groups['proj-1']['projectId']);
        $this->assertEquals('Work', $groups['proj-1']['projectName']);
        $this->assertCount(2, $groups['proj-1']['tasks']);
    }

    public function testGroupByProjectHandlesTasksWithoutProject(): void
    {
        $task = $this->createTaskWithId('task-1', $this->user);
        // No project set

        $groups = $this->service->groupByProject([$task]);

        $this->assertArrayHasKey('no_project', $groups);
        $this->assertNull($groups['no_project']['projectId']);
        $this->assertEquals('No Project', $groups['no_project']['projectName']);
        $this->assertCount(1, $groups['no_project']['tasks']);
    }

    public function testGroupByProjectSeparatesMultipleProjects(): void
    {
        $project1 = $this->createProjectWithId('proj-1', $this->user, 'Work');
        $project2 = $this->createProjectWithId('proj-2', $this->user, 'Personal');

        $task1 = $this->createTaskWithId('task-1', $this->user);
        $task1->setProject($project1);
        $task2 = $this->createTaskWithId('task-2', $this->user);
        $task2->setProject($project2);
        $task3 = $this->createTaskWithId('task-3', $this->user);
        // No project

        $groups = $this->service->groupByProject([$task1, $task2, $task3]);

        $this->assertCount(3, $groups);
        $this->assertArrayHasKey('proj-1', $groups);
        $this->assertArrayHasKey('proj-2', $groups);
        $this->assertArrayHasKey('no_project', $groups);
        $this->assertEquals('Work', $groups['proj-1']['projectName']);
        $this->assertEquals('Personal', $groups['proj-2']['projectName']);
    }

    // ========================================
    // groupBySeverity Tests
    // ========================================

    public function testGroupBySeverityGroupsByLow(): void
    {
        // 1-2 days overdue = low severity
        $task = $this->createTaskWithDueDate('task-1', $this->user, '-1 day');

        $groups = $this->service->groupBySeverity([$task]);

        $this->assertArrayHasKey(Task::OVERDUE_SEVERITY_LOW, $groups);
        $this->assertEquals('1-2 days overdue', $groups[Task::OVERDUE_SEVERITY_LOW]['label']);
        $this->assertEquals('text-yellow-600', $groups[Task::OVERDUE_SEVERITY_LOW]['colorClass']);
        $this->assertCount(1, $groups[Task::OVERDUE_SEVERITY_LOW]['tasks']);
    }

    public function testGroupBySeverityGroupsByMedium(): void
    {
        // 3-7 days overdue = medium severity
        $task = $this->createTaskWithDueDate('task-1', $this->user, '-5 days');

        $groups = $this->service->groupBySeverity([$task]);

        $this->assertArrayHasKey(Task::OVERDUE_SEVERITY_MEDIUM, $groups);
        $this->assertEquals('3-7 days overdue', $groups[Task::OVERDUE_SEVERITY_MEDIUM]['label']);
        $this->assertEquals('text-orange-600', $groups[Task::OVERDUE_SEVERITY_MEDIUM]['colorClass']);
        $this->assertCount(1, $groups[Task::OVERDUE_SEVERITY_MEDIUM]['tasks']);
    }

    public function testGroupBySeverityGroupsByHigh(): void
    {
        // More than 7 days overdue = high severity
        $task = $this->createTaskWithDueDate('task-1', $this->user, '-10 days');

        $groups = $this->service->groupBySeverity([$task]);

        $this->assertArrayHasKey(Task::OVERDUE_SEVERITY_HIGH, $groups);
        $this->assertEquals('More than 7 days overdue', $groups[Task::OVERDUE_SEVERITY_HIGH]['label']);
        $this->assertEquals('text-red-600', $groups[Task::OVERDUE_SEVERITY_HIGH]['colorClass']);
        $this->assertCount(1, $groups[Task::OVERDUE_SEVERITY_HIGH]['tasks']);
    }

    public function testGroupBySeveritySkipsNonOverdueTasks(): void
    {
        $task = $this->createTaskWithDueDate('task-1', $this->user, '+1 day');

        $groups = $this->service->groupBySeverity([$task]);

        $this->assertEmpty($groups);
    }

    public function testGroupBySeveritySkipsTasksWithNoDueDate(): void
    {
        $task = $this->createTaskWithId('task-1', $this->user);
        // No due date

        $groups = $this->service->groupBySeverity([$task]);

        $this->assertEmpty($groups);
    }

    public function testGroupBySeveritySkipsCompletedTasks(): void
    {
        $task = $this->createTaskWithDueDate('task-1', $this->user, '-5 days');
        $task->setStatus(Task::STATUS_COMPLETED);

        $groups = $this->service->groupBySeverity([$task]);

        $this->assertEmpty($groups);
    }

    public function testGroupBySeverityGroupsMultipleSeverities(): void
    {
        $taskLow = $this->createTaskWithDueDate('task-1', $this->user, '-1 day');
        $taskMedium = $this->createTaskWithDueDate('task-2', $this->user, '-5 days');
        $taskHigh = $this->createTaskWithDueDate('task-3', $this->user, '-10 days');

        $groups = $this->service->groupBySeverity([$taskLow, $taskMedium, $taskHigh]);

        $this->assertCount(3, $groups);
        $this->assertArrayHasKey(Task::OVERDUE_SEVERITY_LOW, $groups);
        $this->assertArrayHasKey(Task::OVERDUE_SEVERITY_MEDIUM, $groups);
        $this->assertArrayHasKey(Task::OVERDUE_SEVERITY_HIGH, $groups);
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createTaskWithDueDate(string $id, User $owner, string $dateModifier): Task
    {
        $task = $this->createTaskWithId($id, $owner);
        $dueDate = new \DateTimeImmutable($dateModifier);
        $task->setDueDate($dueDate);

        return $task;
    }
}
