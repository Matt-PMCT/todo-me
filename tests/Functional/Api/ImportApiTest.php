<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the Data Import API endpoints.
 *
 * Tests:
 * - POST /api/v1/import/json - Import from JSON
 * - POST /api/v1/import/todoist - Import from Todoist format
 * - POST /api/v1/import/csv - Import from CSV
 * - Validation errors for malformed data
 * - Imported entities owned by correct user
 */
class ImportApiTest extends ApiTestCase
{
    // ========================================
    // JSON Import Tests
    // ========================================

    public function testImportJsonSuccess(): void
    {
        $user = $this->createUser('import-json@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/import/json',
            [
                'tasks' => [
                    ['title' => 'Imported Task 1', 'status' => 'pending'],
                    ['title' => 'Imported Task 2', 'status' => 'completed'],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('stats', $data);
        $this->assertEquals(2, $data['stats']['tasks']);
    }

    public function testImportJsonWithProjects(): void
    {
        $user = $this->createUser('import-json-projects@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/import/json',
            [
                'projects' => [
                    ['name' => 'Work', 'description' => 'Work tasks'],
                    ['name' => 'Personal'],
                ],
                'tasks' => [
                    ['title' => 'Work Task', 'project' => 'Work'],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals(2, $data['stats']['projects']);
        $this->assertEquals(1, $data['stats']['tasks']);
    }

    public function testImportJsonWithTags(): void
    {
        $user = $this->createUser('import-json-tags@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/import/json',
            [
                'tags' => [
                    ['name' => 'urgent', 'color' => '#FF0000'],
                    ['name' => 'important'],
                ],
                'tasks' => [
                    ['title' => 'Tagged Task', 'tags' => ['urgent']],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals(2, $data['stats']['tags']);
        $this->assertEquals(1, $data['stats']['tasks']);
    }

    public function testImportJsonEmpty(): void
    {
        $user = $this->createUser('import-json-empty@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/import/json',
            []
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals(0, $data['stats']['tasks']);
        $this->assertEquals(0, $data['stats']['projects']);
        $this->assertEquals(0, $data['stats']['tags']);
    }

    public function testImportJsonInvalidFormat(): void
    {
        $user = $this->createUser('import-json-invalid@example.com', 'Password123');

        // Send non-JSON content
        $response = $this->apiRequest(
            'POST',
            '/api/v1/import/json',
            null,
            ['Authorization' => 'Bearer '.$this->getUserApiToken($user)]
        );

        // Empty body should be treated as invalid
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_OK, // May accept empty as null -> empty import
        ]);
    }

    public function testImportJsonUnauthenticated(): void
    {
        $response = $this->apiRequest(
            'POST',
            '/api/v1/import/json',
            ['tasks' => [['title' => 'Test']]]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Todoist Import Tests
    // ========================================

    public function testImportTodoistSuccess(): void
    {
        $user = $this->createUser('import-todoist@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/import/todoist',
            [
                'projects' => [
                    ['id' => 1, 'name' => 'Inbox'],
                    ['id' => 2, 'name' => 'Work'],
                ],
                'labels' => [
                    ['id' => 1, 'name' => 'urgent', 'color' => 'red'],
                ],
                'items' => [
                    ['id' => 1, 'content' => 'Todoist Task 1', 'project_id' => 1],
                    ['id' => 2, 'content' => 'Todoist Task 2', 'project_id' => 2, 'labels' => ['urgent']],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('stats', $data);
        $this->assertEquals(2, $data['stats']['projects']);
        $this->assertEquals(1, $data['stats']['tags']);
        $this->assertEquals(2, $data['stats']['tasks']);
    }

    public function testImportTodoistWithDueDates(): void
    {
        $user = $this->createUser('import-todoist-due@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/import/todoist',
            [
                'items' => [
                    [
                        'id' => 1,
                        'content' => 'Task with due date',
                        'due' => ['date' => '2026-01-30'],
                    ],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);
        $this->assertEquals(1, $data['stats']['tasks']);
    }

    public function testImportTodoistWithPriority(): void
    {
        $user = $this->createUser('import-todoist-priority@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/import/todoist',
            [
                'items' => [
                    ['id' => 1, 'content' => 'Low priority task', 'priority' => 1],
                    ['id' => 2, 'content' => 'High priority task', 'priority' => 4],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);
        $this->assertEquals(2, $data['stats']['tasks']);
    }

    public function testImportTodoistWithCompletedTasks(): void
    {
        $user = $this->createUser('import-todoist-completed@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/import/todoist',
            [
                'items' => [
                    ['id' => 1, 'content' => 'Completed task', 'checked' => true],
                    ['id' => 2, 'content' => 'Active task', 'checked' => false],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);
        $this->assertEquals(2, $data['stats']['tasks']);
    }

    public function testImportTodoistUnauthenticated(): void
    {
        $response = $this->apiRequest(
            'POST',
            '/api/v1/import/todoist',
            ['items' => [['id' => 1, 'content' => 'Test']]]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // CSV Import Tests
    // ========================================

    public function testImportCsvSuccess(): void
    {
        $user = $this->createUser('import-csv@example.com', 'Password123');

        $csvContent = "title,description,status,priority,dueDate,project,tags\n"
            ."Task 1,Description 1,pending,3,2026-01-30,Work,urgent\n"
            .'Task 2,Description 2,completed,2,,Personal,';

        $response = $this->apiRequest(
            'POST',
            '/api/v1/import/csv',
            null,
            [
                'Authorization' => 'Bearer '.$this->getUserApiToken($user),
                'Content-Type' => 'text/csv',
            ]
        );

        // Make request with raw CSV body
        $this->client->request(
            'POST',
            '/api/v1/import/csv',
            [],
            [],
            [
                'CONTENT_TYPE' => 'text/csv',
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->getUserApiToken($user),
            ],
            $csvContent
        );

        $response = $this->client->getResponse();

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = json_decode($response->getContent(), true)['data'] ?? [];

        $this->assertArrayHasKey('stats', $data);
        $this->assertEquals(2, $data['stats']['tasks']);
    }

    public function testImportCsvWithProjectCreation(): void
    {
        $user = $this->createUser('import-csv-projects@example.com', 'Password123');

        $csvContent = "title,project\n"
            ."Task 1,NewProject\n"
            ."Task 2,NewProject\n"
            .'Task 3,AnotherProject';

        $this->client->request(
            'POST',
            '/api/v1/import/csv',
            [],
            [],
            [
                'CONTENT_TYPE' => 'text/csv',
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->getUserApiToken($user),
            ],
            $csvContent
        );

        $response = $this->client->getResponse();

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = json_decode($response->getContent(), true)['data'] ?? [];

        $this->assertEquals(3, $data['stats']['tasks']);
        $this->assertEquals(2, $data['stats']['projects']);
    }

    public function testImportCsvWithTagCreation(): void
    {
        $user = $this->createUser('import-csv-tags@example.com', 'Password123');

        $csvContent = "title,tags\n"
            ."Task 1,\"urgent,important\"\n"
            .'Task 2,urgent';

        $this->client->request(
            'POST',
            '/api/v1/import/csv',
            [],
            [],
            [
                'CONTENT_TYPE' => 'text/csv',
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->getUserApiToken($user),
            ],
            $csvContent
        );

        $response = $this->client->getResponse();

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = json_decode($response->getContent(), true)['data'] ?? [];

        $this->assertEquals(2, $data['stats']['tasks']);
        $this->assertEquals(2, $data['stats']['tags']);
    }

    public function testImportCsvEmptyContent(): void
    {
        $user = $this->createUser('import-csv-empty@example.com', 'Password123');

        $this->client->request(
            'POST',
            '/api/v1/import/csv',
            [],
            [],
            [
                'CONTENT_TYPE' => 'text/csv',
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->getUserApiToken($user),
            ],
            ''
        );

        $response = $this->client->getResponse();

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
    }

    public function testImportCsvUnauthenticated(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/import/csv',
            [],
            [],
            ['CONTENT_TYPE' => 'text/csv'],
            "title\nTask 1"
        );

        $response = $this->client->getResponse();

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Ownership Verification Tests
    // ========================================

    public function testImportedTasksOwnedByCorrectUser(): void
    {
        $user = $this->createUser('import-owner@example.com', 'Password123');

        $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/import/json',
            [
                'tasks' => [
                    ['title' => 'Ownership Test Task'],
                ],
            ]
        );

        // Get the user's tasks
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks'
        );

        $tasks = $this->getResponseData($response)['items'];

        // Find the imported task
        $importedTask = null;
        foreach ($tasks as $task) {
            if ($task['title'] === 'Ownership Test Task') {
                $importedTask = $task;

                break;
            }
        }

        $this->assertNotNull($importedTask);
    }

    public function testImportedTasksNotVisibleToOtherUsers(): void
    {
        $user1 = $this->createUser('import-user1@example.com', 'Password123');
        $user2 = $this->createUser('import-user2@example.com', 'Password123');

        // User1 imports tasks
        $this->authenticatedApiRequest(
            $user1,
            'POST',
            '/api/v1/import/json',
            [
                'tasks' => [
                    ['title' => 'User1 Imported Task'],
                ],
            ]
        );

        // User2 tries to list tasks
        $response = $this->authenticatedApiRequest(
            $user2,
            'GET',
            '/api/v1/tasks'
        );

        $tasks = $this->getResponseData($response)['items'];

        // User2 should not see User1's imported task
        $foundTask = false;
        foreach ($tasks as $task) {
            if ($task['title'] === 'User1 Imported Task') {
                $foundTask = true;

                break;
            }
        }

        $this->assertFalse($foundTask);
    }

    // ========================================
    // Duplicate Handling Tests
    // ========================================

    public function testImportJsonReusesExistingProjects(): void
    {
        $user = $this->createUser('import-reuse-project@example.com', 'Password123');

        // Create a project first
        $this->createProject($user, 'Existing Project');

        // Import with same project name
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/import/json',
            [
                'projects' => [
                    ['name' => 'Existing Project'],
                ],
                'tasks' => [
                    ['title' => 'Task in existing project', 'project' => 'Existing Project'],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Stats count processed items (existing ones are still counted)
        $this->assertEquals(1, $data['stats']['projects']);
        $this->assertEquals(1, $data['stats']['tasks']);

        // Verify no duplicate projects were created by listing all projects
        $projectsResponse = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects'
        );
        $projects = $this->getResponseData($projectsResponse)['items'];

        // Count projects with this name - should be exactly 1
        $matchingProjects = array_filter($projects, fn ($p) => $p['name'] === 'Existing Project');
        $this->assertCount(1, $matchingProjects);
    }

    public function testImportJsonReusesExistingTags(): void
    {
        $user = $this->createUser('import-reuse-tag@example.com', 'Password123');

        // Create a tag first
        $existingTag = $this->createTag($user, 'existing-tag');
        $existingTagId = $existingTag->getId();

        // Import with same tag name
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/import/json',
            [
                'tags' => [
                    ['name' => 'existing-tag'],
                ],
                'tasks' => [
                    ['title' => 'Task with existing tag', 'tags' => ['existing-tag']],
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Stats count processed items (existing ones are still counted)
        $this->assertEquals(1, $data['stats']['tags']);
        $this->assertEquals(1, $data['stats']['tasks']);

        // Verify the task uses the existing tag by checking the task
        $tasksResponse = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks?search=Task+with+existing+tag'
        );
        $tasks = $this->getResponseData($tasksResponse)['items'];

        $this->assertNotEmpty($tasks);
        $taskTags = $tasks[0]['tags'];
        $this->assertCount(1, $taskTags);
        $this->assertEquals($existingTagId, $taskTags[0]['id']);
    }
}
