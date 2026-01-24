<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for advanced Task filtering API endpoints.
 *
 * Tests:
 * - Filter by multiple statuses
 * - Filter by priority range
 * - Filter by project_ids
 * - Filter with include_child_projects
 * - Filter by tag_ids with tag_mode=OR
 * - Filter by tag_ids with tag_mode=AND
 * - Filter by due_before
 * - Filter by due_after
 * - Filter by has_no_due_date
 * - Filter by search term
 * - Filter with include_completed=false
 * - Combined filters
 * - Validation limits (max 50 projects, max 50 tags)
 */
class TaskFilterApiTest extends ApiTestCase
{
    // ========================================
    // Filter by Multiple Statuses Tests
    // ========================================

    public function testFilterByMultipleStatuses(): void
    {
        $user = $this->createUser('filter-statuses@example.com', 'Password123');

        $this->createTask($user, 'Pending Task', null, Task::STATUS_PENDING);
        $this->createTask($user, 'In Progress Task', null, Task::STATUS_IN_PROGRESS);
        $this->createTask($user, 'Completed Task', null, Task::STATUS_COMPLETED);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?statuses=pending,in_progress'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['items']);
        $titles = array_column($data['items'], 'title');
        $this->assertContains('Pending Task', $titles);
        $this->assertContains('In Progress Task', $titles);
        $this->assertNotContains('Completed Task', $titles);
    }

    public function testFilterByMultipleStatusesAsArray(): void
    {
        $user = $this->createUser('filter-statuses-array@example.com', 'Password123');

        $this->createTask($user, 'Pending Task', null, Task::STATUS_PENDING);
        $this->createTask($user, 'In Progress Task', null, Task::STATUS_IN_PROGRESS);
        $this->createTask($user, 'Completed Task', null, Task::STATUS_COMPLETED);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?statuses[]=pending&statuses[]=completed'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['items']);
        $titles = array_column($data['items'], 'title');
        $this->assertContains('Pending Task', $titles);
        $this->assertContains('Completed Task', $titles);
    }

    // ========================================
    // Filter by Priority Range Tests
    // ========================================

    public function testFilterByPriorityRange(): void
    {
        $user = $this->createUser('filter-priority-range@example.com', 'Password123');

        $this->createTask($user, 'Priority 0 Task', null, Task::STATUS_PENDING, 0);
        $this->createTask($user, 'Priority 1 Task', null, Task::STATUS_PENDING, 1);
        $this->createTask($user, 'Priority 2 Task', null, Task::STATUS_PENDING, 2);
        $this->createTask($user, 'Priority 3 Task', null, Task::STATUS_PENDING, 3);
        $this->createTask($user, 'Priority 4 Task', null, Task::STATUS_PENDING, 4);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?priority_min=2&priority_max=4'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(3, $data['items']);
        $priorities = array_column($data['items'], 'priority');
        foreach ($priorities as $priority) {
            $this->assertGreaterThanOrEqual(2, $priority);
            $this->assertLessThanOrEqual(4, $priority);
        }
    }

    public function testFilterByPriorityMinOnly(): void
    {
        $user = $this->createUser('filter-priority-min@example.com', 'Password123');

        $this->createTask($user, 'Priority 1 Task', null, Task::STATUS_PENDING, 1);
        $this->createTask($user, 'Priority 3 Task', null, Task::STATUS_PENDING, 3);
        $this->createTask($user, 'Priority 4 Task', null, Task::STATUS_PENDING, 4);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?priority_min=3'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['items']);
        $priorities = array_column($data['items'], 'priority');
        foreach ($priorities as $priority) {
            $this->assertGreaterThanOrEqual(3, $priority);
        }
    }

    public function testFilterByPriorityMaxOnly(): void
    {
        $user = $this->createUser('filter-priority-max@example.com', 'Password123');

        $this->createTask($user, 'Priority 1 Task', null, Task::STATUS_PENDING, 1);
        $this->createTask($user, 'Priority 3 Task', null, Task::STATUS_PENDING, 3);
        $this->createTask($user, 'Priority 4 Task', null, Task::STATUS_PENDING, 4);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?priority_max=2'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals(1, $data['items'][0]['priority']);
    }

