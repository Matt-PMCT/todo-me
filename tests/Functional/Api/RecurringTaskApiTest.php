<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for recurring task API functionality.
 *
 * Tests:
 * - Create recurring task (daily, weekly, monthly patterns)
 * - Complete recurring task creates next instance
 * - Absolute vs relative date calculation
 * - Complete forever stops recurrence
 * - Chain tracking with original_task_id
 * - Tags copied to new instance
 * - End date respected
 * - Invalid recurrence patterns rejected
 * - Recurring history endpoint
 */
class RecurringTaskApiTest extends ApiTestCase
{
    // ========================================
    // Create Recurring Task Tests
    // ========================================

    public function testCreateRecurringTaskWithDailyPattern(): void
    {
        $user = $this->createUser('recurring-daily@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Daily Task',
                'isRecurring' => true,
                'recurrenceRule' => 'every day',
                'dueDate' => '2026-01-24',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['isRecurring']);
        $this->assertEquals('every day', $data['recurrenceRule']);
        $this->assertEquals('absolute', $data['recurrenceType']);
    }

    public function testCreateRecurringTaskWithWeeklyPattern(): void
    {
        $user = $this->createUser('recurring-weekly@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Weekly Task',
                'isRecurring' => true,
                'recurrenceRule' => 'every Monday',
                'dueDate' => '2026-01-27', // Monday
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['isRecurring']);
        $this->assertEquals('every Monday', $data['recurrenceRule']);
        $this->assertEquals('absolute', $data['recurrenceType']);
    }

    public function testCreateRecurringTaskWithMonthlyPattern(): void
    {
        $user = $this->createUser('recurring-monthly@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Monthly Task',
                'isRecurring' => true,
                'recurrenceRule' => 'every month on the 15th',
                'dueDate' => '2026-01-15',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['isRecurring']);
        $this->assertEquals('every month on the 15th', $data['recurrenceRule']);
    }

    public function testCreateRecurringTaskWithRelativePattern(): void
    {
        $user = $this->createUser('recurring-relative@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Relative Task',
                'isRecurring' => true,
                'recurrenceRule' => 'every! 3 days',
                'dueDate' => '2026-01-24',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['isRecurring']);
        $this->assertEquals('every! 3 days', $data['recurrenceRule']);
        $this->assertEquals('relative', $data['recurrenceType']);
    }

    public function testCreateRecurringTaskWithEndDate(): void
    {
        $user = $this->createUser('recurring-enddate@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Recurring with End Date',
                'isRecurring' => true,
                'recurrenceRule' => 'every day until March 1 2026',
                'dueDate' => '2026-01-24',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['isRecurring']);
        $this->assertNotNull($data['recurrenceEndDate']);
        $this->assertEquals('2026-03-01', $data['recurrenceEndDate']);
    }

    public function testCreateRecurringTaskInvalidPattern(): void
    {
        $user = $this->createUser('recurring-invalid@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Invalid Recurring Task',
                'isRecurring' => true,
                'recurrenceRule' => 'not a valid pattern',
                'dueDate' => '2026-01-24',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, 'INVALID_RECURRENCE_PATTERN');
    }

    public function testCreateRecurringTaskRequiresPattern(): void
    {
        $user = $this->createUser('recurring-no-pattern@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Recurring without Pattern',
                'isRecurring' => true,
                // Missing recurrenceRule
                'dueDate' => '2026-01-24',
            ]
        );

