<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for natural language task creation and reschedule endpoints.
 *
 * Tests:
 * - Natural language task creation with date
 * - Natural language task creation with project
 * - Natural language task creation with tags (including auto-creation)
 * - Natural language task creation with priority
 * - Natural language task creation with all components
 * - Natural language task creation with only title
 * - Natural language task creation with non-existent project (task created, warning returned)
 * - Natural language task creation with invalid priority (uses default, warning returned)
 * - Reschedule with ISO date
 * - Reschedule with natural language (tomorrow, next Monday, etc.)
 * - Reschedule with invalid date (error)
 * - Reschedule returns undo token
 * - Undo reschedule restores original date
 * - Authentication required
 * - Multi-tenant isolation
 */
class NaturalLanguageTaskTest extends ApiTestCase
{
    // ========================================
    // Natural Language Task Creation Tests
    // ========================================

    public function testCreateTaskWithNaturalLanguageDate(): void
    {
        $user = $this->createUser('nl-date@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Buy groceries tomorrow']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('Buy groceries', $data['title']);
        $this->assertNotNull($data['dueDate']);
        // Tomorrow's date
        $tomorrow = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $this->assertEquals($tomorrow, $data['dueDate']);
    }

    public function testCreateTaskWithNaturalLanguageProject(): void
    {
        $user = $this->createUser('nl-project@example.com', 'Password123');
        $project = $this->createProject($user, 'Work');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Review proposal #work']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Review proposal', $data['title']);
        $this->assertArrayHasKey('project', $data);
        $this->assertNotNull($data['project']);
        $this->assertEquals($project->getId(), $data['project']['id']);
        $this->assertEquals('Work', $data['project']['name']);
    }