    // ========================================
    // Filter by Project IDs Tests
    // ========================================

    public function testFilterByProjectIds(): void
    {
        $user = $this->createUser('filter-project-ids@example.com', 'Password123');

        $project1 = $this->createProject($user, 'Project 1');
        $project2 = $this->createProject($user, 'Project 2');
        $project3 = $this->createProject($user, 'Project 3');

        $this->createTask($user, 'Task in Project 1', null, Task::STATUS_PENDING, 2, $project1);
        $this->createTask($user, 'Task in Project 2', null, Task::STATUS_PENDING, 2, $project2);
        $this->createTask($user, 'Task in Project 3', null, Task::STATUS_PENDING, 2, $project3);
        $this->createTask($user, 'Task without Project');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?project_ids=' . $project1->getId() . ',' . $project2->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['items']);
        $titles = array_column($data['items'], 'title');
        $this->assertContains('Task in Project 1', $titles);
        $this->assertContains('Task in Project 2', $titles);
    }

    public function testFilterWithIncludeChildProjects(): void
    {
        $user = $this->createUser('filter-child-projects@example.com', 'Password123');

        $parentProject = $this->createProject($user, 'Parent Project');
        $childProject = $this->createProject($user, 'Child Project', null, false, $parentProject);

        $this->createTask($user, 'Task in Parent', null, Task::STATUS_PENDING, 2, $parentProject);
        $this->createTask($user, 'Task in Child', null, Task::STATUS_PENDING, 2, $childProject);
        $this->createTask($user, 'Task without Project');

        // Without include_child_projects - should only get parent task
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?project_ids=' . $parentProject->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('Task in Parent', $data['items'][0]['title']);

        // With include_child_projects=true - should get both
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?project_ids=' . $parentProject->getId() . '&include_child_projects=true'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['items']);
        $titles = array_column($data['items'], 'title');
        $this->assertContains('Task in Parent', $titles);
        $this->assertContains('Task in Child', $titles);
    }

    // ========================================
    // Filter by Tag IDs Tests
    // ========================================

    public function testFilterByTagIdsWithOrMode(): void
    {
        $user = $this->createUser('filter-tags-or@example.com', 'Password123');

        $tag1 = $this->createTag($user, 'Tag 1');
        $tag2 = $this->createTag($user, 'Tag 2');
        $tag3 = $this->createTag($user, 'Tag 3');

        $task1 = $this->createTask($user, 'Task with Tag 1');
        $task1->addTag($tag1);

        $task2 = $this->createTask($user, 'Task with Tag 2');
        $task2->addTag($tag2);

        $task3 = $this->createTask($user, 'Task with Tag 3');
        $task3->addTag($tag3);

        $task4 = $this->createTask($user, 'Task with Tags 1 and 2');
        $task4->addTag($tag1);
        $task4->addTag($tag2);

        $this->entityManager->flush();

        // Filter by tag1 OR tag2
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?tag_ids=' . $tag1->getId() . ',' . $tag2->getId() . '&tag_mode=OR'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(3, $data['items']);
        $titles = array_column($data['items'], 'title');
        $this->assertContains('Task with Tag 1', $titles);
        $this->assertContains('Task with Tag 2', $titles);
        $this->assertContains('Task with Tags 1 and 2', $titles);
        $this->assertNotContains('Task with Tag 3', $titles);
    }

    public function testFilterByTagIdsWithAndMode(): void
    {
        $user = $this->createUser('filter-tags-and@example.com', 'Password123');

        $tag1 = $this->createTag($user, 'Tag 1');
        $tag2 = $this->createTag($user, 'Tag 2');

        $task1 = $this->createTask($user, 'Task with Tag 1 only');
        $task1->addTag($tag1);

        $task2 = $this->createTask($user, 'Task with Tag 2 only');
        $task2->addTag($tag2);

        $task3 = $this->createTask($user, 'Task with Both Tags');
        $task3->addTag($tag1);
        $task3->addTag($tag2);

        $this->entityManager->flush();

        // Filter by tag1 AND tag2 - should only return task with both
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?tag_ids=' . $tag1->getId() . ',' . $tag2->getId() . '&tag_mode=AND'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('Task with Both Tags', $data['items'][0]['title']);
    }

