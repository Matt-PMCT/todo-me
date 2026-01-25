<?php

declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;

/**
 * Functional tests for TaskViewController.
 *
 * Tests the smart view routes: today, upcoming, overdue, no-date, completed.
 */
class TaskViewControllerTest extends ApiTestCase
{
    // ========================================
    // Today View Tests
    // ========================================

    public function testTodayViewRequiresAuthentication(): void
    {
        $this->client->request('GET', '/today');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testTodayViewRendersSuccessfully(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/today');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Today');
    }

    public function testTodayViewShowsOverdueTasks(): void
    {
        $user = $this->createUser();
        $this->createTask(
            $user,
            'Overdue Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('-2 days')
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/today');

        $this->assertResponseIsSuccessful();
        // Use more specific selector for the overdue section h2 header
        $this->assertSelectorTextContains('h2.text-red-600', 'Overdue');
        $this->assertSelectorTextContains('body', 'Overdue Task');
    }

    public function testTodayViewShowsTasksDueToday(): void
    {
        $user = $this->createUser();
        $this->createTask(
            $user,
            'Today Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('today')
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/today');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Today Task');
    }

    public function testTodayViewExcludesCompletedTasks(): void
    {
        $user = $this->createUser();
        $this->createTask(
            $user,
            'Completed Today Task',
            null,
            Task::STATUS_COMPLETED,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('today')
        );
        $this->createTask(
            $user,
            'Pending Today Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('today')
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/today');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Pending Today Task');
        $this->assertSelectorTextNotContains('body', 'Completed Today Task');
    }

    public function testTodayViewExcludesFutureTasks(): void
    {
        $user = $this->createUser();
        $this->createTask(
            $user,
            'Future Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('+3 days')
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/today');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextNotContains('body', 'Future Task');
    }

    public function testTodayViewShowsEmptyStateWhenNoTasks(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/today');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'All caught up');
    }

    // ========================================
    // Upcoming View Tests
    // ========================================

    public function testUpcomingViewRequiresAuthentication(): void
    {
        $this->client->request('GET', '/upcoming');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testUpcomingViewRendersSuccessfully(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/upcoming');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Upcoming');
    }

    public function testUpcomingViewGroupsByTimePeriod(): void
    {
        $user = $this->createUser();
        // Create task due tomorrow
        $this->createTask(
            $user,
            'Tomorrow Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('+1 day')
        );
        // Create task due in a week
        $this->createTask(
            $user,
            'Next Week Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('+8 days')
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/upcoming');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Tomorrow Task');
        $this->assertSelectorTextContains('body', 'Next Week Task');
    }

    public function testUpcomingViewExcludesTodayTasks(): void
    {
        $user = $this->createUser();
        $this->createTask(
            $user,
            'Today Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('today')
        );
        $this->createTask(
            $user,
            'Tomorrow Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('+1 day')
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/upcoming');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Tomorrow Task');
        $this->assertSelectorTextNotContains('body', 'Today Task');
    }

    public function testUpcomingViewExcludesCompletedTasks(): void
    {
        $user = $this->createUser();
        $this->createTask(
            $user,
            'Completed Upcoming',
            null,
            Task::STATUS_COMPLETED,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('+3 days')
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/upcoming');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextNotContains('body', 'Completed Upcoming');
    }

    public function testUpcomingViewShowsEmptyStateWhenNoTasks(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/upcoming');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'No upcoming tasks');
    }

    // ========================================
    // Overdue View Tests
    // ========================================

    public function testOverdueViewRequiresAuthentication(): void
    {
        $this->client->request('GET', '/overdue');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testOverdueViewRendersSuccessfully(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/overdue');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Overdue');
    }

    public function testOverdueViewGroupsBySeverity(): void
    {
        $user = $this->createUser();
        // Create task overdue by 1 day (low severity)
        $this->createTask(
            $user,
            'Recently Overdue',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('-1 day')
        );
        // Create task overdue by 10 days (high severity)
        $this->createTask(
            $user,
            'Very Overdue',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('-10 days')
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/overdue');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Recently Overdue');
        $this->assertSelectorTextContains('body', 'Very Overdue');
    }

    public function testOverdueViewExcludesCompletedTasks(): void
    {
        $user = $this->createUser();
        $this->createTask(
            $user,
            'Completed Overdue',
            null,
            Task::STATUS_COMPLETED,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('-5 days')
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/overdue');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextNotContains('body', 'Completed Overdue');
    }

    public function testOverdueViewExcludesFutureTasks(): void
    {
        $user = $this->createUser();
        $this->createTask(
            $user,
            'Future Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('+3 days')
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/overdue');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextNotContains('body', 'Future Task');
    }

    public function testOverdueViewShowsEmptyStateWhenNoTasks(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/overdue');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'No overdue tasks');
    }

    // ========================================
    // No Date View Tests
    // ========================================

    public function testNoDateViewRequiresAuthentication(): void
    {
        $this->client->request('GET', '/no-date');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testNoDateViewRendersSuccessfully(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/no-date');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'No Due Date');
    }

    public function testNoDateViewShowsTasksWithoutDueDate(): void
    {
        $user = $this->createUser();
        $this->createTask(
            $user,
            'No Date Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            null // No due date
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/no-date');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'No Date Task');
    }

    public function testNoDateViewExcludesTasksWithDueDate(): void
    {
        $user = $this->createUser();
        $this->createTask(
            $user,
            'Task With Date',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('+1 week')
        );
        $this->createTask(
            $user,
            'Task Without Date',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            null
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/no-date');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Task Without Date');
        $this->assertSelectorTextNotContains('body', 'Task With Date');
    }

    public function testNoDateViewExcludesCompletedTasks(): void
    {
        $user = $this->createUser();
        $this->createTask(
            $user,
            'Completed No Date',
            null,
            Task::STATUS_COMPLETED,
            Task::PRIORITY_DEFAULT,
            null,
            null
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/no-date');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextNotContains('body', 'Completed No Date');
    }

    public function testNoDateViewShowsEmptyStateWhenNoTasks(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/no-date');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'All tasks scheduled');
    }

    // ========================================
    // Completed View Tests
    // ========================================

    public function testCompletedViewRequiresAuthentication(): void
    {
        $this->client->request('GET', '/completed');

        $this->assertTrue($this->client->getResponse()->isRedirect());
        $this->assertStringContainsString('/login', $this->client->getResponse()->headers->get('Location') ?? '');
    }

    public function testCompletedViewRendersSuccessfully(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/completed');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Completed');
    }

    public function testCompletedViewShowsCompletedTasks(): void
    {
        $user = $this->createUser();
        $task = $this->createTask(
            $user,
            'Completed Task',
            null,
            Task::STATUS_COMPLETED,
            Task::PRIORITY_DEFAULT,
            null,
            null
        );
        $task->setCompletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/completed');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Completed Task');
    }

    public function testCompletedViewExcludesPendingTasks(): void
    {
        $user = $this->createUser();
        $this->createTask(
            $user,
            'Pending Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            null
        );
        $this->client->loginUser($user);

        $this->client->request('GET', '/completed');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextNotContains('body', 'Pending Task');
    }

    public function testCompletedViewShowsEmptyStateWhenNoTasks(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/completed');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'No completed tasks');
    }

    // ========================================
    // Multi-tenant Isolation Tests
    // ========================================

    public function testTodayViewDoesNotShowOtherUsersTasks(): void
    {
        $user1 = $this->createUser('user1-today@example.com');
        $user2 = $this->createUser('user2-today@example.com');

        $this->createTask(
            $user1,
            'User1 Today Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('today')
        );
        $this->createTask(
            $user2,
            'User2 Today Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('today')
        );

        $this->client->loginUser($user1);
        $this->client->request('GET', '/today');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'User1 Today Task');
        $this->assertSelectorTextNotContains('body', 'User2 Today Task');
    }

    public function testOverdueViewDoesNotShowOtherUsersTasks(): void
    {
        $user1 = $this->createUser('user1-overdue@example.com');
        $user2 = $this->createUser('user2-overdue@example.com');

        $this->createTask(
            $user1,
            'User1 Overdue Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('-2 days')
        );
        $this->createTask(
            $user2,
            'User2 Overdue Task',
            null,
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            new \DateTimeImmutable('-2 days')
        );

        $this->client->loginUser($user1);
        $this->client->request('GET', '/overdue');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'User1 Overdue Task');
        $this->assertSelectorTextNotContains('body', 'User2 Overdue Task');
    }

    public function testCompletedViewDoesNotShowOtherUsersTasks(): void
    {
        $user1 = $this->createUser('user1-completed@example.com');
        $user2 = $this->createUser('user2-completed@example.com');

        $task1 = $this->createTask(
            $user1,
            'User1 Completed Task',
            null,
            Task::STATUS_COMPLETED,
            Task::PRIORITY_DEFAULT,
            null,
            null
        );
        $task1->setCompletedAt(new \DateTimeImmutable());

        $task2 = $this->createTask(
            $user2,
            'User2 Completed Task',
            null,
            Task::STATUS_COMPLETED,
            Task::PRIORITY_DEFAULT,
            null,
            null
        );
        $task2->setCompletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $this->client->loginUser($user1);
        $this->client->request('GET', '/completed');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'User1 Completed Task');
        $this->assertSelectorTextNotContains('body', 'User2 Completed Task');
    }
}
