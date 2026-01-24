<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the Task API endpoints.
 *
 * Tests:
 * - List tasks (empty, with tasks, pagination, filters)
 * - Create task (success, validation errors, with project, with tags)
 * - Get single task (success, not found, not owned)
 * - Update task (success, partial update, validation errors)
 * - Delete task (success, returns undo token)
 * - Status change (all valid transitions)
 * - Invalid status/priority errors (INVALID_STATUS, INVALID_PRIORITY codes)
 * - Undo delete (success, expired token, wrong user)
 * - Undo update
 * - Reorder tasks
 */
class TaskApiTest extends ApiTestCase
{
    // ========================================
    // List Tasks Tests
    // ========================================

    public function testListTasksEmpty(): void
    {
        $user = $this->createUser('list-empty@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('items', $data['data']);
        $this->assertEmpty($data['data']['items']);
        $this->assertArrayHasKey('meta', $data['data']);
        $this->assertEquals(0, $data['data']['meta']['total']);
    }

    public function testListTasksWithTasks(): void
    {
        $user = $this->createUser('list-tasks@example.com', 'Password123');

        $this->createTask($user, 'Task 1');
        $this->createTask($user, 'Task 2');
        $this->createTask($user, 'Task 3');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(3, $data['items']);
        $this->assertEquals(3, $data['meta']['total']);
    }

    public function testListTasksPagination(): void
    {
        $user = $this->createUser('list-pagination@example.com', 'Password123');

        // Create 25 tasks
        for ($i = 1; $i <= 25; $i++) {
            $this->createTask($user, "Task $i");
        }

        // Get first page with limit 10
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?page=1&limit=10'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(10, $data['items']);
        $this->assertEquals(25, $data['meta']['total']);
        $this->assertEquals(1, $data['meta']['page']);
        $this->assertEquals(10, $data['meta']['limit']);
        $this->assertEquals(3, $data['meta']['totalPages']);
        $this->assertTrue($data['meta']['hasNextPage']);
        $this->assertFalse($data['meta']['hasPreviousPage']);

        // Get second page
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?page=2&limit=10'
        );

        $data = $this->getResponseData($response);

        $this->assertCount(10, $data['items']);
        $this->assertTrue($data['meta']['hasNextPage']);
        $this->assertTrue($data['meta']['hasPreviousPage']);

        // Get third (last) page
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?page=3&limit=10'
        );

        $data = $this->getResponseData($response);