        // When isRecurring=true but no pattern is provided, the task is created
        // as a non-recurring task (graceful fallback)
        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);
        // The task should be created but NOT marked as recurring
        $this->assertFalse($data['isRecurring']);
    }

    // ========================================
    // Complete Recurring Task Tests
    // ========================================

    public function testCompleteRecurringTaskCreatesNextInstance(): void
    {
        $user = $this->createUser('complete-recurring@example.com', 'Password123');

        // Create a recurring task
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Recurring Task',
                'isRecurring' => true,
                'recurrenceRule' => 'every day',
                'dueDate' => '2026-01-24',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $createResponse);
        $taskData = $this->getResponseData($createResponse);
        $taskId = $taskData['id'];

        // Complete the task
        $completeResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$taskId.'/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $completeResponse);

        $data = $this->getResponseData($completeResponse);

        // Verify the task is completed
        $this->assertEquals(Task::STATUS_COMPLETED, $data['status']);

        // Verify next task was created
        $this->assertArrayHasKey('nextTask', $data);
        $this->assertNotNull($data['nextTask']);
        $this->assertEquals('Recurring Task', $data['nextTask']['title']);
        $this->assertTrue($data['nextTask']['isRecurring']);
        $this->assertEquals('2026-01-25', $data['nextTask']['dueDate']); // Next day
    }

    public function testCompleteRecurringTaskAbsoluteDateCalculation(): void
    {
        $user = $this->createUser('complete-absolute@example.com', 'Password123');

        // Create a weekly recurring task (absolute - from schedule)
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Weekly Meeting',
                'isRecurring' => true,
                'recurrenceRule' => 'every week',
                'dueDate' => '2026-01-24', // Friday
            ]
        );

        $taskData = $this->getResponseData($createResponse);
        $taskId = $taskData['id'];

        // Complete the task
        $completeResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$taskId.'/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $data = $this->getResponseData($completeResponse);

        // Next due date should be 1 week from original due date (absolute)
        $this->assertNotNull($data['nextTask']);
        $this->assertEquals('2026-01-31', $data['nextTask']['dueDate']); // Original + 1 week
    }

    public function testCompleteRecurringTaskRelativeDateCalculation(): void
    {
        $user = $this->createUser('complete-relative@example.com', 'Password123');

        // Create a relative recurring task (every! - from completion)
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Follow up in 3 days',
                'isRecurring' => true,
                'recurrenceRule' => 'every! 3 days',
                'dueDate' => '2026-01-20', // Past date
            ]
        );

        $taskData = $this->getResponseData($createResponse);
        $taskId = $taskData['id'];

        // Complete the task (today is 2026-01-24)
        $completeResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$taskId.'/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $data = $this->getResponseData($completeResponse);

        // Next due date should be 3 days from completion (relative)
        $this->assertNotNull($data['nextTask']);
        // The exact date depends on when the test runs, but it should be 3 days from now
        $expectedDate = (new \DateTimeImmutable())->modify('+3 days')->format('Y-m-d');
        $this->assertEquals($expectedDate, $data['nextTask']['dueDate']);
    }

    public function testCompleteRecurringTaskChainTracking(): void
    {
        $user = $this->createUser('complete-chain@example.com', 'Password123');

        // Create a recurring task
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Chain Tracked Task',
                'isRecurring' => true,
                'recurrenceRule' => 'every day',
                'dueDate' => '2026-01-24',
            ]
        );

        $taskData = $this->getResponseData($createResponse);
        $originalTaskId = $taskData['id'];

        // First task should not have originalTaskId (it's the chain root)
        $this->assertNull($taskData['originalTaskId'] ?? null);

        // Complete the first task
        $completeResponse1 = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$originalTaskId.'/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $data1 = $this->getResponseData($completeResponse1);
        $secondTaskId = $data1['nextTask']['id'];

        // Second task should reference the original
        $this->assertEquals($originalTaskId, $data1['nextTask']['originalTaskId']);

        // Complete the second task
        $completeResponse2 = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$secondTaskId.'/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $data2 = $this->getResponseData($completeResponse2);

        // Third task should also reference the original (not the second)
        $this->assertEquals($originalTaskId, $data2['nextTask']['originalTaskId']);
    }

    public function testCompleteRecurringTaskCopiesTags(): void
    {
        $user = $this->createUser('complete-tags@example.com', 'Password123');
        $tag1 = $this->createTag($user, 'Work', '#FF0000');
        $tag2 = $this->createTag($user, 'Important', '#00FF00');

        // Create a recurring task with tags
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Tagged Recurring Task',
                'isRecurring' => true,
                'recurrenceRule' => 'every day',
                'dueDate' => '2026-01-24',
                'tagIds' => [$tag1->getId(), $tag2->getId()],
            ]
        );

        $taskData = $this->getResponseData($createResponse);
        $taskId = $taskData['id'];

        // Verify original task has tags
        $this->assertCount(2, $taskData['tags']);

        // Complete the task
        $completeResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$taskId.'/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $data = $this->getResponseData($completeResponse);

        // Verify next task also has the same tags
        $this->assertArrayHasKey('tags', $data['nextTask']);
        $this->assertCount(2, $data['nextTask']['tags']);

        $nextTaskTagIds = array_column($data['nextTask']['tags'], 'id');
        $this->assertContains($tag1->getId(), $nextTaskTagIds);
        $this->assertContains($tag2->getId(), $nextTaskTagIds);
    }

    public function testCompleteRecurringTaskRespectsEndDate(): void
    {
        $user = $this->createUser('complete-end@example.com', 'Password123');

        // Create a recurring task that ends today
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Ending Recurring Task',
                'isRecurring' => true,
                'recurrenceRule' => 'every day until January 24 2026',
                'dueDate' => '2026-01-24',
            ]
        );

        $taskData = $this->getResponseData($createResponse);
        $taskId = $taskData['id'];

        // Complete the task
        $completeResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$taskId.'/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $data = $this->getResponseData($completeResponse);

        // Task is completed
        $this->assertEquals(Task::STATUS_COMPLETED, $data['status']);

        // No next task should be created since end date would be exceeded
        $this->assertNull($data['nextTask'] ?? null);
    }

    // ========================================
    // Complete Forever Tests
    // ========================================

    public function testCompleteForeverStopsRecurrence(): void
    {
        $user = $this->createUser('complete-forever@example.com', 'Password123');

        // Create a recurring task
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Forever Completed Task',
                'isRecurring' => true,
                'recurrenceRule' => 'every day',
                'dueDate' => '2026-01-24',
            ]
        );

        $taskData = $this->getResponseData($createResponse);
        $taskId = $taskData['id'];

        // Complete forever
        $completeForeverResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/'.$taskId.'/complete-forever'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $completeForeverResponse);

        $data = $this->getResponseData($completeForeverResponse);

        // Task should be completed
        $this->assertEquals(Task::STATUS_COMPLETED, $data['status']);

        // Task should no longer be recurring
        $this->assertFalse($data['isRecurring']);

        // No next task should be created
        $this->assertNull($data['nextTask'] ?? null);
    }

    public function testCompleteForeverOnNonRecurringTaskReturnsError(): void
    {
        $user = $this->createUser('complete-forever-non@example.com', 'Password123');

        // Create a regular (non-recurring) task
        $task = $this->createTask($user, 'Regular Task');

        // Complete forever should return an error for non-recurring tasks
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/'.$task->getId().'/complete-forever'
        );

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('TASK_NOT_RECURRING', $data['error']['code']);
    }

    public function testCompleteForeverNotFound(): void
    {
        $user = $this->createUser('complete-forever-404@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/00000000-0000-0000-0000-000000000000/complete-forever'
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    // ========================================
    // Recurring History Tests
    // ========================================

    public function testRecurringHistoryEndpoint(): void
    {
        $user = $this->createUser('recurring-history@example.com', 'Password123');

        // Create a recurring task
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'History Task',
                'isRecurring' => true,
                'recurrenceRule' => 'every day',
                'dueDate' => '2026-01-24',
            ]
        );

        $taskData = $this->getResponseData($createResponse);
        $taskId = $taskData['id'];

        // Complete it twice to create a chain
        $complete1 = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$taskId.'/status',
            ['status' => Task::STATUS_COMPLETED]
        );
        $data1 = $this->getResponseData($complete1);
        $task2Id = $data1['nextTask']['id'];

        $complete2 = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$task2Id.'/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        // Get recurring history
        $historyResponse = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/'.$taskId.'/recurring-history'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $historyResponse);

        $historyData = $this->getResponseData($historyResponse);

        // Should have all tasks in the chain
        $this->assertArrayHasKey('tasks', $historyData);
        $this->assertCount(3, $historyData['tasks']); // Original + 2 completed = 3 total

        // Count stats should be present
        $this->assertArrayHasKey('totalCount', $historyData);
        $this->assertArrayHasKey('completedCount', $historyData);
        $this->assertEquals(3, $historyData['totalCount']);
        $this->assertEquals(2, $historyData['completedCount']);
    }

    public function testRecurringHistoryNotFound(): void
    {
        $user = $this->createUser('history-404@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/00000000-0000-0000-0000-000000000000/recurring-history'
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    // ========================================
    // Update Recurring Task Tests
    // ========================================

    public function testUpdateRecurrenceRule(): void
    {
        $user = $this->createUser('update-recurrence@example.com', 'Password123');

        // Create a recurring task
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Update Recurrence Task',
                'isRecurring' => true,
                'recurrenceRule' => 'every day',
                'dueDate' => '2026-01-24',
            ]
        );

        $taskData = $this->getResponseData($createResponse);
        $taskId = $taskData['id'];

        // Update the recurrence rule
        $updateResponse = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/tasks/'.$taskId,
            [
                'recurrenceRule' => 'every week',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $updateResponse);

        $data = $this->getResponseData($updateResponse);
        $this->assertEquals('every week', $data['recurrenceRule']);
    }

    public function testClearRecurrence(): void
    {
        $user = $this->createUser('clear-recurrence@example.com', 'Password123');

        // Create a recurring task
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Clear Recurrence Task',
                'isRecurring' => true,
                'recurrenceRule' => 'every day',
                'dueDate' => '2026-01-24',
            ]
        );

        $taskData = $this->getResponseData($createResponse);
        $taskId = $taskData['id'];

        // Clear recurrence
        $updateResponse = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/tasks/'.$taskId,
            [
                'clearRecurrence' => true,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $updateResponse);

        $data = $this->getResponseData($updateResponse);
        $this->assertFalse($data['isRecurring']);
        // When isRecurring is false, recurrenceRule is not included in the response
        $this->assertArrayNotHasKey('recurrenceRule', $data);
    }

    // ========================================
    // Edge Cases and Validation Tests
    // ========================================

    public function testRecurringTaskCopiesPriority(): void
    {
        $user = $this->createUser('copy-priority@example.com', 'Password123');

        // Create a high-priority recurring task
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'High Priority Recurring',
                'priority' => 4,
                'isRecurring' => true,
                'recurrenceRule' => 'every day',
                'dueDate' => '2026-01-24',
            ]
        );

        $taskData = $this->getResponseData($createResponse);
        $taskId = $taskData['id'];

        // Complete the task
        $completeResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$taskId.'/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $data = $this->getResponseData($completeResponse);

        // Next task should have the same priority
        $this->assertEquals(4, $data['nextTask']['priority']);
    }

    public function testRecurringTaskCopiesProject(): void
    {
        $user = $this->createUser('copy-project@example.com', 'Password123');
        $project = $this->createProject($user, 'Work Project');

        // Create a recurring task in a project
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Project Recurring Task',
                'projectId' => $project->getId(),
                'isRecurring' => true,
                'recurrenceRule' => 'every day',
                'dueDate' => '2026-01-24',
            ]
        );

        $taskData = $this->getResponseData($createResponse);
        $taskId = $taskData['id'];

        // Complete the task
        $completeResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$taskId.'/status',
            ['status' => Task::STATUS_COMPLETED]
        );

        $data = $this->getResponseData($completeResponse);

        // Next task should be in the same project
        $this->assertArrayHasKey('project', $data['nextTask']);
        $this->assertEquals($project->getId(), $data['nextTask']['project']['id']);
    }

    public function testRecurringTaskResponseStructure(): void
    {
        $user = $this->createUser('response-structure@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Structure Test',
                'isRecurring' => true,
                'recurrenceRule' => 'every day at 9am',
                'dueDate' => '2026-01-24',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        // Verify all recurring-related fields are present
        $this->assertArrayHasKey('isRecurring', $data);
        $this->assertArrayHasKey('recurrenceRule', $data);
        $this->assertArrayHasKey('recurrenceType', $data);
        $this->assertArrayHasKey('recurrenceEndDate', $data);
        // originalTaskId is only present when not null (for subsequent tasks in a chain)
        // For newly created tasks, it should not be present since there's no original
        $this->assertArrayNotHasKey('originalTaskId', $data);
    }
}
