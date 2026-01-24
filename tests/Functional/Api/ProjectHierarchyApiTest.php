<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Project;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProjectHierarchyApiTest extends ApiTestCase
{
    // ========================================
    // Create with Parent Tests
    // ========================================

    public function testCreateProjectWithParent(): void
    {
        $user = $this->createUser('create-parent@example.com');
        $parent = $this->createProject($user, 'Parent Project');

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/projects', [
            'name' => 'Child Project',
            'parentId' => $parent->getId(),
        ]);

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);
        $data = $this->getResponseData($response);

        $this->assertEquals('Child Project', $data['name']);
        $this->assertEquals($parent->getId(), $data['parentId']);
        $this->assertEquals(1, $data['depth']);
    }

    public function testCreateProjectWithInvalidParentReturns422(): void
    {
        $user = $this->createUser('create-invalid-parent@example.com');

        // Use a valid UUID format that doesn't exist in the database
        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/projects', [
            'name' => 'Child Project',
            'parentId' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'PROJECT_PARENT_NOT_FOUND');
    }

    public function testCreateProjectWithOtherUsersParentReturns403(): void
    {
        $user1 = $this->createUser('owner1@example.com');
        $user2 = $this->createUser('owner2@example.com');
        $parent = $this->createProject($user1, 'User 1 Project');

        $response = $this->authenticatedApiRequest($user2, 'POST', '/api/v1/projects', [
            'name' => 'Child Project',
            'parentId' => $parent->getId(),
        ]);

        $this->assertResponseStatusCode(Response::HTTP_FORBIDDEN, $response);
        $this->assertErrorCode($response, 'PROJECT_PARENT_NOT_OWNED_BY_USER');
    }

    public function testCreateProjectWithArchivedParentReturns422(): void
    {
        $user = $this->createUser('create-archived-parent@example.com');
        $parent = $this->createProject($user, 'Archived Parent', null, true);

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/projects', [
            'name' => 'Child Project',
            'parentId' => $parent->getId(),
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'PROJECT_CANNOT_MOVE_TO_ARCHIVED_PARENT');
    }

    // ========================================
    // Tree Endpoint Tests
    // ========================================

    public function testTreeEndpointReturnsTree(): void
    {
        $user = $this->createUser('tree-test@example.com');
        $parent = $this->createProject($user, 'Parent');
        $this->createProject($user, 'Child', null, false, $parent);

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/tree');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('projects', $data);
        $this->assertCount(1, $data['projects']); // Only root projects
        $this->assertEquals('Parent', $data['projects'][0]['name']);
        $this->assertCount(1, $data['projects'][0]['children']);
        $this->assertEquals('Child', $data['projects'][0]['children'][0]['name']);
    }

    public function testTreeEndpointIncludesTaskCountsByDefault(): void
    {
        $user = $this->createUser('tree-counts@example.com');
        $project = $this->createProject($user, 'Project');
        $this->createTask($user, 'Task 1', null, 'pending', 3, $project);
        $this->createTask($user, 'Task 2', null, 'completed', 3, $project);

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/tree?include_task_counts=true');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);

        $this->assertEquals(2, $data['projects'][0]['taskCount']);
        $this->assertEquals(1, $data['projects'][0]['completedTaskCount']);
        $this->assertEquals(1, $data['projects'][0]['pendingTaskCount']); // 2 - 1 = 1
    }

    public function testTreeEndpointExcludesArchivedByDefault(): void
    {
        $user = $this->createUser('tree-archived@example.com');
        $this->createProject($user, 'Active');
        $this->createProject($user, 'Archived', null, true);

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/tree');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['projects']);
        $this->assertEquals('Active', $data['projects'][0]['name']);
    }

    public function testTreeEndpointIncludesArchivedWhenRequested(): void
    {
        $user = $this->createUser('tree-include-archived@example.com');
        $this->createProject($user, 'Active');
        $this->createProject($user, 'Archived', null, true);

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/tree?include_archived=true');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['projects']);
    }

    // ========================================
    // Move Endpoint Tests
    // ========================================

    public function testMoveProjectToNewParent(): void
    {
        $user = $this->createUser('move-parent@example.com');
        $parent = $this->createProject($user, 'Parent');
        $project = $this->createProject($user, 'Project');

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/projects/' . $project->getId() . '/move', [
            'parentId' => $parent->getId(),
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);

        $this->assertEquals($parent->getId(), $data['parentId']);
        $this->assertEquals(1, $data['depth']);
    }

    public function testMoveProjectToRoot(): void
    {
        $user = $this->createUser('move-root@example.com');
        $parent = $this->createProject($user, 'Parent');
        $child = $this->createProject($user, 'Child', null, false, $parent);

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/projects/' . $child->getId() . '/move', [
            'parentId' => null,
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);

        $this->assertNull($data['parentId']);
        $this->assertEquals(0, $data['depth']);
    }

    public function testMoveProjectToDescendantReturns422(): void
    {
        $user = $this->createUser('move-descendant@example.com');
        $parent = $this->createProject($user, 'Parent');
        $child = $this->createProject($user, 'Child', null, false, $parent);
        $grandchild = $this->createProject($user, 'Grandchild', null, false, $child);

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/projects/' . $parent->getId() . '/move', [
            'parentId' => $grandchild->getId(),
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'PROJECT_MOVE_TO_DESCENDANT');
    }

    public function testMoveProjectReturnsUndoToken(): void
    {
        $user = $this->createUser('move-undo@example.com');
        $parent = $this->createProject($user, 'Parent');
        $project = $this->createProject($user, 'Project');

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/projects/' . $project->getId() . '/move', [
            'parentId' => $parent->getId(),
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $meta = $this->getResponseMeta($response);

        $this->assertArrayHasKey('undoToken', $meta);
    }

    // ========================================
    // Reorder Endpoint Tests
    // ========================================

    public function testReorderProjects(): void
    {
        $user = $this->createUser('reorder@example.com');
        $project1 = $this->createProject($user, 'Project 1');
        $project2 = $this->createProject($user, 'Project 2');
        $project3 = $this->createProject($user, 'Project 3');

        // Reorder: 3, 1, 2
        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/projects/reorder', [
            'parentId' => null,
            'projectIds' => [$project3->getId(), $project1->getId(), $project2->getId()],
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        // Verify order via tree endpoint
        $treeResponse = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/tree');
        $treeData = $this->getResponseData($treeResponse);

        $names = array_map(fn($p) => $p['name'], $treeData['projects']);
        $this->assertEquals(['Project 3', 'Project 1', 'Project 2'], $names);
    }

    // ========================================
    // Project Tasks Endpoint Tests
    // ========================================

    public function testProjectTasksEndpointReturnsTasksForProject(): void
    {
        $user = $this->createUser('project-tasks@example.com');
        $project = $this->createProject($user, 'Project');
        $this->createTask($user, 'Task 1', null, 'pending', 3, $project);
        $this->createTask($user, 'Task 2', null, 'pending', 3, $project);

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/' . $project->getId() . '/tasks');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('tasks', $data);
        $this->assertCount(2, $data['tasks']);
        $this->assertEquals(2, $data['total']);
    }

    public function testProjectTasksEndpointIncludesChildrenTasks(): void
    {
        $user = $this->createUser('project-tasks-children@example.com');
        $parent = $this->createProject($user, 'Parent');
        $child = $this->createProject($user, 'Child', null, false, $parent);

        $this->createTask($user, 'Parent Task', null, 'pending', 3, $parent);
        $this->createTask($user, 'Child Task', null, 'pending', 3, $child);

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/' . $parent->getId() . '/tasks?include_children=true');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['tasks']);
    }

    // ========================================
    // Settings Endpoint Tests
    // ========================================

    public function testUpdateProjectSettings(): void
    {
        $user = $this->createUser('settings@example.com');
        $project = $this->createProject($user, 'Project');

        $response = $this->authenticatedApiRequest($user, 'PATCH', '/api/v1/projects/' . $project->getId() . '/settings', [
            'showChildrenTasks' => false,
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);

        $this->assertFalse($data['showChildrenTasks']);
    }

    // ========================================
    // Archived List Endpoint Tests
    // ========================================

    public function testArchivedListEndpoint(): void
    {
        $user = $this->createUser('archived-list@example.com');
        $this->createProject($user, 'Active Project');
        $this->createProject($user, 'Archived 1', null, true);
        $this->createProject($user, 'Archived 2', null, true);

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/archived');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('projects', $data);
        $this->assertCount(2, $data['projects']);
        $this->assertEquals(2, $data['total']);
    }
}
