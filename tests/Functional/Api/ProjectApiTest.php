<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the Project API endpoints.
 *
 * Tests:
 * - List projects (empty, with projects, pagination, includeArchived)
 * - Create project (success, validation errors)
 * - Get single project (success, includes task counts)
 * - Update project (success, partial update)
 * - Delete project (cascade warning in response)
 * - Archive/unarchive (success, returns undo token)
 * - Undo operations
 */
class ProjectApiTest extends ApiTestCase
{
    // ========================================
    // List Projects Tests
    // ========================================

    public function testListProjectsEmpty(): void
    {
        $user = $this->createUser('list-empty@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('items', $data['data']);
        $this->assertEmpty($data['data']['items']);
        $this->assertArrayHasKey('meta', $data['data']);
        $this->assertEquals(0, $data['data']['meta']['total']);
    }

    public function testListProjectsWithProjects(): void
    {
        $user = $this->createUser('list-projects@example.com', 'Password123');

        $this->createProject($user, 'Project 1');
        $this->createProject($user, 'Project 2');
        $this->createProject($user, 'Project 3');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(3, $data['items']);
        $this->assertEquals(3, $data['meta']['total']);
    }

    public function testListProjectsPagination(): void
    {
        $user = $this->createUser('list-pagination@example.com', 'Password123');

        // Create 25 projects
        for ($i = 1; $i <= 25; $i++) {
            $this->createProject($user, "Project $i");
        }

        // Get first page with limit 10
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects?page=1&limit=10'
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
            '/api/v1/projects?page=2&limit=10'
        );

        $data = $this->getResponseData($response);

        $this->assertCount(10, $data['items']);
        $this->assertTrue($data['meta']['hasNextPage']);
        $this->assertTrue($data['meta']['hasPreviousPage']);
    }

    public function testListProjectsExcludesArchivedByDefault(): void
    {
        $user = $this->createUser('list-archived@example.com', 'Password123');

        $this->createProject($user, 'Active Project', null, false);
        $this->createProject($user, 'Archived Project', null, true);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('Active Project', $data['items'][0]['name']);
    }

    public function testListProjectsIncludeArchived(): void
    {
        $user = $this->createUser('list-include-archived@example.com', 'Password123');

        $this->createProject($user, 'Active Project', null, false);
        $this->createProject($user, 'Archived Project', null, true);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects?includeArchived=true'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['items']);
    }

