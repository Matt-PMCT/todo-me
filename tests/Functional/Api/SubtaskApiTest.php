<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the Subtask API endpoints.
 *
 * Tests:
 * - Create subtask (success, ownership validation, nesting depth validation)
 * - List subtasks for a parent task
 * - Subtask counts in task response
 * - Cascade delete of subtasks when parent is deleted
 * - List tasks excludes subtasks by default
 */
class SubtaskApiTest extends ApiTestCase
{
    // ========================================
    // Create Subtask Tests
    // ========================================

    public function testCreateSubtaskSuccess(): void
    {
        $user = $this->createUser('subtask-create@example.com', 'Password123');
        $parentTask = $this->createTask($user, 'Parent Task');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks',
            [
                'title' => 'Subtask 1',
                'description' => 'First subtask',
                'priority' => 3,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('Subtask 1', $data['title']);
        $this->assertEquals('First subtask', $data['description']);
        $this->assertEquals(3, $data['priority']);
        $this->assertEquals(Task::STATUS_PENDING, $data['status']);
        $this->assertArrayHasKey('parentTaskId', $data);
        $this->assertEquals($parentTask->getId(), $data['parentTaskId']);
    }

    public function testCreateSubtaskValidatesParentOwnership(): void
    {
        $user1 = $this->createUser('user1-subtask@example.com', 'Password123');
        $user2 = $this->createUser('user2-subtask@example.com', 'Password123');
        $user2Task = $this->createTask($user2, 'User 2 Task');

        // User 1 tries to create a subtask for User 2's task
        $response = $this->authenticatedApiRequest(
            $user1,
            'POST',
            '/api/v1/tasks/' . $user2Task->getId() . '/subtasks',
            ['title' => 'Unauthorized Subtask']
        );

        // Should return 403 or 404 (depending on implementation - hiding existence)
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_NOT_FOUND,
            Response::HTTP_FORBIDDEN,
        ]);
    }

    public function testCreateSubtaskPreventsTooDeepNesting(): void
    {
        $user = $this->createUser('subtask-nesting@example.com', 'Password123');
        $parentTask = $this->createTask($user, 'Parent Task');

        // Create a subtask
        $subtaskResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks',
            ['title' => 'Subtask 1']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $subtaskResponse);
        $subtaskData = $this->getResponseData($subtaskResponse);
        $subtaskId = $subtaskData['id'];

        // Try to create a subtask of the subtask (should fail)
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $subtaskId . '/subtasks',
            ['title' => 'Sub-subtask']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');

        $error = $this->getResponseError($response);
        $this->assertArrayHasKey('details', $error);
        $this->assertArrayHasKey('errors', $error['details']);
        $this->assertArrayHasKey('parentTaskId', $error['details']['errors']);
    }

    // ========================================
    // List Subtasks Tests
    // ========================================

    public function testListSubtasks(): void
    {
        $user = $this->createUser('list-subtasks@example.com', 'Password123');
        $parentTask = $this->createTask($user, 'Parent Task');

        // Create subtasks
        $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks',
            ['title' => 'Subtask 1']
        );

        $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks',
            ['title' => 'Subtask 2']
        );

        $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks',
            ['title' => 'Subtask 3']
        );

        // List subtasks
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('tasks', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('completedCount', $data);
        $this->assertCount(3, $data['tasks']);
        $this->assertEquals(3, $data['total']);
        $this->assertEquals(0, $data['completedCount']);

        // All subtasks should reference the parent
        foreach ($data['tasks'] as $subtask) {
            $this->assertEquals($parentTask->getId(), $subtask['parentTaskId']);
        }
    }

    // ========================================
    // Subtask Counts in Response Tests
    // ========================================

    public function testSubtaskCountsInResponse(): void
    {
        $user = $this->createUser('subtask-counts@example.com', 'Password123');
        $parentTask = $this->createTask($user, 'Parent Task');

        // Create 3 subtasks
        $subtask1Response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks',
            ['title' => 'Subtask 1']
        );
        $subtask1Id = $this->getResponseData($subtask1Response)['id'];

        $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks',
            ['title' => 'Subtask 2']
        );

        $subtask3Response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks',
            ['title' => 'Subtask 3']
        );
        $subtask3Id = $this->getResponseData($subtask3Response)['id'];

        // Complete 2 of them
        $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $subtask1Id . '/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $subtask3Id . '/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        // Get the parent task and verify subtask counts
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/' . $parentTask->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('subtaskCount', $data);
        $this->assertArrayHasKey('completedSubtaskCount', $data);
        $this->assertEquals(3, $data['subtaskCount']);
        $this->assertEquals(2, $data['completedSubtaskCount']);
    }

    // ========================================
    // Cascade Delete Tests
    // ========================================

    public function testDeleteParentCascadesSubtasks(): void
    {
        $user = $this->createUser('cascade-delete@example.com', 'Password123');
        $parentTask = $this->createTask($user, 'Parent Task');

        // Create subtasks
        $subtask1Response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks',
            ['title' => 'Subtask 1']
        );
        $subtask1Id = $this->getResponseData($subtask1Response)['id'];

        $subtask2Response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks',
            ['title' => 'Subtask 2']
        );
        $subtask2Id = $this->getResponseData($subtask2Response)['id'];

        // Delete the parent task
        $deleteResponse = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/tasks/' . $parentTask->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $deleteResponse);

        // Verify subtasks are also deleted (should return 404)
        $response1 = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/' . $subtask1Id
        );
        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response1);

        $response2 = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/' . $subtask2Id
        );
        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response2);
    }

    // ========================================
    // List Tasks Excludes Subtasks Tests
    // ========================================

    public function testListTasksExcludesSubtasksByDefault(): void
    {
        $user = $this->createUser('exclude-subtasks@example.com', 'Password123');

        // Create top-level tasks
        $parentTask = $this->createTask($user, 'Parent Task');
        $this->createTask($user, 'Regular Task 1');
        $this->createTask($user, 'Regular Task 2');

        // Create subtasks
        $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks',
            ['title' => 'Subtask 1']
        );

        $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks',
            ['title' => 'Subtask 2']
        );

        // List tasks (default behavior excludes subtasks)
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should only see the 3 top-level tasks, not the 2 subtasks
        $this->assertCount(3, $data['items']);
        $this->assertEquals(3, $data['meta']['total']);

        // Verify none of the items have a parentTaskId
        foreach ($data['items'] as $task) {
            $this->assertArrayNotHasKey('parentTaskId', $task);
        }
    }

    public function testListTasksIncludesSubtasksWhenExplicitlyRequested(): void
    {
        $user = $this->createUser('include-subtasks@example.com', 'Password123');

        // Create top-level tasks
        $parentTask = $this->createTask($user, 'Parent Task');
        $this->createTask($user, 'Regular Task 1');

        // Create subtasks
        $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks',
            ['title' => 'Subtask 1']
        );

        $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/' . $parentTask->getId() . '/subtasks',
            ['title' => 'Subtask 2']
        );

        // List tasks with exclude_subtasks=false
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?exclude_subtasks=false'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should see all 4 tasks (2 top-level + 2 subtasks)
        $this->assertCount(4, $data['items']);
        $this->assertEquals(4, $data['meta']['total']);

        // Verify that at least some items have parentTaskId
        $subtasks = array_filter($data['items'], fn($task) => isset($task['parentTaskId']));
        $this->assertCount(2, $subtasks);
    }
}