        $this->assertCount(5, $data['items']);
        $this->assertFalse($data['meta']['hasNextPage']);
        $this->assertTrue($data['meta']['hasPreviousPage']);
    }

    public function testListTasksFilterByStatus(): void
    {
        $user = $this->createUser('list-status@example.com', 'Password123');

        $this->createTask($user, 'Pending Task', null, Task::STATUS_PENDING);
        $this->createTask($user, 'In Progress Task', null, Task::STATUS_IN_PROGRESS);
        $this->createTask($user, 'Completed Task', null, Task::STATUS_COMPLETED);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?status=pending'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('Pending Task', $data['items'][0]['title']);
    }

    public function testListTasksFilterByPriority(): void
    {
        $user = $this->createUser('list-priority@example.com', 'Password123');

        $this->createTask($user, 'Low Priority', null, Task::STATUS_PENDING, 1);
        $this->createTask($user, 'Medium Priority', null, Task::STATUS_PENDING, 3);
        $this->createTask($user, 'High Priority', null, Task::STATUS_PENDING, 4);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?priority_min=4&priority_max=4'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('High Priority', $data['items'][0]['title']);
    }

    public function testListTasksFilterByProject(): void
    {
        $user = $this->createUser('list-project@example.com', 'Password123');
        $project = $this->createProject($user, 'Test Project');

        $this->createTask($user, 'Task in Project', null, Task::STATUS_PENDING, 3, $project);
        $this->createTask($user, 'Task without Project');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?project_ids=' . $project->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('Task in Project', $data['items'][0]['title']);
    }

    public function testListTasksFilterBySearch(): void
    {
        $user = $this->createUser('list-search@example.com', 'Password123');

        $this->createTask($user, 'Buy groceries', 'Need milk and eggs');
        $this->createTask($user, 'Call dentist', 'Schedule appointment');
        $this->createTask($user, 'Fix bug', 'Need to fix the milk calculation');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?search=milk'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should find tasks with "milk" in title or description
        $this->assertCount(2, $data['items']);
    }

    public function testListTasksUnauthenticated(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/tasks');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testListTasksOnlyOwned(): void
    {
        $user1 = $this->createUser('user1-owned@example.com', 'Password123');
        $user2 = $this->createUser('user2-owned@example.com', 'Password123');

        $this->createTask($user1, 'User 1 Task');
        $this->createTask($user2, 'User 2 Task');

        $response = $this->authenticatedApiRequest(
            $user1,
            'GET',
            '/api/v1/tasks'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('User 1 Task', $data['items'][0]['title']);
    }

    // ========================================
    // Create Task Tests
    // ========================================

    public function testCreateTaskSuccess(): void
    {
        $user = $this->createUser('create-task@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'New Task',
                'description' => 'Task description',
                'priority' => 4,
                'status' => 'pending',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('New Task', $data['title']);
        $this->assertEquals('Task description', $data['description']);
        $this->assertEquals(4, $data['priority']);
        $this->assertEquals('pending', $data['status']);
    }

    public function testCreateTaskWithMinimalData(): void
    {
        $user = $this->createUser('create-minimal@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            ['title' => 'Minimal Task']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Minimal Task', $data['title']);
        $this->assertEquals(Task::STATUS_PENDING, $data['status']);
        $this->assertEquals(Task::PRIORITY_DEFAULT, $data['priority']);
    }

    public function testCreateTaskValidationErrors(): void
    {
        $user = $this->createUser('create-validation@example.com', 'Password123');

        // Missing title
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            ['description' => 'No title provided']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testCreateTaskWithProject(): void
    {
        $user = $this->createUser('create-project@example.com', 'Password123');
        $project = $this->createProject($user, 'Test Project');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Task in Project',
                'projectId' => $project->getId(),
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('project', $data);
        $this->assertEquals($project->getId(), $data['project']['id']);
    }

    public function testCreateTaskWithTags(): void
    {
        $user = $this->createUser('create-tags@example.com', 'Password123');
        $tag1 = $this->createTag($user, 'Tag 1', '#FF0000');
        $tag2 = $this->createTag($user, 'Tag 2', '#00FF00');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Task with Tags',
                'tagIds' => [$tag1->getId(), $tag2->getId()],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('tags', $data);
        $this->assertCount(2, $data['tags']);
    }

    public function testCreateTaskWithDueDate(): void
    {
        $user = $this->createUser('create-due@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Task with Due Date',
                'dueDate' => '2024-12-31',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('dueDate', $data);
        $this->assertEquals('2024-12-31', $data['dueDate']);
    }

    public function testCreateTaskInvalidStatus(): void
    {
        $user = $this->createUser('create-invalid-status@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Task with Invalid Status',
                'status' => 'invalid_status',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');

        $error = $this->getResponseError($response);
        $this->assertArrayHasKey('details', $error);
        $this->assertArrayHasKey('errors', $error['details']);
        $this->assertArrayHasKey('status', $error['details']['errors']);
    }

    public function testCreateTaskInvalidPriority(): void
    {
        $user = $this->createUser('create-invalid-priority@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Task with Invalid Priority',
                'priority' => 10,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');

        $error = $this->getResponseError($response);
        $this->assertArrayHasKey('details', $error);
        $this->assertArrayHasKey('errors', $error['details']);
        $this->assertArrayHasKey('priority', $error['details']['errors']);
    }

    public function testCreateTaskInvalidProjectOwnership(): void
    {
        $user1 = $this->createUser('user1-create@example.com', 'Password123');
        $user2 = $this->createUser('user2-create@example.com', 'Password123');
        $project = $this->createProject($user2, 'User 2 Project');

        $response = $this->authenticatedApiRequest(
            $user1,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Task in Other Users Project',
                'projectId' => $project->getId(),
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_FORBIDDEN, $response);
    }

    public function testCreateTaskUnauthenticated(): void
    {
        $response = $this->apiRequest(
            'POST',
            '/api/v1/tasks',
            ['title' => 'Unauthorized Task']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Get Single Task Tests
    // ========================================

    public function testGetTaskSuccess(): void
    {
        $user = $this->createUser('get-task@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task', 'Description');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/' . $task->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals($task->getId(), $data['id']);
        $this->assertEquals('Test Task', $data['title']);
        $this->assertEquals('Description', $data['description']);
    }

    public function testGetTaskNotFound(): void
    {
        $user = $this->createUser('get-notfound@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/00000000-0000-0000-0000-000000000000'
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
        $this->assertErrorCode($response, 'RESOURCE_NOT_FOUND');
    }

    public function testGetTaskNotOwned(): void
    {
        $user1 = $this->createUser('user1-get@example.com', 'Password123');
        $user2 = $this->createUser('user2-get@example.com', 'Password123');
        $task = $this->createTask($user2, 'User 2 Task');

        $response = $this->authenticatedApiRequest(
            $user1,
            'GET',
            '/api/v1/tasks/' . $task->getId()
        );

        // Should return 404 or 403 (depending on implementation)
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_NOT_FOUND,
            Response::HTTP_FORBIDDEN,
        ]);
    }

    // ========================================
    // Update Task Tests
    // ========================================

    public function testUpdateTaskSuccess(): void
    {
        $user = $this->createUser('update-task@example.com', 'Password123');
        $task = $this->createTask($user, 'Original Title', 'Original Description');

        $response = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/tasks/' . $task->getId(),
            [
                'title' => 'Updated Title',
                'description' => 'Updated Description',
                'priority' => 4,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Updated Title', $data['title']);
        $this->assertEquals('Updated Description', $data['description']);
        $this->assertEquals(4, $data['priority']);
    }

    public function testUpdateTaskPartialUpdate(): void
    {
        $user = $this->createUser('update-partial@example.com', 'Password123');
        $task = $this->createTask($user, 'Original Title', 'Original Description', Task::STATUS_PENDING, 3);

        // Only update title
        $response = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/tasks/' . $task->getId(),
            ['title' => 'Only Title Updated']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Only Title Updated', $data['title']);
        $this->assertEquals('Original Description', $data['description']);
        $this->assertEquals(3, $data['priority']);
    }

    public function testUpdateTaskValidationErrors(): void
    {
        $user = $this->createUser('update-validation@example.com', 'Password123');
        $task = $this->createTask($user, 'Original Title');

        // Title too long (> 500 characters)
        $response = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/tasks/' . $task->getId(),
            ['title' => str_repeat('a', 501)]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testUpdateTaskReturnsUndoToken(): void
    {
        $user = $this->createUser('update-undo@example.com', 'Password123');
        $task = $this->createTask($user, 'Original Title');

        $response = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/tasks/' . $task->getId(),
            ['title' => 'Updated Title']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('undoToken', $data);
        $this->assertNotEmpty($data['undoToken']);
    }

    public function testUpdateTaskInvalidStatus(): void
    {
        $user = $this->createUser('update-invalid-status@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task');

        $response = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/tasks/' . $task->getId(),
            ['status' => 'not_a_real_status']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testUpdateTaskInvalidPriority(): void
    {
        $user = $this->createUser('update-invalid-priority@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task');

        $response = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/tasks/' . $task->getId(),
            ['priority' => 5]  // 5 is invalid since range is now 0-4
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    // ========================================
    // Delete Task Tests
    // ========================================

    public function testDeleteTaskSuccess(): void
    {
        $user = $this->createUser('delete-task@example.com', 'Password123');
        $task = $this->createTask($user, 'Task to Delete');
        $taskId = $task->getId();

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/tasks/' . $taskId
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('undoToken', $data);
        $this->assertNotEmpty($data['undoToken']);
    }

    public function testDeleteTaskNotFound(): void
    {
        $user = $this->createUser('delete-notfound@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/tasks/00000000-0000-0000-0000-000000000000'
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    public function testDeleteTaskNotOwned(): void
    {
        $user1 = $this->createUser('user1-delete@example.com', 'Password123');
        $user2 = $this->createUser('user2-delete@example.com', 'Password123');
        $task = $this->createTask($user2, 'User 2 Task');

        $response = $this->authenticatedApiRequest(
            $user1,
            'DELETE',
            '/api/v1/tasks/' . $task->getId()
        );

        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_NOT_FOUND,
            Response::HTTP_FORBIDDEN,
        ]);
    }

    // ========================================
    // Status Change Tests
    // ========================================

    public function testChangeStatusPendingToInProgress(): void
    {
        $user = $this->createUser('status-p2i@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task', null, Task::STATUS_PENDING);

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $task->getId() . '/status',
            ['status' => Task::STATUS_IN_PROGRESS]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals(Task::STATUS_IN_PROGRESS, $data['status']);
    }

    public function testChangeStatusPendingToCompleted(): void
    {
        $user = $this->createUser('status-p2c@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task', null, Task::STATUS_PENDING);

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $task->getId() . '/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals(Task::STATUS_COMPLETED, $data['status']);
        $this->assertArrayHasKey('completedAt', $data);
        $this->assertNotNull($data['completedAt']);
    }

    public function testChangeStatusInProgressToCompleted(): void
    {
        $user = $this->createUser('status-i2c@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task', null, Task::STATUS_IN_PROGRESS);

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $task->getId() . '/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals(Task::STATUS_COMPLETED, $data['status']);
    }

    public function testChangeStatusCompletedToPending(): void
    {
        $user = $this->createUser('status-c2p@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task', null, Task::STATUS_COMPLETED);

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $task->getId() . '/status',
            ['status' => Task::STATUS_PENDING]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals(Task::STATUS_PENDING, $data['status']);
        // completedAt should be cleared when moving from completed
        $this->assertNull($data['completedAt']);
    }

    public function testChangeStatusInvalidStatus(): void
    {
        $user = $this->createUser('status-invalid@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $task->getId() . '/status',
            ['status' => 'invalid']
        );

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, 'INVALID_STATUS');
    }

    public function testChangeStatusMissingStatus(): void
    {
        $user = $this->createUser('status-missing@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $task->getId() . '/status',
            []
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    public function testChangeStatusReturnsUndoToken(): void
    {
        $user = $this->createUser('status-undo@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task', null, Task::STATUS_PENDING);

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $task->getId() . '/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('undoToken', $data);
    }

    // ========================================
    // Undo Operations Tests
    // ========================================

    public function testUndoDeleteSuccess(): void
    {
        $user = $this->createUser('undo-delete@example.com', 'Password123');
        $task = $this->createTask($user, 'Task to Delete');
        $originalTitle = $task->getTitle();

        // Delete the task
        $deleteResponse = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/tasks/' . $task->getId()
        );

        $deleteData = $this->getResponseData($deleteResponse);
        $undoToken = $deleteData['undoToken'];

        // Undo the delete
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/undo/' . $undoToken
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals($originalTitle, $data['title']);
    }

    public function testUndoUpdateSuccess(): void
    {
        $user = $this->createUser('undo-update@example.com', 'Password123');
        $task = $this->createTask($user, 'Original Title', 'Original Description');

        // Update the task
        $updateResponse = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/tasks/' . $task->getId(),
            ['title' => 'New Title']
        );

        $updateData = $this->getResponseData($updateResponse);
        $undoToken = $updateData['undoToken'];

        // Undo the update
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/undo/' . $undoToken
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Original Title', $data['title']);
    }

    public function testUndoInvalidToken(): void
    {
        $user = $this->createUser('undo-invalid@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/undo/invalid-token-12345'
        );

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
    }

    public function testUndoWrongUser(): void
    {
        $user1 = $this->createUser('user1-undo@example.com', 'Password123');
        $user2 = $this->createUser('user2-undo@example.com', 'Password123');
        $task = $this->createTask($user1, 'User 1 Task');

        // User 1 deletes task
        $deleteResponse = $this->authenticatedApiRequest(
            $user1,
            'DELETE',
            '/api/v1/tasks/' . $task->getId()
        );

        $deleteData = $this->getResponseData($deleteResponse);
        $undoToken = $deleteData['undoToken'];

        // User 2 tries to undo
        $response = $this->authenticatedApiRequest(
            $user2,
            'POST',
            '/api/v1/tasks/undo/' . $undoToken
        );

        // Should fail - token belongs to different user
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            Response::HTTP_FORBIDDEN,
            Response::HTTP_NOT_FOUND,
        ]);
    }

    // ========================================
    // Reorder Tasks Tests
    // ========================================

    public function testReorderTasksSuccess(): void
    {
        $user = $this->createUser('reorder@example.com', 'Password123');

        $task1 = $this->createTask($user, 'Task 1');
        $task2 = $this->createTask($user, 'Task 2');
        $task3 = $this->createTask($user, 'Task 3');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/reorder',
            [
                'taskIds' => [
                    $task3->getId(),
                    $task1->getId(),
                    $task2->getId(),
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_NO_CONTENT, $response);
    }

    public function testReorderTasksMissingTaskIds(): void
    {
        $user = $this->createUser('reorder-missing@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/reorder',
            []
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    public function testReorderTasksInvalidUuid(): void
    {
        $user = $this->createUser('reorder-invalid@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/reorder',
            ['taskIds' => ['not-a-uuid', 'also-not-a-uuid']]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    // ========================================
    // Response Structure Tests
    // ========================================

    public function testTaskResponseStructure(): void
    {
        $user = $this->createUser('structure@example.com', 'Password123');
        $project = $this->createProject($user, 'Test Project');
        $tag = $this->createTag($user, 'Test Tag');
        $task = $this->createTask(
            $user,
            'Complete Task',
            'Full description',
            Task::STATUS_IN_PROGRESS,
            4,
            $project,
            new \DateTimeImmutable('2024-12-31')
        );
        $task->addTag($tag);
        $this->entityManager->flush();

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/' . $task->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Verify all expected fields are present
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('priority', $data);
        $this->assertArrayHasKey('dueDate', $data);
        $this->assertArrayHasKey('project', $data);
        $this->assertArrayHasKey('tags', $data);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertArrayHasKey('updatedAt', $data);
    }
}
