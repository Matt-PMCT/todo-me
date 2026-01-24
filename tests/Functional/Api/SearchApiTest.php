<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the Search API endpoint.
 *
 * Tests:
 * - Search all entity types
 * - Search by specific type (tasks, projects, tags)
 * - Pagination
 * - Empty results
 * - Validation errors
 * - Authentication required
 */
class SearchApiTest extends ApiTestCase
{
    // ========================================
    // Search All Tests
    // ========================================

    public function testSearchAllReturnsMatchingEntities(): void
    {
        $user = $this->createUser('search-all@example.com', 'Password123');

        // Create test data
        $this->createTask($user, 'Meeting with team');
        $this->createTask($user, 'Code review session');
        $project = $this->createProject($user, 'Meeting Notes Project');
        $this->createTag($user, 'meeting');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/search?q=meeting'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->assertSuccessResponse($response);

        // Should have tasks, projects, and tags sections
        $this->assertArrayHasKey('tasks', $data['data']);
        $this->assertArrayHasKey('projects', $data['data']);
        $this->assertArrayHasKey('tags', $data['data']);
        $this->assertArrayHasKey('meta', $data['data']);

        // Should find the matching task
        $this->assertCount(1, $data['data']['tasks']);
        $this->assertEquals('Meeting with team', $data['data']['tasks'][0]['title']);

        // Should find the matching project
        $this->assertCount(1, $data['data']['projects']);
        $this->assertEquals('Meeting Notes Project', $data['data']['projects'][0]['name']);

        // Should find the matching tag
        $this->assertCount(1, $data['data']['tags']);
        $this->assertEquals('meeting', $data['data']['tags'][0]['name']);

        // Meta should have counts
        $this->assertArrayHasKey('counts', $data['data']['meta']);
        $this->assertEquals(1, $data['data']['meta']['counts']['tasks']);
        $this->assertEquals(1, $data['data']['meta']['counts']['projects']);
        $this->assertEquals(1, $data['data']['meta']['counts']['tags']);
    }

    public function testSearchTasksOnly(): void
    {
        $user = $this->createUser('search-tasks@example.com', 'Password123');

        $this->createTask($user, 'Important task');
        $this->createProject($user, 'Important project');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/search?q=important&type=tasks'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should only have task results
        $this->assertCount(1, $data['tasks']);
        $this->assertEmpty($data['projects']);
        $this->assertEmpty($data['tags']);
        $this->assertEquals('Important task', $data['tasks'][0]['title']);
    }

    public function testSearchProjectsOnly(): void
    {
        $user = $this->createUser('search-projects@example.com', 'Password123');

        $this->createTask($user, 'Work task');
        $this->createProject($user, 'Work project');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/search?q=work&type=projects'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should only have project results
        $this->assertEmpty($data['tasks']);
        $this->assertCount(1, $data['projects']);
        $this->assertEmpty($data['tags']);
        $this->assertEquals('Work project', $data['projects'][0]['name']);
    }

    public function testSearchTagsOnly(): void
    {
        $user = $this->createUser('search-tags@example.com', 'Password123');

        $this->createTask($user, 'Home task');
        $this->createTag($user, 'home');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/search?q=home&type=tags'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should only have tag results
        $this->assertEmpty($data['tasks']);
        $this->assertEmpty($data['projects']);
        $this->assertCount(1, $data['tags']);
        $this->assertEquals('home', $data['tags'][0]['name']);
    }

    // ========================================
    // Pagination Tests
    // ========================================

    public function testSearchPagination(): void
    {
        $user = $this->createUser('search-pagination@example.com', 'Password123');

        // Create multiple tasks
        for ($i = 1; $i <= 15; $i++) {
            $this->createTask($user, "Test task $i");
        }

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/search?q=test&type=tasks&limit=5&page=1'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(5, $data['tasks']);
        $this->assertEquals(15, $data['meta']['counts']['tasks']);
        $this->assertEquals(1, $data['meta']['page']);
        $this->assertEquals(5, $data['meta']['limit']);
        $this->assertTrue($data['meta']['hasNextPage']);
        $this->assertFalse($data['meta']['hasPreviousPage']);
    }