    public function testCreateTaskWithNaturalLanguageExistingTags(): void
    {
        $user = $this->createUser('nl-existing-tags@example.com', 'Password123');
        $tag = $this->createTag($user, 'urgent', '#FF0000');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Fix critical bug @urgent']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Fix critical bug', $data['title']);
        $this->assertArrayHasKey('tags', $data);
        $this->assertCount(1, $data['tags']);
        $this->assertEquals($tag->getId(), $data['tags'][0]['id']);
        $this->assertEquals('urgent', $data['tags'][0]['name']);
    }

    public function testCreateTaskWithNaturalLanguageNewTagAutoCreation(): void
    {
        $user = $this->createUser('nl-new-tags@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Setup project @newtag']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Setup project', $data['title']);
        $this->assertArrayHasKey('tags', $data);
        $this->assertCount(1, $data['tags']);
        $this->assertEquals('newtag', $data['tags'][0]['name']);
        // Verify tag was actually created
        $this->assertNotEmpty($data['tags'][0]['id']);
    }

    public function testCreateTaskWithNaturalLanguageMultipleTags(): void
    {
        $user = $this->createUser('nl-multi-tags@example.com', 'Password123');
        $this->createTag($user, 'urgent', '#FF0000');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Deploy feature @urgent @work @important']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Deploy feature', $data['title']);
        $this->assertArrayHasKey('tags', $data);
        $this->assertCount(3, $data['tags']);
    }

    public function testCreateTaskWithNaturalLanguagePriority(): void
    {
        $user = $this->createUser('nl-priority@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Urgent meeting p4']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Urgent meeting', $data['title']);
        $this->assertEquals(4, $data['priority']);
    }

    public function testCreateTaskWithNaturalLanguageAllComponents(): void
    {
        $user = $this->createUser('nl-all@example.com', 'Password123');
        $project = $this->createProject($user, 'Work');
        $this->createTag($user, 'important', '#FF0000');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Review proposal #work @important tomorrow p3']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Review proposal', $data['title']);
        $this->assertNotNull($data['project']);
        $this->assertEquals($project->getId(), $data['project']['id']);
        $this->assertCount(1, $data['tags']);
        $this->assertEquals('important', $data['tags'][0]['name']);
        $this->assertEquals(3, $data['priority']);
        $tomorrow = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $this->assertEquals($tomorrow, $data['dueDate']);
    }

    public function testCreateTaskWithNaturalLanguageOnlyTitle(): void
    {
        $user = $this->createUser('nl-title-only@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Simple task without metadata']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Simple task without metadata', $data['title']);
        $this->assertNull($data['dueDate']);
        $this->assertNull($data['project']);
        $this->assertEmpty($data['tags']);
        $this->assertEquals(Task::PRIORITY_DEFAULT, $data['priority']);
    }

    public function testCreateTaskWithNonExistentProjectReturnsWarning(): void
    {
        $user = $this->createUser('nl-no-project@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Task #nonexistent']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        // Task is still created with the hashtag in the title
        $this->assertStringContainsString('Task', $data['title']);
        $this->assertNull($data['project']);

        // Check for parse result warnings
        $this->assertArrayHasKey('parseResult', $data);
        $this->assertArrayHasKey('warnings', $data['parseResult']);
        $this->assertNotEmpty($data['parseResult']['warnings']);
        $this->assertStringContainsString('not found', $data['parseResult']['warnings'][0]);
    }

    public function testCreateTaskWithInvalidPriorityUsesDefault(): void
    {
        $user = $this->createUser('nl-invalid-priority@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Task with p7']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        // Priority defaults since p7 is invalid
        $this->assertEquals(Task::PRIORITY_DEFAULT, $data['priority']);

        // Check for parse result warnings
        $this->assertArrayHasKey('parseResult', $data);
        $this->assertArrayHasKey('warnings', $data['parseResult']);
        $this->assertNotEmpty($data['parseResult']['warnings']);
        $this->assertStringContainsString('priority', strtolower($data['parseResult']['warnings'][0]));
    }

    public function testCreateTaskNaturalLanguageRequiresInputText(): void
    {
        $user = $this->createUser('nl-missing-input@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['title' => 'Wrong field']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testCreateTaskNaturalLanguageEmptyTitleFails(): void
    {
        $user = $this->createUser('nl-empty-title@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'tomorrow p3']
        );

        // Should fail because after parsing, title is empty
        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    public function testCreateTaskNaturalLanguageReturnsHighlights(): void
    {
        $user = $this->createUser('nl-highlights@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Buy milk tomorrow']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('parseResult', $data);
        $this->assertArrayHasKey('highlights', $data['parseResult']);
        $this->assertNotEmpty($data['parseResult']['highlights']);

        // Should have a date highlight
        $dateHighlight = null;
        foreach ($data['parseResult']['highlights'] as $highlight) {
            if ($highlight['type'] === 'date') {
                $dateHighlight = $highlight;

                break;
            }
        }
        $this->assertNotNull($dateHighlight);
        $this->assertEquals('tomorrow', $dateHighlight['text']);
        $this->assertTrue($dateHighlight['valid']);
    }

    // ========================================
    // Reschedule Endpoint Tests
    // ========================================

    public function testRescheduleWithIsoDate(): void
    {
        $user = $this->createUser('reschedule-iso@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task', null, Task::STATUS_PENDING, 2, null, new \DateTimeImmutable('2026-01-20'));

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$task->getId().'/reschedule',
            ['due_date' => '2026-02-15']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('2026-02-15', $data['dueDate']);
    }

    public function testRescheduleWithNaturalLanguageTomorrow(): void
    {
        $user = $this->createUser('reschedule-tomorrow@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$task->getId().'/reschedule',
            ['due_date' => 'tomorrow']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $tomorrow = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $this->assertEquals($tomorrow, $data['dueDate']);
    }

    public function testRescheduleWithNaturalLanguageNextMonday(): void
    {
        $user = $this->createUser('reschedule-monday@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$task->getId().'/reschedule',
            ['due_date' => 'next Monday']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertNotNull($data['dueDate']);
        // Verify it's a Monday
        $date = new \DateTimeImmutable($data['dueDate']);
        $this->assertEquals('Monday', $date->format('l'));
    }

    public function testRescheduleWithInvalidDateFails(): void
    {
        $user = $this->createUser('reschedule-invalid@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$task->getId().'/reschedule',
            ['due_date' => 'not a valid date at all xyz']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testRescheduleMissingDueDateFails(): void
    {
        $user = $this->createUser('reschedule-missing@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$task->getId().'/reschedule',
            []
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    public function testRescheduleReturnsUndoToken(): void
    {
        $user = $this->createUser('reschedule-undo@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task', null, Task::STATUS_PENDING, 2, null, new \DateTimeImmutable('2026-01-20'));

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$task->getId().'/reschedule',
            ['due_date' => 'tomorrow']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('undoToken', $data);
        $this->assertNotEmpty($data['undoToken']);
    }

    public function testUndoRescheduleRestoresOriginalDate(): void
    {
        $user = $this->createUser('undo-reschedule@example.com', 'Password123');
        $originalDate = new \DateTimeImmutable('2026-01-20');
        $task = $this->createTask($user, 'Test Task', null, Task::STATUS_PENDING, 2, null, $originalDate);

        // Reschedule the task
        $rescheduleResponse = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$task->getId().'/reschedule',
            ['due_date' => 'tomorrow']
        );

        $rescheduleData = $this->getResponseData($rescheduleResponse);
        $undoToken = $rescheduleData['undoToken'];

        // Verify date was changed
        $this->assertNotEquals('2026-01-20', $rescheduleData['dueDate']);

        // Undo the reschedule
        $undoResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/undo/'.$undoToken
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $undoResponse);

        $undoData = $this->getResponseData($undoResponse);

        // Original date should be restored
        $this->assertEquals('2026-01-20', $undoData['dueDate']);
    }

    public function testRescheduleTaskNotFound(): void
    {
        $user = $this->createUser('reschedule-notfound@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/00000000-0000-0000-0000-000000000000/reschedule',
            ['due_date' => 'tomorrow']
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    // ========================================
    // Authentication Tests
    // ========================================

    public function testNaturalLanguageTaskCreationRequiresAuthentication(): void
    {
        $response = $this->apiRequest(
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Test task tomorrow']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testRescheduleRequiresAuthentication(): void
    {
        $user = $this->createUser('auth-reschedule@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task');

        $response = $this->apiRequest(
            'PATCH',
            '/api/v1/tasks/'.$task->getId().'/reschedule',
            ['due_date' => 'tomorrow']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Multi-tenant Isolation Tests
    // ========================================

    public function testNaturalLanguageTaskCreationWithOtherUsersProject(): void
    {
        $user1 = $this->createUser('user1-nl@example.com', 'Password123');
        $user2 = $this->createUser('user2-nl@example.com', 'Password123');
        $this->createProject($user2, 'OtherProject');

        // User1 tries to use user2's project
        $response = $this->authenticatedApiRequest(
            $user1,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Task #OtherProject']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        // Project should not be assigned (not found for user1)
        $this->assertNull($data['project']);
        // Warning should be returned
        $this->assertNotEmpty($data['parseResult']['warnings']);
    }

    public function testRescheduleOtherUsersTaskFails(): void
    {
        $user1 = $this->createUser('user1-reschedule@example.com', 'Password123');
        $user2 = $this->createUser('user2-reschedule@example.com', 'Password123');
        $task = $this->createTask($user2, 'User 2 Task');

        $response = $this->authenticatedApiRequest(
            $user1,
            'PATCH',
            '/api/v1/tasks/'.$task->getId().'/reschedule',
            ['due_date' => 'tomorrow']
        );

        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_NOT_FOUND,
            Response::HTTP_FORBIDDEN,
        ]);
    }

    public function testNaturalLanguageTaskCreationOnlySeesOwnTags(): void
    {
        $user1 = $this->createUser('user1-tags@example.com', 'Password123');
        $user2 = $this->createUser('user2-tags@example.com', 'Password123');
        $this->createTag($user2, 'othertag', '#FF0000');

        // User1 references user2's tag - should create a new tag for user1
        $response = $this->authenticatedApiRequest(
            $user1,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Task @othertag']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        // A new tag should be created for user1
        $this->assertCount(1, $data['tags']);
        $this->assertEquals('othertag', $data['tags'][0]['name']);
        // The ID should be different from user2's tag (new tag created)
    }

    // ========================================
    // Edge Cases
    // ========================================

    public function testNaturalLanguageWithCaseInsensitiveProject(): void
    {
        $user = $this->createUser('nl-case@example.com', 'Password123');
        $project = $this->createProject($user, 'MyProject');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks?parse_natural_language=true',
            ['input_text' => 'Task #myproject'] // lowercase
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertNotNull($data['project']);
        $this->assertEquals($project->getId(), $data['project']['id']);
    }

    public function testRescheduleWithRelativeDate(): void
    {
        $user = $this->createUser('reschedule-relative@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$task->getId().'/reschedule',
            ['due_date' => 'in 3 days']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $expectedDate = (new \DateTimeImmutable('+3 days'))->format('Y-m-d');
        $this->assertEquals($expectedDate, $data['dueDate']);
    }

    public function testReschedulePreservesOtherTaskFields(): void
    {
        $user = $this->createUser('reschedule-preserve@example.com', 'Password123');
        $project = $this->createProject($user, 'Test Project');
        $tag = $this->createTag($user, 'important', '#FF0000');
        $task = $this->createTask($user, 'Important Task', 'Description here', Task::STATUS_IN_PROGRESS, 4, $project, new \DateTimeImmutable('2026-01-20'));
        $task->addTag($tag);
        $this->entityManager->flush();

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/'.$task->getId().'/reschedule',
            ['due_date' => '2026-03-15']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Verify date changed
        $this->assertEquals('2026-03-15', $data['dueDate']);

        // Verify other fields preserved
        $this->assertEquals('Important Task', $data['title']);
        $this->assertEquals('Description here', $data['description']);
        $this->assertEquals(Task::STATUS_IN_PROGRESS, $data['status']);
        $this->assertEquals(4, $data['priority']);
        $this->assertEquals($project->getId(), $data['project']['id']);
        $this->assertCount(1, $data['tags']);
    }
}
