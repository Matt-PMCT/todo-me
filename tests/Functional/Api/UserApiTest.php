<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the User API endpoints (GDPR compliance).
 */
class UserApiTest extends ApiTestCase
{
    // ========================================
    // Data Export Tests
    // ========================================

    public function testExportDataReturnsUserData(): void
    {
        $user = $this->createUser('export@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/users/me/export'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->assertSuccessResponse($response);

        // Verify user data structure
        $this->assertArrayHasKey('user', $data['data']);
        $this->assertArrayHasKey('projects', $data['data']);
        $this->assertArrayHasKey('tasks', $data['data']);
        $this->assertArrayHasKey('tags', $data['data']);
        $this->assertArrayHasKey('savedFilters', $data['data']);
        $this->assertArrayHasKey('exportedAt', $data['data']);

        // Verify user info
        $this->assertEquals($user->getId(), $data['data']['user']['id']);
        $this->assertEquals('export@example.com', $data['data']['user']['email']);
    }

    public function testExportDataIncludesProjects(): void
    {
        $user = $this->createUser('exportproj@example.com', 'Password123');
        $project = $this->createProject($user, 'Test Project', 'Project description');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/users/me/export'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['projects']);
        $this->assertEquals($project->getId(), $data['projects'][0]['id']);
        $this->assertEquals('Test Project', $data['projects'][0]['name']);
    }

    public function testExportDataIncludesTasks(): void
    {
        $user = $this->createUser('exporttask@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task', 'Task description');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/users/me/export'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['tasks']);
        $this->assertEquals($task->getId(), $data['tasks'][0]['id']);
        $this->assertEquals('Test Task', $data['tasks'][0]['title']);
    }

    public function testExportDataIncludesTags(): void
    {
        $user = $this->createUser('exporttag@example.com', 'Password123');
        $tag = $this->createTag($user, 'Test Tag', '#FF0000');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/users/me/export'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['tags']);
        $this->assertEquals($tag->getId(), $data['tags'][0]['id']);
        $this->assertEquals('Test Tag', $data['tags'][0]['name']);
    }

    public function testExportDataRequiresAuthentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/users/me/export');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Account Deletion Tests
    // ========================================

    public function testDeleteAccountWithCorrectPassword(): void
    {
        $user = $this->createUser('delete@example.com', 'Password123');
        $userId = $user->getId();

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/users/me',
            ['password' => 'Password123']
        );

        $this->assertResponseStatusCode(Response::HTTP_NO_CONTENT, $response);

        // Verify user is deleted
        $this->entityManager->clear();
        $deletedUser = $this->entityManager->getRepository(\App\Entity\User::class)->find($userId);
        $this->assertNull($deletedUser);
    }

    public function testDeleteAccountWithWrongPassword(): void
    {
        $user = $this->createUser('deletewrong@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/users/me',
            ['password' => 'WrongPassword']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
        $this->assertErrorCode($response, 'INVALID_PASSWORD');
    }

    public function testDeleteAccountWithoutPassword(): void
    {
        $user = $this->createUser('deletenopw@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/users/me',
            []
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testDeleteAccountWithEmptyPassword(): void
    {
        $user = $this->createUser('deleteempty@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/users/me',
            ['password' => '']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testDeleteAccountCascadesProjects(): void
    {
        $user = $this->createUser('cascadeproj@example.com', 'Password123');
        $project = $this->createProject($user, 'Test Project');
        $projectId = $project->getId();

        $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/users/me',
            ['password' => 'Password123']
        );

        // Verify project is deleted
        $this->entityManager->clear();
        $deletedProject = $this->entityManager->getRepository(\App\Entity\Project::class)->find($projectId);
        $this->assertNull($deletedProject);
    }

    public function testDeleteAccountCascadesTasks(): void
    {
        $user = $this->createUser('cascadetask@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task');
        $taskId = $task->getId();

        $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/users/me',
            ['password' => 'Password123']
        );

        // Verify task is deleted
        $this->entityManager->clear();
        $deletedTask = $this->entityManager->getRepository(\App\Entity\Task::class)->find($taskId);
        $this->assertNull($deletedTask);
    }

    public function testDeleteAccountCascadesTags(): void
    {
        $user = $this->createUser('cascadetag@example.com', 'Password123');
        $tag = $this->createTag($user, 'Test Tag');
        $tagId = $tag->getId();

        $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/users/me',
            ['password' => 'Password123']
        );

        // Verify tag is deleted
        $this->entityManager->clear();
        $deletedTag = $this->entityManager->getRepository(\App\Entity\Tag::class)->find($tagId);
        $this->assertNull($deletedTag);
    }

    public function testDeleteAccountRequiresAuthentication(): void
    {
        $response = $this->apiRequest(
            'DELETE',
            '/api/v1/users/me',
            ['password' => 'Password123']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Edge Cases
    // ========================================

    public function testExportDataWithNoContent(): void
    {
        $user = $this->createUser('emptyexport@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/users/me/export'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEmpty($data['projects']);
        $this->assertEmpty($data['tasks']);
        $this->assertEmpty($data['tags']);
        $this->assertEmpty($data['savedFilters']);
    }
}
