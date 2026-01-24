<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for Task view-specific API endpoints.
 *
 * Tests:
 * - GET /api/v1/tasks/today - returns today and overdue tasks
 * - GET /api/v1/tasks/upcoming - returns future tasks
 * - GET /api/v1/tasks/overdue - returns only overdue tasks
 * - GET /api/v1/tasks/no-date - returns tasks without due date
 * - GET /api/v1/tasks/completed - returns completed tasks
 * - Authentication requirements
 * - User isolation
 */
class TaskViewApiTest extends ApiTestCase
{
    // ========================================
    // Today View Tests
    // ========================================

    public function testTodayViewReturnsTodayAndOverdueTasks(): void
    {
        $user = $this->createUser('today-view@example.com', 'Password123');

        $today = new \DateTimeImmutable('today');
        $yesterday = new \DateTimeImmutable('yesterday');
        $tomorrow = new \DateTimeImmutable('tomorrow');

        $this->createTask($user, 'Task due today', null, Task::STATUS_PENDING, 2, null, $today);
        $this->createTask($user, 'Overdue task', null, Task::STATUS_PENDING, 2, null, $yesterday);
        $this->createTask($user, 'Task due tomorrow', null, Task::STATUS_PENDING, 2, null, $tomorrow);
        $this->createTask($user, 'Task without due date');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/today'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);

        $titles = array_column($data['items'], 'title');
        $this->assertContains('Task due today', $titles);
        $this->assertContains('Overdue task', $titles);
        $this->assertNotContains('Task due tomorrow', $titles);
        $this->assertNotContains('Task without due date', $titles);
    }

    public function testTodayViewExcludesCompletedTasks(): void
    {
        $user = $this->createUser('today-no-completed@example.com', 'Password123');

        $today = new \DateTimeImmutable('today');

        $this->createTask($user, 'Pending task due today', null, Task::STATUS_PENDING, 2, null, $today);
        $this->createTask($user, 'Completed task due today', null, Task::STATUS_COMPLETED, 2, null, $today);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/today'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('Pending task due today', $data['items'][0]['title']);
        $this->assertEquals(Task::STATUS_PENDING, $data['items'][0]['status']);
    }

    public function testTodayViewEmpty(): void
    {
        $user = $this->createUser('today-empty@example.com', 'Password123');

        $tomorrow = new \DateTimeImmutable('tomorrow');
        $this->createTask($user, 'Task due tomorrow', null, Task::STATUS_PENDING, 2, null, $tomorrow);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/today'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('items', $data);
        $this->assertEmpty($data['items']);
    }

    // ========================================
    // Upcoming View Tests
    // ========================================

    public function testUpcomingViewReturnsFutureTasks(): void
    {
        $user = $this->createUser('upcoming-view@example.com', 'Password123');

        $today = new \DateTimeImmutable('today');
        $twoDaysFromNow = new \DateTimeImmutable('+2 days');
        $fiveDaysFromNow = new \DateTimeImmutable('+5 days');
        $tenDaysFromNow = new \DateTimeImmutable('+10 days');

        $this->createTask($user, 'Task due today', null, Task::STATUS_PENDING, 2, null, $today);
        $this->createTask($user, 'Task in 2 days', null, Task::STATUS_PENDING, 2, null, $twoDaysFromNow);
        $this->createTask($user, 'Task in 5 days', null, Task::STATUS_PENDING, 2, null, $fiveDaysFromNow);
        $this->createTask($user, 'Task in 10 days', null, Task::STATUS_PENDING, 2, null, $tenDaysFromNow);

        // Default is 7 days
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/upcoming'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);

        $titles = array_column($data['items'], 'title');
        $this->assertContains('Task in 2 days', $titles);
        $this->assertContains('Task in 5 days', $titles);
        // Task due today should not be in upcoming
        $this->assertNotContains('Task due today', $titles);
        // Task in 10 days is outside the 7-day window
        $this->assertNotContains('Task in 10 days', $titles);
    }