    // ========================================
    // Filter by Due Date Tests
    // ========================================

    public function testFilterByDueBefore(): void
    {
        $user = $this->createUser('filter-due-before@example.com', 'Password123');

        $this->createTask($user, 'Task due Jan 1', null, Task::STATUS_PENDING, 2, null, new \DateTimeImmutable('2024-01-01'));
        $this->createTask($user, 'Task due Jan 15', null, Task::STATUS_PENDING, 2, null, new \DateTimeImmutable('2024-01-15'));
        $this->createTask($user, 'Task due Feb 1', null, Task::STATUS_PENDING, 2, null, new \DateTimeImmutable('2024-02-01'));

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?due_before=2024-01-20'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['items']);
        $titles = array_column($data['items'], 'title');
        $this->assertContains('Task due Jan 1', $titles);
        $this->assertContains('Task due Jan 15', $titles);
    }

    public function testFilterByDueAfter(): void
    {
        $user = $this->createUser('filter-due-after@example.com', 'Password123');

        $this->createTask($user, 'Task due Jan 1', null, Task::STATUS_PENDING, 2, null, new \DateTimeImmutable('2024-01-01'));
        $this->createTask($user, 'Task due Jan 15', null, Task::STATUS_PENDING, 2, null, new \DateTimeImmutable('2024-01-15'));
        $this->createTask($user, 'Task due Feb 1', null, Task::STATUS_PENDING, 2, null, new \DateTimeImmutable('2024-02-01'));

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?due_after=2024-01-10'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['items']);
        $titles = array_column($data['items'], 'title');
        $this->assertContains('Task due Jan 15', $titles);
        $this->assertContains('Task due Feb 1', $titles);
    }

    public function testFilterByHasNoDueDate(): void
    {
        $user = $this->createUser('filter-no-due-date@example.com', 'Password123');

        $this->createTask($user, 'Task with due date', null, Task::STATUS_PENDING, 2, null, new \DateTimeImmutable('2024-01-15'));
        $this->createTask($user, 'Task without due date 1');
        $this->createTask($user, 'Task without due date 2');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?has_no_due_date=true'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['items']);
        foreach ($data['items'] as $item) {
            $this->assertNull($item['dueDate']);
        }
    }

    public function testFilterByHasDueDate(): void
    {
        $user = $this->createUser('filter-has-due-date@example.com', 'Password123');

        $this->createTask($user, 'Task with due date', null, Task::STATUS_PENDING, 2, null, new \DateTimeImmutable('2024-01-15'));
        $this->createTask($user, 'Task without due date');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?has_no_due_date=false'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('Task with due date', $data['items'][0]['title']);
    }

    // ========================================
    // Filter by Search Term Tests
    // ========================================

    public function testFilterBySearchTerm(): void
    {
        $user = $this->createUser('filter-search@example.com', 'Password123');

        $this->createTask($user, 'Buy groceries', 'Need to buy milk and bread');
        $this->createTask($user, 'Call the dentist', 'Schedule annual checkup');
        $this->createTask($user, 'Review documents', 'Contains milk formula calculations');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?search=milk'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should find tasks with "milk" in title or description
        $this->assertCount(2, $data['items']);
        $titles = array_column($data['items'], 'title');
        $this->assertContains('Buy groceries', $titles);
        $this->assertContains('Review documents', $titles);
    }

    // ========================================
    // Filter by Include Completed Tests
    // ========================================

    public function testFilterWithIncludeCompletedFalse(): void
    {
        $user = $this->createUser('filter-no-completed@example.com', 'Password123');

        $this->createTask($user, 'Pending Task', null, Task::STATUS_PENDING);
        $this->createTask($user, 'In Progress Task', null, Task::STATUS_IN_PROGRESS);
        $this->createTask($user, 'Completed Task', null, Task::STATUS_COMPLETED);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?include_completed=false'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['items']);
        foreach ($data['items'] as $item) {
            $this->assertNotEquals(Task::STATUS_COMPLETED, $item['status']);
        }
    }

    public function testFilterWithIncludeCompletedTrue(): void
    {
        $user = $this->createUser('filter-with-completed@example.com', 'Password123');

        $this->createTask($user, 'Pending Task', null, Task::STATUS_PENDING);
        $this->createTask($user, 'Completed Task', null, Task::STATUS_COMPLETED);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?include_completed=true'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['items']);
    }

