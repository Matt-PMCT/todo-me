<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for authorization edge cases across API endpoints.
 *
 * Tests cross-user access attempts, ownership validation, and authentication
 * edge cases that may not be covered in individual endpoint tests.
 */
class AuthorizationEdgeCasesTest extends ApiTestCase
{
    // ========================================
    // Cross-User Access Tests (Tasks)
    // ========================================

    public function testCannotAccessOtherUserTask(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $task = $this->createTask($user1, 'User 1 Task');

        $response = $this->authenticatedApiRequest(
            $user2,
            'GET',
            '/api/v1/tasks/' . $task->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_FORBIDDEN, $response);
        $this->assertErrorCode($response, 'PERMISSION_DENIED');
    }

    public function testCannotUpdateOtherUserTask(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $task = $this->createTask($user1, 'User 1 Task');

        $response = $this->authenticatedApiRequest(
            $user2,
            'PATCH',
            '/api/v1/tasks/' . $task->getId(),
            ['title' => 'Hijacked Task']
        );

        $this->assertResponseStatusCode(Response::HTTP_FORBIDDEN, $response);
        $this->assertErrorCode($response, 'PERMISSION_DENIED');

        // Verify original task unchanged
        $this->refreshEntity($task);
        $this->assertSame('User 1 Task', $task->getTitle());
    }

    public function testCannotDeleteOtherUserTask(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $task = $this->createTask($user1, 'User 1 Task');
        $taskId = $task->getId();

        $response = $this->authenticatedApiRequest(
            $user2,
            'DELETE',
            '/api/v1/tasks/' . $taskId
        );

        $this->assertResponseStatusCode(Response::HTTP_FORBIDDEN, $response);
        $this->assertErrorCode($response, 'PERMISSION_DENIED');
    }

    public function testCannotChangeOtherUserTaskStatus(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $task = $this->createTask($user1, 'User 1 Task', status: Task::STATUS_PENDING);

        $response = $this->authenticatedApiRequest(
            $user2,
            'POST',
            '/api/v1/tasks/' . $task->getId() . '/complete'
        );

        $this->assertResponseStatusCode(Response::HTTP_FORBIDDEN, $response);

        // Verify status unchanged
        $this->refreshEntity($task);
        $this->assertSame(Task::STATUS_PENDING, $task->getStatus());
    }

    // ========================================
    // Cross-User Access Tests (Projects)
    // ========================================

    public function testCannotAccessOtherUserProject(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $project = $this->createProject($user1, 'User 1 Project');

        $response = $this->authenticatedApiRequest(
            $user2,
            'GET',
            '/api/v1/projects/' . $project->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_FORBIDDEN, $response);
        $this->assertErrorCode($response, 'PERMISSION_DENIED');
    }

    public function testCannotUpdateOtherUserProject(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $project = $this->createProject($user1, 'User 1 Project');

        $response = $this->authenticatedApiRequest(
            $user2,
            'PATCH',
            '/api/v1/projects/' . $project->getId(),
            ['name' => 'Hijacked Project']
        );

        $this->assertResponseStatusCode(Response::HTTP_FORBIDDEN, $response);

        // Verify original project unchanged
        $this->refreshEntity($project);
        $this->assertSame('User 1 Project', $project->getName());
    }

    public function testCannotDeleteOtherUserProject(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $project = $this->createProject($user1, 'User 1 Project');

        $response = $this->authenticatedApiRequest(
            $user2,
            'DELETE',
            '/api/v1/projects/' . $project->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_FORBIDDEN, $response);
    }

    public function testCannotArchiveOtherUserProject(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $project = $this->createProject($user1, 'User 1 Project');

        $response = $this->authenticatedApiRequest(
            $user2,
            'POST',
            '/api/v1/projects/' . $project->getId() . '/archive'
        );

        $this->assertResponseStatusCode(Response::HTTP_FORBIDDEN, $response);
    }

    // ========================================
    // Cross-Reference Tests
    // ========================================