    public function testListProjectsUnauthenticated(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/projects');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testListProjectsOnlyOwned(): void
    {
        $user1 = $this->createUser('user1-owned@example.com', 'Password123');
        $user2 = $this->createUser('user2-owned@example.com', 'Password123');

        $this->createProject($user1, 'User 1 Project');
        $this->createProject($user2, 'User 2 Project');

        $response = $this->authenticatedApiRequest(
            $user1,
            'GET',
            '/api/v1/projects'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('User 1 Project', $data['items'][0]['name']);
    }

    // ========================================
    // Create Project Tests
    // ========================================

    public function testCreateProjectSuccess(): void
    {
        $user = $this->createUser('create-project@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects',
            [
                'name' => 'New Project',
                'description' => 'Project description',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('New Project', $data['name']);
        $this->assertEquals('Project description', $data['description']);
        $this->assertFalse($data['isArchived']);
    }

    public function testCreateProjectMinimalData(): void
    {
        $user = $this->createUser('create-minimal@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects',
            ['name' => 'Minimal Project']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Minimal Project', $data['name']);
        $this->assertNull($data['description']);
    }

    public function testCreateProjectValidationErrors(): void
    {
        $user = $this->createUser('create-validation@example.com', 'Password123');

        // Missing name
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects',
            ['description' => 'No name provided']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testCreateProjectNameTooLong(): void
    {
        $user = $this->createUser('create-long-name@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects',
            ['name' => str_repeat('a', 150)]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testCreateProjectDescriptionTooLong(): void
    {
        $user = $this->createUser('create-long-desc@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects',
            [
                'name' => 'Valid Name',
                'description' => str_repeat('a', 600),
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testCreateProjectUnauthenticated(): void
    {
        $response = $this->apiRequest(
            'POST',
            '/api/v1/projects',
            ['name' => 'Unauthorized Project']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Get Single Project Tests
    // ========================================

    public function testGetProjectSuccess(): void
    {
        $user = $this->createUser('get-project@example.com', 'Password123');
        $project = $this->createProject($user, 'Test Project', 'Description');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects/' . $project->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals($project->getId(), $data['id']);
        $this->assertEquals('Test Project', $data['name']);
        $this->assertEquals('Description', $data['description']);
    }

    public function testGetProjectIncludesTaskCounts(): void
    {
        $user = $this->createUser('get-counts@example.com', 'Password123');
        $project = $this->createProject($user, 'Test Project');

        // Create tasks with different statuses
        $this->createTask($user, 'Pending Task', null, Task::STATUS_PENDING, 3, $project);
        $this->createTask($user, 'In Progress Task', null, Task::STATUS_IN_PROGRESS, 3, $project);
        $this->createTask($user, 'Completed Task', null, Task::STATUS_COMPLETED, 3, $project);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects/' . $project->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('taskCount', $data);
        $this->assertArrayHasKey('completedTaskCount', $data);
        $this->assertEquals(3, $data['taskCount']);
        $this->assertEquals(1, $data['completedTaskCount']);
    }

    public function testGetProjectNotFound(): void
    {
        $user = $this->createUser('get-notfound@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects/00000000-0000-0000-0000-000000000000'
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
        $this->assertErrorCode($response, 'NOT_FOUND');
    }

    public function testGetProjectNotOwned(): void
    {
        $user1 = $this->createUser('user1-get@example.com', 'Password123');
        $user2 = $this->createUser('user2-get@example.com', 'Password123');
        $project = $this->createProject($user2, 'User 2 Project');

        $response = $this->authenticatedApiRequest(
            $user1,
            'GET',
            '/api/v1/projects/' . $project->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    // ========================================
    // Update Project Tests
    // ========================================

    public function testUpdateProjectSuccess(): void
    {
        $user = $this->createUser('update-project@example.com', 'Password123');
        $project = $this->createProject($user, 'Original Name', 'Original Description');

        $response = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/projects/' . $project->getId(),
            [
                'name' => 'Updated Name',
                'description' => 'Updated Description',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Updated Name', $data['name']);
        $this->assertEquals('Updated Description', $data['description']);
    }

    public function testUpdateProjectPartialUpdate(): void
    {
        $user = $this->createUser('update-partial@example.com', 'Password123');
        $project = $this->createProject($user, 'Original Name', 'Original Description');

        // Only update name
        $response = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/projects/' . $project->getId(),
            ['name' => 'Only Name Updated']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Only Name Updated', $data['name']);
        $this->assertEquals('Original Description', $data['description']);
    }

    public function testUpdateProjectReturnsUndoToken(): void
    {
        $user = $this->createUser('update-undo@example.com', 'Password123');
        $project = $this->createProject($user, 'Original Name');

        $response = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/projects/' . $project->getId(),
            ['name' => 'Updated Name']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $meta = $this->getResponseMeta($response);

        $this->assertArrayHasKey('undoToken', $meta);
        $this->assertNotEmpty($meta['undoToken']);
    }

    public function testUpdateProjectNotFound(): void
    {
        $user = $this->createUser('update-notfound@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/projects/00000000-0000-0000-0000-000000000000',
            ['name' => 'Updated']
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    public function testUpdateProjectValidationErrors(): void
    {
        $user = $this->createUser('update-validation@example.com', 'Password123');
        $project = $this->createProject($user, 'Original Name');

        $response = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/projects/' . $project->getId(),
            ['name' => str_repeat('a', 150)]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    // ========================================
    // Delete Project Tests
    // ========================================

    public function testDeleteProjectSuccess(): void
    {
        $user = $this->createUser('delete-project@example.com', 'Password123');
        $project = $this->createProject($user, 'Project to Delete');

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/projects/' . $project->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('warning', $data);
        $this->assertStringContainsString('deleted', strtolower($data['message']));
    }

    public function testDeleteProjectReturnsCascadeWarning(): void
    {
        $user = $this->createUser('delete-cascade@example.com', 'Password123');
        $project = $this->createProject($user, 'Project with Tasks');

        // Create tasks in project
        $this->createTask($user, 'Task 1', null, Task::STATUS_PENDING, 3, $project);
        $this->createTask($user, 'Task 2', null, Task::STATUS_PENDING, 3, $project);

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/projects/' . $project->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should warn about cascade delete
        $this->assertArrayHasKey('warning', $data);
        $this->assertStringContainsString('task', strtolower($data['warning']));
    }

    public function testDeleteProjectReturnsUndoToken(): void
    {
        $user = $this->createUser('delete-undo@example.com', 'Password123');
        $project = $this->createProject($user, 'Project to Delete');

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/projects/' . $project->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $meta = $this->getResponseMeta($response);

        $this->assertArrayHasKey('undoToken', $meta);
        $this->assertNotEmpty($meta['undoToken']);
    }

    public function testDeleteProjectNotFound(): void
    {
        $user = $this->createUser('delete-notfound@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/projects/00000000-0000-0000-0000-000000000000'
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    public function testDeleteProjectNotOwned(): void
    {
        $user1 = $this->createUser('user1-delete@example.com', 'Password123');
        $user2 = $this->createUser('user2-delete@example.com', 'Password123');
        $project = $this->createProject($user2, 'User 2 Project');

        $response = $this->authenticatedApiRequest(
            $user1,
            'DELETE',
            '/api/v1/projects/' . $project->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    // ========================================
    // Archive/Unarchive Tests
    // ========================================

    public function testArchiveProjectSuccess(): void
    {
        $user = $this->createUser('archive@example.com', 'Password123');
        $project = $this->createProject($user, 'Project to Archive', null, false);

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects/' . $project->getId() . '/archive'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['isArchived']);
    }

    public function testArchiveProjectReturnsUndoToken(): void
    {
        $user = $this->createUser('archive-undo@example.com', 'Password123');
        $project = $this->createProject($user, 'Project to Archive');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects/' . $project->getId() . '/archive'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $meta = $this->getResponseMeta($response);

        $this->assertArrayHasKey('undoToken', $meta);
    }

    public function testUnarchiveProjectSuccess(): void
    {
        $user = $this->createUser('unarchive@example.com', 'Password123');
        $project = $this->createProject($user, 'Archived Project', null, true);

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects/' . $project->getId() . '/unarchive'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertFalse($data['isArchived']);
    }

    public function testUnarchiveProjectReturnsUndoToken(): void
    {
        $user = $this->createUser('unarchive-undo@example.com', 'Password123');
        $project = $this->createProject($user, 'Archived Project', null, true);

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects/' . $project->getId() . '/unarchive'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $meta = $this->getResponseMeta($response);

        $this->assertArrayHasKey('undoToken', $meta);
    }

    public function testArchiveProjectNotFound(): void
    {
        $user = $this->createUser('archive-notfound@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects/00000000-0000-0000-0000-000000000000/archive'
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    // ========================================
    // Undo Operations Tests
    // ========================================

    public function testUndoUpdateSuccess(): void
    {
        $user = $this->createUser('undo-update@example.com', 'Password123');
        $project = $this->createProject($user, 'Original Name', 'Original Description');

        // Update the project
        $updateResponse = $this->authenticatedApiRequest(
            $user,
            'PUT',
            '/api/v1/projects/' . $project->getId(),
            ['name' => 'New Name']
        );

        $meta = $this->getResponseMeta($updateResponse);
        $undoToken = $meta['undoToken'];

        // Undo the update
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects/undo/' . $undoToken
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('project', $data);
        $this->assertEquals('Original Name', $data['project']['name']);
    }

    public function testUndoArchiveSuccess(): void
    {
        $user = $this->createUser('undo-archive@example.com', 'Password123');
        $project = $this->createProject($user, 'Test Project', null, false);

        // Archive the project
        $archiveResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects/' . $project->getId() . '/archive'
        );

        $meta = $this->getResponseMeta($archiveResponse);
        $undoToken = $meta['undoToken'];

        // Undo the archive
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects/undo/' . $undoToken
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('project', $data);
        $this->assertFalse($data['project']['isArchived']);
    }

    public function testUndoDeleteSuccess(): void
    {
        $user = $this->createUser('undo-delete@example.com', 'Password123');
        $project = $this->createProject($user, 'Project to Delete');
        $originalName = $project->getName();

        // Delete the project
        $deleteResponse = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/projects/' . $project->getId()
        );

        $meta = $this->getResponseMeta($deleteResponse);
        $undoToken = $meta['undoToken'];

        // Undo the delete
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects/undo/' . $undoToken
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('project', $data);
        $this->assertEquals($originalName, $data['project']['name']);
        // Should have warning about tasks not being restored
        $this->assertArrayHasKey('warning', $data);
    }

    public function testUndoInvalidToken(): void
    {
        $user = $this->createUser('undo-invalid@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects/undo/invalid-token-12345'
        );

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, 'INVALID_UNDO_TOKEN');
    }

    public function testUndoWrongUser(): void
    {
        $user1 = $this->createUser('user1-undo@example.com', 'Password123');
        $user2 = $this->createUser('user2-undo@example.com', 'Password123');
        $project = $this->createProject($user1, 'User 1 Project');

        // User 1 updates project
        $updateResponse = $this->authenticatedApiRequest(
            $user1,
            'PUT',
            '/api/v1/projects/' . $project->getId(),
            ['name' => 'Updated Name']
        );

        $meta = $this->getResponseMeta($updateResponse);
        $undoToken = $meta['undoToken'];

        // User 2 tries to undo
        $response = $this->authenticatedApiRequest(
            $user2,
            'POST',
            '/api/v1/projects/undo/' . $undoToken
        );

        // Should fail - token belongs to different user
        $this->assertIn($response->getStatusCode(), [
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_FORBIDDEN,
            Response::HTTP_NOT_FOUND,
        ]);
    }

    // ========================================
    // Response Structure Tests
    // ========================================

    public function testProjectResponseStructure(): void
    {
        $user = $this->createUser('structure@example.com', 'Password123');
        $project = $this->createProject($user, 'Test Project', 'Description');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects/' . $project->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Verify all expected fields are present
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('isArchived', $data);
        $this->assertArrayHasKey('taskCount', $data);
        $this->assertArrayHasKey('completedTaskCount', $data);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertArrayHasKey('updatedAt', $data);
    }

    public function testProjectListResponseStructure(): void
    {
        $user = $this->createUser('list-structure@example.com', 'Password123');
        $this->createProject($user, 'Test Project');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Verify list structure
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('total', $data['meta']);
        $this->assertArrayHasKey('page', $data['meta']);
        $this->assertArrayHasKey('limit', $data['meta']);
        $this->assertArrayHasKey('totalPages', $data['meta']);
        $this->assertArrayHasKey('hasNextPage', $data['meta']);
        $this->assertArrayHasKey('hasPreviousPage', $data['meta']);
    }

    // ========================================
    // Edge Cases
    // ========================================

    public function testCanAccessArchivedProjectById(): void
    {
        $user = $this->createUser('access-archived@example.com', 'Password123');
        $project = $this->createProject($user, 'Archived Project', null, true);

        // Even though it's archived, we should be able to access it by ID
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/projects/' . $project->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Archived Project', $data['name']);
        $this->assertTrue($data['isArchived']);
    }

    public function testTasksInArchivedProjectStillExist(): void
    {
        $user = $this->createUser('archived-tasks@example.com', 'Password123');
        $project = $this->createProject($user, 'Project to Archive');

        $task = $this->createTask($user, 'Task in Project', null, Task::STATUS_PENDING, 3, $project);

        // Archive the project
        $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects/' . $project->getId() . '/archive'
        );

        // Task should still be accessible
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/' . $task->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
    }
}