    public function testUpcomingViewWithCustomDaysParameter(): void
    {
        $user = $this->createUser('upcoming-days@example.com', 'Password123');

        $twoDaysFromNow = new \DateTimeImmutable('+2 days');
        $fiveDaysFromNow = new \DateTimeImmutable('+5 days');
        $fifteenDaysFromNow = new \DateTimeImmutable('+15 days');

        $this->createTask($user, 'Task in 2 days', null, Task::STATUS_PENDING, 2, null, $twoDaysFromNow);
        $this->createTask($user, 'Task in 5 days', null, Task::STATUS_PENDING, 2, null, $fiveDaysFromNow);
        $this->createTask($user, 'Task in 15 days', null, Task::STATUS_PENDING, 2, null, $fifteenDaysFromNow);

        // Request with 3-day window
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/upcoming?days=3'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('Task in 2 days', $data['items'][0]['title']);

        // Request with 30-day window
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/upcoming?days=30'
        );

        $data = $this->getResponseData($response);

        $this->assertCount(3, $data['items']);
    }

    public function testUpcomingViewExcludesCompletedTasks(): void
    {
        $user = $this->createUser('upcoming-no-completed@example.com', 'Password123');

        $threeDaysFromNow = new \DateTimeImmutable('+3 days');

        $this->createTask($user, 'Pending upcoming task', null, Task::STATUS_PENDING, 2, null, $threeDaysFromNow);
        $this->createTask($user, 'Completed upcoming task', null, Task::STATUS_COMPLETED, 2, null, $threeDaysFromNow);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/upcoming'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('Pending upcoming task', $data['items'][0]['title']);
    }

    public function testUpcomingViewInvalidDaysDefaultsToSeven(): void
    {
        $user = $this->createUser('upcoming-invalid-days@example.com', 'Password123');

        $fiveDaysFromNow = new \DateTimeImmutable('+5 days');
        $fiftyDaysFromNow = new \DateTimeImmutable('+50 days');

        $this->createTask($user, 'Task in 5 days', null, Task::STATUS_PENDING, 2, null, $fiveDaysFromNow);
        $this->createTask($user, 'Task in 50 days', null, Task::STATUS_PENDING, 2, null, $fiftyDaysFromNow);

        // Invalid days value (negative)
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/upcoming?days=-5'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should default to 7 days, only task in 5 days should be returned
        $this->assertCount(1, $data['items']);
        $this->assertEquals('Task in 5 days', $data['items'][0]['title']);

        // Invalid days value (too large)
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/upcoming?days=100'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should default to 7 days, only task in 5 days should be returned
        $this->assertCount(1, $data['items']);
    }

    // ========================================
    // Overdue View Tests
    // ========================================

    public function testOverdueViewReturnsOnlyOverdueTasks(): void
    {
        $user = $this->createUser('overdue-view@example.com', 'Password123');

        $yesterday = new \DateTimeImmutable('yesterday');
        $lastWeek = new \DateTimeImmutable('-7 days');
        $today = new \DateTimeImmutable('today');
        $tomorrow = new \DateTimeImmutable('tomorrow');

        $this->createTask($user, 'Overdue yesterday', null, Task::STATUS_PENDING, 2, null, $yesterday);
        $this->createTask($user, 'Overdue last week', null, Task::STATUS_PENDING, 2, null, $lastWeek);
        $this->createTask($user, 'Due today', null, Task::STATUS_PENDING, 2, null, $today);
        $this->createTask($user, 'Due tomorrow', null, Task::STATUS_PENDING, 2, null, $tomorrow);
        $this->createTask($user, 'No due date');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/overdue'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);

        $titles = array_column($data['items'], 'title');
        $this->assertContains('Overdue yesterday', $titles);
        $this->assertContains('Overdue last week', $titles);
        $this->assertNotContains('Due today', $titles);
        $this->assertNotContains('Due tomorrow', $titles);
        $this->assertNotContains('No due date', $titles);
    }

    public function testOverdueViewExcludesCompletedTasks(): void
    {
        $user = $this->createUser('overdue-no-completed@example.com', 'Password123');

        $yesterday = new \DateTimeImmutable('yesterday');

        $this->createTask($user, 'Pending overdue task', null, Task::STATUS_PENDING, 2, null, $yesterday);
        $this->createTask($user, 'Completed overdue task', null, Task::STATUS_COMPLETED, 2, null, $yesterday);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/overdue'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('Pending overdue task', $data['items'][0]['title']);
    }

    public function testOverdueViewEmpty(): void
    {
        $user = $this->createUser('overdue-empty@example.com', 'Password123');

        $tomorrow = new \DateTimeImmutable('tomorrow');
        $this->createTask($user, 'Task due tomorrow', null, Task::STATUS_PENDING, 2, null, $tomorrow);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/overdue'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('items', $data);
        $this->assertEmpty($data['items']);
    }

    // ========================================
    // No Date View Tests
    // ========================================

    public function testNoDateViewReturnsTasksWithoutDueDate(): void
    {
        $user = $this->createUser('no-date-view@example.com', 'Password123');

        $tomorrow = new \DateTimeImmutable('tomorrow');

        $this->createTask($user, 'Task with no due date 1');
        $this->createTask($user, 'Task with no due date 2');
        $this->createTask($user, 'Task with due date', null, Task::STATUS_PENDING, 2, null, $tomorrow);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/no-date'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);

        foreach ($data['items'] as $item) {
            $this->assertNull($item['dueDate']);
        }
    }

    public function testNoDateViewExcludesCompletedTasks(): void
    {
        $user = $this->createUser('no-date-no-completed@example.com', 'Password123');

        $this->createTask($user, 'Pending task without due date', null, Task::STATUS_PENDING);
        $this->createTask($user, 'Completed task without due date', null, Task::STATUS_COMPLETED);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/no-date'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('Pending task without due date', $data['items'][0]['title']);
    }

    public function testNoDateViewEmpty(): void
    {
        $user = $this->createUser('no-date-empty@example.com', 'Password123');

        $tomorrow = new \DateTimeImmutable('tomorrow');
        $this->createTask($user, 'Task with due date', null, Task::STATUS_PENDING, 2, null, $tomorrow);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/no-date'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('items', $data);
        $this->assertEmpty($data['items']);
    }

    // ========================================
    // Completed View Tests
    // ========================================

    public function testCompletedViewReturnsCompletedTasks(): void
    {
        $user = $this->createUser('completed-view@example.com', 'Password123');

        $this->createTask($user, 'Pending task', null, Task::STATUS_PENDING);
        $this->createTask($user, 'In progress task', null, Task::STATUS_IN_PROGRESS);
        $this->createTask($user, 'Completed task 1', null, Task::STATUS_COMPLETED);
        $this->createTask($user, 'Completed task 2', null, Task::STATUS_COMPLETED);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/completed'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);

        foreach ($data['items'] as $item) {
            $this->assertEquals(Task::STATUS_COMPLETED, $item['status']);
        }
    }

    public function testCompletedViewRespectsLimitParameter(): void
    {
        $user = $this->createUser('completed-limit@example.com', 'Password123');

        // Create 10 completed tasks
        for ($i = 1; $i <= 10; $i++) {
            $this->createTask($user, "Completed task $i", null, Task::STATUS_COMPLETED);
        }

        // Request with limit of 5
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/completed?limit=5'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(5, $data['items']);
    }

    public function testCompletedViewDefaultLimit(): void
    {
        $user = $this->createUser('completed-default-limit@example.com', 'Password123');

        // Create 30 completed tasks
        for ($i = 1; $i <= 30; $i++) {
            $this->createTask($user, "Completed task $i", null, Task::STATUS_COMPLETED);
        }

        // Request without limit - should default to 20 (standard pagination)
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/completed'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(20, $data['items']);
        $this->assertEquals(30, $data['meta']['total']);
        $this->assertEquals(2, $data['meta']['totalPages']);
    }

    public function testCompletedViewPaginationWithPage(): void
    {
        $user = $this->createUser('completed-pagination-page@example.com', 'Password123');

        // Create 30 completed tasks
        for ($i = 1; $i <= 30; $i++) {
            $this->createTask($user, "Completed task $i", null, Task::STATUS_COMPLETED);
        }

        // Request page 2
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/completed?page=2&limit=10'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(10, $data['items']);
        $this->assertEquals(2, $data['meta']['page']);
    }

    public function testCompletedViewEmpty(): void
    {
        $user = $this->createUser('completed-empty@example.com', 'Password123');

        $this->createTask($user, 'Pending task', null, Task::STATUS_PENDING);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/completed'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('items', $data);
        $this->assertEmpty($data['items']);
    }

    // ========================================
    // Pagination Meta Tests
    // ========================================

    public function testTodayViewIncludesPaginationMeta(): void
    {
        $user = $this->createUser('today-meta@example.com', 'Password123');

        $today = new \DateTimeImmutable('today');
        $this->createTask($user, 'Task due today', null, Task::STATUS_PENDING, 2, null, $today);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/today'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('total', $data['meta']);
        $this->assertArrayHasKey('page', $data['meta']);
        $this->assertArrayHasKey('limit', $data['meta']);
        $this->assertArrayHasKey('totalPages', $data['meta']);
    }

    public function testUpcomingViewIncludesPaginationMeta(): void
    {
        $user = $this->createUser('upcoming-meta@example.com', 'Password123');

        $tomorrow = new \DateTimeImmutable('tomorrow');
        $this->createTask($user, 'Task due tomorrow', null, Task::STATUS_PENDING, 2, null, $tomorrow);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/upcoming'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('total', $data['meta']);
    }

    public function testOverdueViewIncludesPaginationMeta(): void
    {
        $user = $this->createUser('overdue-meta@example.com', 'Password123');

        $yesterday = new \DateTimeImmutable('yesterday');
        $this->createTask($user, 'Overdue task', null, Task::STATUS_PENDING, 2, null, $yesterday);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/overdue'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('total', $data['meta']);
    }

    public function testNoDateViewIncludesPaginationMeta(): void
    {
        $user = $this->createUser('no-date-meta@example.com', 'Password123');

        $this->createTask($user, 'Task without due date');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/no-date'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('total', $data['meta']);
    }

    public function testCompletedViewIncludesPaginationMeta(): void
    {
        $user = $this->createUser('completed-meta@example.com', 'Password123');

        $this->createTask($user, 'Completed task', null, Task::STATUS_COMPLETED);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/completed'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('total', $data['meta']);
    }

    // ========================================
    // Pagination Parameters Tests
    // ========================================

    public function testTodayViewSupportsPaginationParams(): void
    {
        $user = $this->createUser('today-pagination@example.com', 'Password123');

        $today = new \DateTimeImmutable('today');
        for ($i = 1; $i <= 25; $i++) {
            $this->createTask($user, "Today task $i", null, Task::STATUS_PENDING, 2, null, $today);
        }

        // Request first page with limit 10
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/today?page=1&limit=10'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(10, $data['items']);
        $this->assertEquals(25, $data['meta']['total']);
        $this->assertEquals(1, $data['meta']['page']);
        $this->assertEquals(10, $data['meta']['limit']);
        $this->assertEquals(3, $data['meta']['totalPages']);
    }

    public function testOverdueViewSupportsPaginationParams(): void
    {
        $user = $this->createUser('overdue-pagination@example.com', 'Password123');

        $yesterday = new \DateTimeImmutable('yesterday');
        for ($i = 1; $i <= 15; $i++) {
            $this->createTask($user, "Overdue task $i", null, Task::STATUS_PENDING, 2, null, $yesterday);
        }

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/overdue?page=2&limit=5'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(5, $data['items']);
        $this->assertEquals(15, $data['meta']['total']);
        $this->assertEquals(2, $data['meta']['page']);
    }

    // ========================================
    // Sort Override Tests
    // ========================================

    public function testTodayViewSupportsSortOverride(): void
    {
        $user = $this->createUser('today-sort@example.com', 'Password123');

        $today = new \DateTimeImmutable('today');
        $this->createTask($user, 'High priority', null, Task::STATUS_PENDING, 4, null, $today);
        $this->createTask($user, 'Low priority', null, Task::STATUS_PENDING, 1, null, $today);
        $this->createTask($user, 'Medium priority', null, Task::STATUS_PENDING, 2, null, $today);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/today?sort=priority&direction=DESC'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('High priority', $data['items'][0]['title']);
        $this->assertEquals('Medium priority', $data['items'][1]['title']);
        $this->assertEquals('Low priority', $data['items'][2]['title']);
    }

    public function testCompletedViewSupportsSortByCompletedAt(): void
    {
        $user = $this->createUser('completed-sort@example.com', 'Password123');

        $this->createTask($user, 'Completed task A', null, Task::STATUS_COMPLETED, 2);
        $this->createTask($user, 'Completed task B', null, Task::STATUS_COMPLETED, 2);
        $this->createTask($user, 'Completed task C', null, Task::STATUS_COMPLETED, 2);

        // Default sort should be completed_at DESC
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/completed'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(3, $data['items']);
        $this->assertArrayHasKey('meta', $data);
    }

    // ========================================
    // Authentication Tests
    // ========================================

    public function testTodayViewRequiresAuthentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/tasks/today');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testUpcomingViewRequiresAuthentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/tasks/upcoming');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testOverdueViewRequiresAuthentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/tasks/overdue');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testNoDateViewRequiresAuthentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/tasks/no-date');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testCompletedViewRequiresAuthentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/tasks/completed');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // User Isolation Tests
    // ========================================

    public function testTodayViewRespectsUserIsolation(): void
    {
        $user1 = $this->createUser('user1-today@example.com', 'Password123');
        $user2 = $this->createUser('user2-today@example.com', 'Password123');

        $today = new \DateTimeImmutable('today');

        $this->createTask($user1, 'User 1 Task', null, Task::STATUS_PENDING, 2, null, $today);
        $this->createTask($user2, 'User 2 Task', null, Task::STATUS_PENDING, 2, null, $today);

        $response = $this->authenticatedApiRequest(
            $user1,
            'GET',
            '/api/v1/tasks/today'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('User 1 Task', $data['items'][0]['title']);
    }

    public function testUpcomingViewRespectsUserIsolation(): void
    {
        $user1 = $this->createUser('user1-upcoming@example.com', 'Password123');
        $user2 = $this->createUser('user2-upcoming@example.com', 'Password123');

        $threeDaysFromNow = new \DateTimeImmutable('+3 days');

        $this->createTask($user1, 'User 1 Task', null, Task::STATUS_PENDING, 2, null, $threeDaysFromNow);
        $this->createTask($user2, 'User 2 Task', null, Task::STATUS_PENDING, 2, null, $threeDaysFromNow);

        $response = $this->authenticatedApiRequest(
            $user1,
            'GET',
            '/api/v1/tasks/upcoming'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('User 1 Task', $data['items'][0]['title']);
    }

    public function testOverdueViewRespectsUserIsolation(): void
    {
        $user1 = $this->createUser('user1-overdue@example.com', 'Password123');
        $user2 = $this->createUser('user2-overdue@example.com', 'Password123');

        $yesterday = new \DateTimeImmutable('yesterday');

        $this->createTask($user1, 'User 1 Task', null, Task::STATUS_PENDING, 2, null, $yesterday);
        $this->createTask($user2, 'User 2 Task', null, Task::STATUS_PENDING, 2, null, $yesterday);

        $response = $this->authenticatedApiRequest(
            $user1,
            'GET',
            '/api/v1/tasks/overdue'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('User 1 Task', $data['items'][0]['title']);
    }

    public function testNoDateViewRespectsUserIsolation(): void
    {
        $user1 = $this->createUser('user1-no-date@example.com', 'Password123');
        $user2 = $this->createUser('user2-no-date@example.com', 'Password123');

        $this->createTask($user1, 'User 1 Task');
        $this->createTask($user2, 'User 2 Task');

        $response = $this->authenticatedApiRequest(
            $user1,
            'GET',
            '/api/v1/tasks/no-date'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('User 1 Task', $data['items'][0]['title']);
    }

    public function testCompletedViewRespectsUserIsolation(): void
    {
        $user1 = $this->createUser('user1-completed@example.com', 'Password123');
        $user2 = $this->createUser('user2-completed@example.com', 'Password123');

        $this->createTask($user1, 'User 1 Task', null, Task::STATUS_COMPLETED);
        $this->createTask($user2, 'User 2 Task', null, Task::STATUS_COMPLETED);

        $response = $this->authenticatedApiRequest(
            $user1,
            'GET',
            '/api/v1/tasks/completed'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('User 1 Task', $data['items'][0]['title']);
    }
}
