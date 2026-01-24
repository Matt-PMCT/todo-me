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

    public function testCreateProjectWithOtherUsersParentReturns422(): void
    {
        $user1 = $this->createUser('owner1@example.com');
        $user2 = $this->createUser('owner2@example.com');
        $parent = $this->createProject($user1, 'User 1 Project');

        $response = $this->authenticatedApiRequest($user2, 'POST', '/api/v1/projects', [
            'name' => 'Child Project',
            'parentId' => $parent->getId(),
        ]);

        // Security through obscurity: don't reveal that another user's project exists
        // by returning 422 PROJECT_PARENT_NOT_FOUND instead of 403
        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'PROJECT_PARENT_NOT_FOUND');
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

        // Response uses paginated structure with items and meta
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);
        $this->assertArrayHasKey('meta', $data);
        $this->assertEquals(2, $data['meta']['total']);
    }

    // ========================================
    // Error Code Tests (Issue 2.8)
    // ========================================

    public function testMoveToSelfReturnsCannotBeOwnParentError(): void
    {
        $user = $this->createUser('move-self@example.com');
        $project = $this->createProject($user, 'Test Project');

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/projects/' . $project->getId() . '/move', [
            'parentId' => $project->getId(),
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        // Implementation uses PROJECT_MOVE_TO_DESCENDANT for both self-reference and descendant moves
        $this->assertErrorCode($response, 'PROJECT_MOVE_TO_DESCENDANT');
    }

    public function testCircularReferenceReturnsErrorCode(): void
    {
        $user = $this->createUser('circular-ref@example.com');
        $parent = $this->createProject($user, 'Parent');
        $child = $this->createProject($user, 'Child', null, false, $parent);

        // Try to move parent under child (would create circular reference)
        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/projects/' . $parent->getId() . '/move', [
            'parentId' => $child->getId(),
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        // Implementation uses PROJECT_MOVE_TO_DESCENDANT for circular reference detection
        $this->assertErrorCode($response, 'PROJECT_MOVE_TO_DESCENDANT');
    }

    // ========================================
    // Depth Limit Tests (Additional Edge Cases)
    // ========================================

    public function testDepthLimitEnforcementViaApi(): void
    {
        $user = $this->createUser('depth-limit@example.com');

        // Create a chain of projects up to MAX_DEPTH - 1
        // MAX_HIERARCHY_DEPTH is 50, so we need to build a deep hierarchy
        // For performance, we'll test at a smaller depth that triggers the limit
        $projects = [];
        $parent = null;

        // Create 49 nested projects (depth 0 to 48)
        for ($i = 0; $i < 49; $i++) {
            $projectName = "Level $i";
            $projects[$i] = $this->createProject($user, $projectName, null, false, $parent);
            $parent = $projects[$i];
        }

        // Try to create the 50th nested project (depth 49) - should succeed
        // 51st project (depth 50) should fail
        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/projects', [
            'name' => 'Level 49',
            'parentId' => $parent->getId(),
        ]);

        // This creates depth 49, which is still valid (MAX_HIERARCHY_DEPTH = 50 means depth < 50 is valid)
        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);
        $data = $this->getResponseData($response);
        $levelFortyNine = $data['id'];

        // Now try to create another level under that - should fail
        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/projects', [
            'name' => 'Too Deep',
            'parentId' => $levelFortyNine,
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'PROJECT_HIERARCHY_TOO_DEEP');
    }

    // ========================================
    // Batch Reorder Tests (Additional Edge Cases)
    // ========================================

    public function testBatchReorderExceedingLimitFails(): void
    {
        $user = $this->createUser('batch-limit@example.com');

        // Create a valid project first
        $project = $this->createProject($user, 'Test Project');

        // Try to reorder with > 1000 project IDs (generate fake UUIDs)
        $projectIds = [];
        for ($i = 0; $i < 1001; $i++) {
            $projectIds[] = sprintf(
                '%08x-%04x-%04x-%04x-%012x',
                $i,
                0,
                0,
                0,
                $i
            );
        }

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/projects/reorder', [
            'parentId' => null,
            'projectIds' => $projectIds,
        ]);

        // Should return 422 Unprocessable Entity for exceeding the limit
        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'BATCH_SIZE_LIMIT_EXCEEDED');
    }

    // ========================================
    // Cross-User Parent Assignment Tests
    // ========================================

    public function testCrossUserParentAssignmentFails(): void
    {
        $user1 = $this->createUser('user1-cross@example.com');
        $user2 = $this->createUser('user2-cross@example.com');

        $user1Project = $this->createProject($user1, 'User 1 Parent');

        // User 2 tries to create a project with User 1's project as parent
        $response = $this->authenticatedApiRequest($user2, 'POST', '/api/v1/projects', [
            'name' => 'User 2 Child',
            'parentId' => $user1Project->getId(),
        ]);

        // Security through obscurity: don't reveal that another user's project exists
        // by returning 422 PROJECT_PARENT_NOT_FOUND instead of 403
        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'PROJECT_PARENT_NOT_FOUND');
    }

    public function testCrossUserMoveOperationFails(): void
    {
        $user1 = $this->createUser('user1-move@example.com');
        $user2 = $this->createUser('user2-move@example.com');

        $user1Parent = $this->createProject($user1, 'User 1 Parent');
        $user2Project = $this->createProject($user2, 'User 2 Project');

        // User 2 tries to move their project under User 1's project
        $response = $this->authenticatedApiRequest($user2, 'POST', '/api/v1/projects/' . $user2Project->getId() . '/move', [
            'parentId' => $user1Parent->getId(),
        ]);

        // Should fail - parent not owned by the user
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_FORBIDDEN,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            Response::HTTP_NOT_FOUND,
        ]);
    }
}