    // ========================================
    // Empty Results Tests
    // ========================================

    public function testSearchNoResults(): void
    {
        $user = $this->createUser('search-empty@example.com', 'Password123');

        $this->createTask($user, 'Unrelated task');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/search?q=nonexistent'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEmpty($data['tasks']);
        $this->assertEmpty($data['projects']);
        $this->assertEmpty($data['tags']);
        $this->assertEquals(0, $data['meta']['total']);
    }

    // ========================================
    // Task Details Tests
    // ========================================

    public function testSearchTaskIncludesRelevantFields(): void
    {
        $user = $this->createUser('search-details@example.com', 'Password123');

        $project = $this->createProject($user, 'My Project');
        $this->createTask(
            $user,
            'Detailed task',
            'Task description here',
            Task::STATUS_IN_PROGRESS,
            3,
            $project,
            new \DateTimeImmutable('2026-02-15')
        );

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/search?q=detailed&type=tasks'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['tasks']);

        $task = $data['tasks'][0];
        $this->assertArrayHasKey('id', $task);
        $this->assertArrayHasKey('type', $task);
        $this->assertArrayHasKey('title', $task);
        $this->assertArrayHasKey('description', $task);
        $this->assertArrayHasKey('status', $task);
        $this->assertArrayHasKey('priority', $task);
        $this->assertArrayHasKey('dueDate', $task);
        $this->assertArrayHasKey('projectId', $task);
        $this->assertArrayHasKey('projectName', $task);

        $this->assertEquals('task', $task['type']);
        $this->assertEquals('Detailed task', $task['title']);
        $this->assertEquals('Task description here', $task['description']);
        $this->assertEquals(Task::STATUS_IN_PROGRESS, $task['status']);
        $this->assertEquals(3, $task['priority']);
        $this->assertEquals('2026-02-15', $task['dueDate']);
        $this->assertEquals($project->getId(), $task['projectId']);
        $this->assertEquals('My Project', $task['projectName']);
    }

    // ========================================
    // Validation Tests
    // ========================================

    public function testSearchRequiresQuery(): void
    {
        $user = $this->createUser('search-no-query@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/search'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testSearchRejectsInvalidType(): void
    {
        $user = $this->createUser('search-invalid@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/search?q=test&type=invalid'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testSearchQueryMinLength(): void
    {
        $user = $this->createUser('search-minlen@example.com', 'Password123');

        // Single character query should fail (minimum is 2)
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/search?q=a'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');

        // Two character query should succeed
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/search?q=ab'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
    }

    // ========================================
    // Authentication Tests
    // ========================================

    public function testSearchRequiresAuthentication(): void
    {
        $response = $this->apiRequest(
            'GET',
            '/api/v1/search?q=test'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Isolation Tests
    // ========================================

    public function testSearchDoesNotReturnOtherUsersData(): void
    {
        $user1 = $this->createUser('search-user1@example.com', 'Password123');
        $user2 = $this->createUser('search-user2@example.com', 'Password123');

        $this->createTask($user1, 'Secret task');
        $this->createProject($user1, 'Secret project');
        $this->createTag($user1, 'secret');

        $this->createTask($user2, 'Public task');

        // User2 searches for 'secret'
        $response = $this->authenticatedApiRequest(
            $user2,
            'GET',
            '/api/v1/search?q=secret'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should not find user1's data
        $this->assertEmpty($data['tasks']);
        $this->assertEmpty($data['projects']);
        $this->assertEmpty($data['tags']);
    }

    // ========================================
    // Case Insensitivity Tests
    // ========================================

    public function testSearchIsCaseInsensitive(): void
    {
        $user = $this->createUser('search-case@example.com', 'Password123');

        $this->createTask($user, 'UPPERCASE task');
        $this->createProject($user, 'MixedCase Project');
        $this->createTag($user, 'lowercase');

        // Search with different case
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/search?q=MIXEDCASE'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['projects']);
        $this->assertEquals('MixedCase Project', $data['projects'][0]['name']);
    }
}
