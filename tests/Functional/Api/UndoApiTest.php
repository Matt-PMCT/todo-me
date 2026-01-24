<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the Undo API endpoints.
 *
 * Tests:
 * - Generic undo endpoint
 * - Task completion undo (including recurring tasks)
 * - Task deletion undo
 * - Task update undo
 * - Project archive undo
 * - Token validation and authentication
 */
class UndoApiTest extends ApiTestCase
{
    // ========================================
    // Generic Undo Endpoint Tests
    // ========================================

    public function testGenericUndoEndpoint(): void
    {
        $user = $this->createUser('undo-generic@example.com', 'Password123');

        $task = $this->createTask($user, 'Task to update');

        // Update the task to get an undo token
        $updateResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $task->getId(),
            ['title' => 'Updated task title']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $updateResponse);
        $updateData = $this->getResponseData($updateResponse);
        $undoToken = $updateData['undoToken'] ?? null;
        $this->assertNotNull($undoToken, 'Expected undo token from update operation');

        // Use the generic undo endpoint
        $undoResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/undo',
            ['token' => $undoToken]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $undoResponse);

        $undoData = $this->assertSuccessResponse($undoResponse);
        $this->assertArrayHasKey('entityType', $undoData['data']);
        $this->assertEquals('task', $undoData['data']['entityType']);
        $this->assertArrayHasKey('entity', $undoData['data']);
        $this->assertEquals('Task to update', $undoData['data']['entity']['title']);
    }

    // ========================================
    // Task Completion Undo Tests
    // ========================================

    public function testUndoTaskCompletion(): void
    {
        $user = $this->createUser('undo-complete@example.com', 'Password123');

        $task = $this->createTask($user, 'Task to complete', null, Task::STATUS_PENDING);

        // Complete the task
        $completeResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $task->getId() . '/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $completeResponse);
        $completeData = $this->getResponseData($completeResponse);
        $undoToken = $completeData['undoToken'] ?? null;
        $this->assertNotNull($undoToken);

        // Verify task is completed (TaskStatusResult returns task fields at top level)
        $this->assertEquals(Task::STATUS_COMPLETED, $completeData['status']);

        // Undo the completion
        $undoResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/undo/' . $undoToken
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $undoResponse);

        // Verify task is back to pending
        $undoData = $this->getResponseData($undoResponse);
        $this->assertEquals(Task::STATUS_PENDING, $undoData['status']);
    }

    public function testUndoRecurringTaskCompletion(): void
    {
        $user = $this->createUser('undo-recurring@example.com', 'Password123');

        // Create a recurring task
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Daily recurring task',
                'isRecurring' => true,
                'recurrenceRule' => 'daily',
                'dueDate' => date('Y-m-d'),
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $createResponse);
        $createData = $this->getResponseData($createResponse);
        $taskId = $createData['id'];

        // Complete the recurring task (creates next instance)
        $completeResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $taskId . '/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $completeResponse);
        $completeData = $this->getResponseData($completeResponse);

        // Verify next task was created
        $this->assertArrayHasKey('nextTask', $completeData);
        $this->assertNotNull($completeData['nextTask']);
        $nextTaskId = $completeData['nextTask']['id'];

        $undoToken = $completeData['undoToken'] ?? null;
        $this->assertNotNull($undoToken);

        // Undo the completion
        $undoResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/undo/' . $undoToken
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $undoResponse);

        // Verify original task is restored to pending
        $undoData = $this->getResponseData($undoResponse);
        $this->assertEquals(Task::STATUS_PENDING, $undoData['status']);

        // Verify the auto-generated next task was deleted
        $nextTaskResponse = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/' . $nextTaskId
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $nextTaskResponse);
    }

    public function testUndoRecurringTaskWhenNextTaskCompleted(): void
    {
        $user = $this->createUser('undo-recurring-completed@example.com', 'Password123');

        // Create a recurring task
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Daily recurring task',
                'isRecurring' => true,
                'recurrenceRule' => 'daily',
                'dueDate' => date('Y-m-d'),
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $createResponse);
        $createData = $this->getResponseData($createResponse);
        $taskId = $createData['id'];

        // Complete the recurring task (creates next instance)
        $completeResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $taskId . '/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $completeResponse);
        $completeData = $this->getResponseData($completeResponse);
        $nextTaskId = $completeData['nextTask']['id'];
        $undoToken = $completeData['undoToken'];

        // Complete the next task (so it's no longer pending)
        $completeNextResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $nextTaskId . '/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $completeNextResponse);

        // Now undo the original completion
        $undoResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/undo/' . $undoToken
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $undoResponse);

        // The next task should NOT be deleted because it was completed
        $nextTaskResponse = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/' . $nextTaskId
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $nextTaskResponse);
        $nextTaskData = $this->getResponseData($nextTaskResponse);
        $this->assertEquals(Task::STATUS_COMPLETED, $nextTaskData['status']);
    }

    public function testUndoRecurringTaskWhenNextTaskModified(): void
    {
        $user = $this->createUser('undo-recurring-modified@example.com', 'Password123');

        // Create a recurring task
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Daily recurring task',
                'isRecurring' => true,
                'recurrenceRule' => 'daily',
                'dueDate' => date('Y-m-d'),
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $createResponse);
        $createData = $this->getResponseData($createResponse);
        $taskId = $createData['id'];

        // Complete the recurring task (creates next instance)
        $completeResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $taskId . '/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $completeResponse);
        $completeData = $this->getResponseData($completeResponse);
        $nextTaskId = $completeData['nextTask']['id'];
        $undoToken = $completeData['undoToken'];

        // Modify the next task (change status to in_progress)
        $updateNextResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $nextTaskId . '/status',
            ['status' => Task::STATUS_IN_PROGRESS]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $updateNextResponse);

        // Now undo the original completion
        $undoResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/undo/' . $undoToken
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $undoResponse);

        // The next task should NOT be deleted because its status was changed from pending
        $nextTaskResponse = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/' . $nextTaskId
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $nextTaskResponse);
        $nextTaskData = $this->getResponseData($nextTaskResponse);
        $this->assertEquals(Task::STATUS_IN_PROGRESS, $nextTaskData['status']);
    }

    // ========================================
    // Task Deletion Undo Tests
    // ========================================

    public function testUndoTaskDeletion(): void
    {
        $user = $this->createUser('undo-delete@example.com', 'Password123');

        $task = $this->createTask($user, 'Task to delete', 'Task description', Task::STATUS_PENDING, 3);
        $taskId = $task->getId();

        // Delete the task
        $deleteResponse = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/tasks/' . $taskId
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $deleteResponse);
        $deleteData = $this->getResponseData($deleteResponse);
        $undoToken = $deleteData['undoToken'] ?? null;
        $this->assertNotNull($undoToken);

        // Verify task is deleted
        $getResponse = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/' . $taskId
        );
        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $getResponse);

        // Undo the deletion
        $undoResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/undo/' . $undoToken
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $undoResponse);

        // Verify task is restored with original properties
        $undoData = $this->getResponseData($undoResponse);
        $this->assertEquals('Task to delete', $undoData['title']);
        $this->assertEquals('Task description', $undoData['description']);
        $this->assertEquals(Task::STATUS_PENDING, $undoData['status']);
        $this->assertEquals(3, $undoData['priority']);
    }

    // ========================================
    // Task Update Undo Tests
    // ========================================

    public function testUndoTaskUpdate(): void
    {
        $user = $this->createUser('undo-update@example.com', 'Password123');

        $task = $this->createTask(
            $user,
            'Original title',
            'Original description',
            Task::STATUS_PENDING,
            2
        );

        // Update the task
        $updateResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $task->getId(),
            [
                'title' => 'Updated title',
                'description' => 'Updated description',
                'priority' => 4,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $updateResponse);
        $updateData = $this->getResponseData($updateResponse);
        $undoToken = $updateData['undoToken'] ?? null;
        $this->assertNotNull($undoToken);

        // Verify task was updated
        $this->assertEquals('Updated title', $updateData['title']);
        $this->assertEquals('Updated description', $updateData['description']);
        $this->assertEquals(4, $updateData['priority']);

        // Undo the update
        $undoResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/undo/' . $undoToken
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $undoResponse);

        // Verify task has original values
        $undoData = $this->getResponseData($undoResponse);
        $this->assertEquals('Original title', $undoData['title']);
        $this->assertEquals('Original description', $undoData['description']);
        $this->assertEquals(2, $undoData['priority']);
    }

    // ========================================
    // Project Archive Undo Tests
    // ========================================

    public function testUndoProjectArchive(): void
    {
        $user = $this->createUser('undo-archive@example.com', 'Password123');

        $project = $this->createProject($user, 'Project to archive', 'Project description', false);

        // Archive the project
        $archiveResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/projects/' . $project->getId() . '/archive'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $archiveResponse);

        $archiveJson = $this->assertJsonResponse($archiveResponse);
        $undoToken = $archiveJson['meta']['undoToken'] ?? null;
        $this->assertNotNull($undoToken, 'Expected undo token from archive operation');

        // Verify project is archived
        $archiveData = $this->getResponseData($archiveResponse);
        $this->assertTrue($archiveData['isArchived']);

        // Undo the archive
        $undoResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects/undo/' . $undoToken
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $undoResponse);

        // Verify project is unarchived
        $getResponse = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects/' . $project->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $getResponse);
        $getData = $this->getResponseData($getResponse);
        $this->assertFalse($getData['isArchived']);
    }

    // ========================================
    // Token Validation Tests
    // ========================================

    public function testUndoExpiredToken(): void
    {
        $user = $this->createUser('undo-expired@example.com', 'Password123');

        // Use an invalid/malformed token that won't match any stored token
        $invalidToken = 'definitely-not-a-real-token-' . uniqid();

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/undo',
            ['token' => $invalidToken]
        );

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, 'INVALID_UNDO_TOKEN');
    }

    public function testUndoInvalidToken(): void
    {
        $user = $this->createUser('undo-invalid@example.com', 'Password123');

        // Use a non-existent token
        $nonExistentToken = $this->generateUuid();

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/undo',
            ['token' => $nonExistentToken]
        );

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, 'INVALID_UNDO_TOKEN');
    }

    public function testUndoWrongUser(): void
    {
        $user1 = $this->createUser('undo-user1@example.com', 'Password123');
        $user2 = $this->createUser('undo-user2@example.com', 'Password123');

        $task = $this->createTask($user1, 'User1 task');

        // Update task as user1 to get undo token
        $updateResponse = $this->authenticatedApiRequest(
            $user1,
            'PATCH',
            '/api/v1/tasks/' . $task->getId(),
            ['title' => 'Updated by user1']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $updateResponse);
        $updateData = $this->getResponseData($updateResponse);
        $undoToken = $updateData['undoToken'];

        // Try to use the token as user2
        $undoResponse = $this->authenticatedApiRequest(
            $user2,
            'POST',
            '/api/v1/undo',
            ['token' => $undoToken]
        );

        // Should fail because the token belongs to user1
        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $undoResponse);
        $this->assertErrorCode($undoResponse, 'INVALID_UNDO_TOKEN');
    }

    // ========================================
    // Authentication Tests
    // ========================================

    public function testUndoRequiresAuthentication(): void
    {
        $response = $this->apiRequest(
            'POST',
            '/api/v1/undo',
            ['token' => 'some-token']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testUndoMissingToken(): void
    {
        $user = $this->createUser('undo-missing@example.com', 'Password123');

        // POST without token should fail
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/undo',
            []
        );

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, 'INVALID_UNDO_TOKEN');
    }
}