    // ========================================
    // Combined Filters Tests
    // ========================================

    public function testCombinedFilters(): void
    {
        $user = $this->createUser('filter-combined@example.com', 'Password123');

        $project = $this->createProject($user, 'Work Project');
        $tag = $this->createTag($user, 'Urgent');

        // High priority, urgent, in project, due soon - should match
        $task1 = $this->createTask(
            $user,
            'Urgent work task',
            null,
            Task::STATUS_PENDING,
            4,
            $project,
            new \DateTimeImmutable('2024-01-15')
        );
        $task1->addTag($tag);

        // Low priority - should not match
        $this->createTask(
            $user,
            'Low priority task',
            null,
            Task::STATUS_PENDING,
            1,
            $project,
            new \DateTimeImmutable('2024-01-15')
        );

        // No tag - should not match
        $this->createTask(
            $user,
            'Task without tag',
            null,
            Task::STATUS_PENDING,
            4,
            $project,
            new \DateTimeImmutable('2024-01-15')
        );

        // Different project - should not match
        $task4 = $this->createTask(
            $user,
            'Different project task',
            null,
            Task::STATUS_PENDING,
            4,
            null,
            new \DateTimeImmutable('2024-01-15')
        );
        $task4->addTag($tag);

        $this->entityManager->flush();

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?project_ids=' . $project->getId() .
            '&priority_min=3' .
            '&tag_ids=' . $tag->getId() .
            '&due_before=2024-01-20'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('Urgent work task', $data['items'][0]['title']);
    }

    // ========================================
    // Validation Tests
    // ========================================

    public function testFilterValidationMaxProjects(): void
    {
        $user = $this->createUser('filter-max-projects@example.com', 'Password123');

        // Create 51 project UUIDs (exceeds limit of 50)
        $projectIds = [];
        for ($i = 0; $i < 51; $i++) {
            $projectIds[] = $this->generateUuid();
        }

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?project_ids=' . implode(',', $projectIds)
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testFilterValidationMaxTags(): void
    {
        $user = $this->createUser('filter-max-tags@example.com', 'Password123');

        // Create 51 tag UUIDs (exceeds limit of 50)
        $tagIds = [];
        for ($i = 0; $i < 51; $i++) {
            $tagIds[] = $this->generateUuid();
        }

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?tag_ids=' . implode(',', $tagIds)
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testFilterValidationInvalidStatus(): void
    {
        $user = $this->createUser('filter-invalid-status@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?statuses=pending,invalid_status'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testFilterValidationInvalidPriorityMin(): void
    {
        $user = $this->createUser('filter-invalid-priority-min@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?priority_min=-1'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testFilterValidationInvalidPriorityMax(): void
    {
        $user = $this->createUser('filter-invalid-priority-max@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?priority_max=10'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testFilterValidationInvalidTagMode(): void
    {
        $user = $this->createUser('filter-invalid-tag-mode@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?tag_mode=INVALID'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testFilterValidationInvalidProjectUuid(): void
    {
        $user = $this->createUser('filter-invalid-project-uuid@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?project_ids=not-a-valid-uuid'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testFilterValidationInvalidTagUuid(): void
    {
        $user = $this->createUser('filter-invalid-tag-uuid@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?tag_ids=not-a-valid-uuid'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    // ========================================
    // Authentication Tests
    // ========================================

    public function testFilterRequiresAuthentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/tasks?statuses=pending');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // User Isolation Tests
    // ========================================

    public function testFilterRespectsUserIsolation(): void
    {
        $user1 = $this->createUser('user1-filter@example.com', 'Password123');
        $user2 = $this->createUser('user2-filter@example.com', 'Password123');

        $this->createTask($user1, 'User 1 Pending Task', null, Task::STATUS_PENDING);
        $this->createTask($user2, 'User 2 Pending Task', null, Task::STATUS_PENDING);

        $response = $this->authenticatedApiRequest(
            $user1,
            'GET',
            '/api/v1/tasks?statuses=pending'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('User 1 Pending Task', $data['items'][0]['title']);
    }
}
