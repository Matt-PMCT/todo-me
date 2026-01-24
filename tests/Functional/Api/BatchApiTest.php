<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the Batch API endpoint.
 *
 * Tests:
 * - Batch create operations
 * - Batch update operations
 * - Batch delete operations
 * - Batch complete operations
 * - Batch reschedule operations
 * - Mixed operations
 * - Partial success mode (default)
 * - Atomic mode
 * - Validation errors
 * - Batch undo
 * - Authentication required
 */
class BatchApiTest extends ApiTestCase
{
    // ========================================
    // Single Operation Tests
    // ========================================

    public function testBatchCreateSingleTask(): void
    {
        $user = $this->createUser('batch-create@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/batch',
            [
                'operations' => [
                    [
                        'action' => 'create',
                        'data' => [
                            'title' => 'New Task from Batch',
                            'priority' => 3,
                        ],
                    ],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->assertSuccessResponse($response);

        $this->assertTrue($data['data']['success']);
        $this->assertEquals(1, $data['data']['totalOperations']);
        $this->assertEquals(1, $data['data']['successfulOperations']);
        $this->assertEquals(0, $data['data']['failedOperations']);
        $this->assertCount(1, $data['data']['results']);
        $this->assertTrue($data['data']['results'][0]['success']);
        $this->assertEquals('create', $data['data']['results'][0]['action']);
        $this->assertArrayHasKey('taskId', $data['data']['results'][0]);
    }

    public function testBatchUpdateTask(): void
    {
        $user = $this->createUser('batch-update@example.com', 'Password123');
        $task = $this->createTask($user, 'Original Title');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/batch',
            [
                'operations' => [
                    [
                        'action' => 'update',
                        'taskId' => $task->getId(),
                        'data' => [
                            'title' => 'Updated Title',
                        ],
                    ],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['success']);
        $this->assertTrue($data['results'][0]['success']);
        $this->assertEquals($task->getId(), $data['results'][0]['taskId']);

        // Verify task was updated
        $this->refreshEntity($task);
        $this->assertEquals('Updated Title', $task->getTitle());
    }

    public function testBatchDeleteTask(): void
    {
        $user = $this->createUser('batch-delete@example.com', 'Password123');
        $task = $this->createTask($user, 'Task to Delete');
        $taskId = $task->getId();

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/batch',
            [
                'operations' => [
                    [
                        'action' => 'delete',
                        'taskId' => $taskId,
                    ],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['success']);
        $this->assertTrue($data['results'][0]['success']);
    }

    public function testBatchCompleteTask(): void
    {
        $user = $this->createUser('batch-complete@example.com', 'Password123');
        $task = $this->createTask($user, 'Task to Complete', null, Task::STATUS_PENDING);

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/batch',
            [
                'operations' => [
                    [
                        'action' => 'complete',
                        'taskId' => $task->getId(),
                    ],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['success']);

        // Verify task was completed
        $this->refreshEntity($task);
        $this->assertEquals(Task::STATUS_COMPLETED, $task->getStatus());
    }

    public function testBatchRescheduleTask(): void
    {
        $user = $this->createUser('batch-reschedule@example.com', 'Password123');
        $task = $this->createTask($user, 'Task to Reschedule', null, Task::STATUS_PENDING, 3, null, new \DateTimeImmutable('2026-01-15'));

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/batch',
            [
                'operations' => [
                    [
                        'action' => 'reschedule',
                        'taskId' => $task->getId(),
                        'data' => [
                            'due_date' => '2026-02-15',
                        ],
                    ],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['success']);

        // Verify task was rescheduled
        $this->refreshEntity($task);
        $this->assertEquals('2026-02-15', $task->getDueDate()->format('Y-m-d'));
    }

    // ========================================
    // Multiple Operations Tests
    // ========================================

    public function testBatchMultipleOperations(): void
    {
        $user = $this->createUser('batch-multi@example.com', 'Password123');
        $task1 = $this->createTask($user, 'Task 1');
        $task2 = $this->createTask($user, 'Task 2');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/batch',
            [
                'operations' => [
                    ['action' => 'create', 'data' => ['title' => 'New Task']],
                    ['action' => 'update', 'taskId' => $task1->getId(), 'data' => ['title' => 'Updated']],
                    ['action' => 'complete', 'taskId' => $task2->getId()],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['success']);
        $this->assertEquals(3, $data['totalOperations']);
        $this->assertEquals(3, $data['successfulOperations']);
        $this->assertEquals(0, $data['failedOperations']);
    }

    // ========================================
    // Partial Success Mode Tests
    // ========================================

    public function testBatchPartialSuccessMode(): void
    {
        $user = $this->createUser('batch-partial@example.com', 'Password123');
        $validTask = $this->createTask($user, 'Valid Task');
        $invalidTaskId = $this->generateUuid(); // Non-existent task

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/batch',
            [
                'operations' => [
                    ['action' => 'complete', 'taskId' => $validTask->getId()],
                    ['action' => 'complete', 'taskId' => $invalidTaskId], // Will fail
                    ['action' => 'create', 'data' => ['title' => 'Another Task']],
                ],
            ]
        );

        // 207 Multi-Status for partial success
        $this->assertResponseStatusCode(207, $response);

        $data = $this->getResponseData($response);

        $this->assertFalse($data['success']); // Overall not success due to failure
        $this->assertEquals(3, $data['totalOperations']);
        $this->assertEquals(2, $data['successfulOperations']);
        $this->assertEquals(1, $data['failedOperations']);

        // Check individual results
        $this->assertTrue($data['results'][0]['success']);
        $this->assertFalse($data['results'][1]['success']);
        $this->assertEquals('NOT_FOUND', $data['results'][1]['errorCode']);
        $this->assertTrue($data['results'][2]['success']);
    }

    // ========================================
    // Atomic Mode Tests
    // ========================================

    public function testBatchAtomicModeSuccess(): void
    {
        $user = $this->createUser('batch-atomic-success@example.com', 'Password123');
        $task = $this->createTask($user, 'Task for Atomic');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/batch?atomic=true',
            [
                'operations' => [
                    ['action' => 'create', 'data' => ['title' => 'Atomic Task 1']],
                    ['action' => 'update', 'taskId' => $task->getId(), 'data' => ['title' => 'Atomic Updated']],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['successfulOperations']);
    }

    public function testBatchAtomicModeRollbackOnFailure(): void
    {
        $user = $this->createUser('batch-atomic-fail@example.com', 'Password123');
        $task = $this->createTask($user, 'Original Title');
        $originalTitle = $task->getTitle();
        $invalidTaskId = $this->generateUuid();

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/batch?atomic=true',
            [
                'operations' => [
                    ['action' => 'update', 'taskId' => $task->getId(), 'data' => ['title' => 'Should Be Rolled Back']],
                    ['action' => 'complete', 'taskId' => $invalidTaskId], // Will fail
                ],
            ]
        );

        // In atomic mode, partial failure returns 207 but with rollback
        $this->assertResponseStatusCode(207, $response);

        $data = $this->getResponseData($response);

        $this->assertFalse($data['success']);
        $this->assertEquals(1, $data['failedOperations']);

        // The first operation should show as successful (it executed before the failure)
        // but the changes were rolled back

        // Verify the task was NOT updated due to rollback
        $this->clearEntityManager();
        $task = $this->entityManager->find(Task::class, $task->getId());
        $this->assertEquals($originalTitle, $task->getTitle());
    }

    // ========================================
    // Undo Tests
    // ========================================

    public function testBatchUndoToken(): void
    {
        $user = $this->createUser('batch-undo-token@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/batch',
            [
                'operations' => [
                    ['action' => 'create', 'data' => ['title' => 'Task with Undo']],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('undoToken', $data);
    }

    // ========================================
    // Validation Tests
    // ========================================

    public function testBatchRequiresOperations(): void
    {
        $user = $this->createUser('batch-no-ops@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/batch',
            [
                'operations' => [],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testBatchRejectsInvalidAction(): void
    {
        $user = $this->createUser('batch-invalid-action@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/batch',
            [
                'operations' => [
                    ['action' => 'invalid_action', 'data' => ['title' => 'Test']],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testBatchRejectsMissingTaskId(): void
    {
        $user = $this->createUser('batch-no-taskid@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/batch',
            [
                'operations' => [
                    ['action' => 'update', 'data' => ['title' => 'Test']], // Missing taskId
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testBatchRejectsTooManyOperations(): void
    {
        $user = $this->createUser('batch-too-many@example.com', 'Password123');

        // Create 101 operations (max is 100)
        $operations = [];
        for ($i = 0; $i < 101; $i++) {
            $operations[] = ['action' => 'create', 'data' => ['title' => "Task $i"]];
        }

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/batch',
            ['operations' => $operations]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    // ========================================
    // Authentication Tests
    // ========================================

    public function testBatchRequiresAuthentication(): void
    {
        $response = $this->apiRequest(
            'POST',
            '/api/v1/tasks/batch',
            [
                'operations' => [
                    ['action' => 'create', 'data' => ['title' => 'Test']],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Isolation Tests
    // ========================================

    public function testBatchCannotAccessOtherUsersTasks(): void
    {
        $user1 = $this->createUser('batch-user1@example.com', 'Password123');
        $user2 = $this->createUser('batch-user2@example.com', 'Password123');

        $user1Task = $this->createTask($user1, 'User 1 Task');

        // User2 tries to update user1's task
        $response = $this->authenticatedApiRequest(
            $user2,
            'POST',
            '/api/v1/tasks/batch',
            [
                'operations' => [
                    ['action' => 'update', 'taskId' => $user1Task->getId(), 'data' => ['title' => 'Hacked']],
                ],
            ]
        );

        $this->assertResponseStatusCode(207, $response); // Partial failure

        $data = $this->getResponseData($response);

        $this->assertFalse($data['results'][0]['success']);
        // Should return NOT_FOUND or FORBIDDEN
        $this->assertContains($data['results'][0]['errorCode'], ['NOT_FOUND', 'FORBIDDEN']);
    }
}