    public function testCannotCreateTaskWithOtherUserProject(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $project = $this->createProject($user1, 'User 1 Project');

        $response = $this->authenticatedApiRequest(
            $user2,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Task in wrong project',
                'projectId' => $project->getId(),
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_FORBIDDEN, $response);
    }

    public function testCannotUpdateTaskWithOtherUserProject(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $task = $this->createTask($user2, 'User 2 Task');
        $project = $this->createProject($user1, 'User 1 Project');

        $response = $this->authenticatedApiRequest(
            $user2,
            'PATCH',
            '/api/v1/tasks/' . $task->getId(),
            ['projectId' => $project->getId()]
        );

        $this->assertResponseStatusCode(Response::HTTP_FORBIDDEN, $response);
    }

    public function testCannotCreateTaskWithOtherUserTag(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $tag = $this->createTag($user1, 'User 1 Tag');

        $response = $this->authenticatedApiRequest(
            $user2,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Task with wrong tag',
                'tagIds' => [$tag->getId()],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_FORBIDDEN, $response);
    }

    // ========================================
    // Authentication Edge Cases
    // ========================================

    public function testExpiredTokenRejected(): void
    {
        $user = $this->createUser('test@example.com', withToken: true);

        // Manually expire the token
        $user->setApiTokenExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->entityManager->flush();

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testMalformedTokenRejected(): void
    {
        $this->client->request(
            'GET',
            '/api/v1/tasks',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer invalid-token-format',
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testMissingAuthorizationHeaderRejected(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/tasks');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testInvalidAuthSchemeRejected(): void
    {
        $user = $this->createUser('test@example.com');

        $this->client->request(
            'GET',
            '/api/v1/tasks',
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Basic ' . base64_encode('user:pass'),
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->client->getResponse();

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Non-Existent Resource Tests
    // ========================================

    public function testAccessNonExistentTask(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/' . $this->generateUuid()
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
        $this->assertErrorCode($response, 'ENTITY_NOT_FOUND');
    }

    public function testAccessNonExistentProject(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects/' . $this->generateUuid()
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
        $this->assertErrorCode($response, 'ENTITY_NOT_FOUND');
    }

    public function testUpdateNonExistentTask(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/tasks/' . $this->generateUuid(),
            ['title' => 'Updated Title']
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    public function testDeleteNonExistentTask(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/tasks/' . $this->generateUuid()
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    // ========================================
    // Invalid UUID Tests
    // ========================================

    public function testInvalidTaskIdFormat(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/not-a-valid-uuid'
        );

        // Should return 404 since the ID format is invalid
        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_NOT_FOUND, Response::HTTP_BAD_REQUEST]
        );
    }

    public function testInvalidProjectIdFormat(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects/not-a-valid-uuid'
        );

        $this->assertContains(
            $response->getStatusCode(),
            [Response::HTTP_NOT_FOUND, Response::HTTP_BAD_REQUEST]
        );
    }

    // ========================================
    // Empty/Malformed Request Body Tests
    // ========================================

    public function testCreateTaskWithEmptyBody(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            []
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testCreateProjectWithEmptyBody(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects',
            []
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    // ========================================
    // Undo Token Edge Cases
    // ========================================

    public function testUndoWithInvalidToken(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks/undo',
            ['token' => 'invalid-undo-token']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testCannotUseOtherUserUndoToken(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        // Create and delete a task to get an undo token
        $task = $this->createTask($user1, 'Task to delete');

        $deleteResponse = $this->authenticatedApiRequest(
            $user1,
            'DELETE',
            '/api/v1/tasks/' . $task->getId()
        );

        $deleteData = $this->assertJsonResponse($deleteResponse);
        $undoToken = $deleteData['data']['undoToken'] ?? null;

        if ($undoToken) {
            // User 2 tries to use User 1's undo token
            $response = $this->authenticatedApiRequest(
                $user2,
                'POST',
                '/api/v1/tasks/undo',
                ['token' => $undoToken]
            );

            $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        } else {
            $this->markTestSkipped('Undo token not returned from delete');
        }
    }
}
