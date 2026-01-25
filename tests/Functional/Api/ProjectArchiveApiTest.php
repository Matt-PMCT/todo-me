<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProjectArchiveApiTest extends ApiTestCase
{
    // ========================================
    // Archive with Cascade Tests
    // ========================================

    public function testArchiveWithCascadeArchivesDescendants(): void
    {
        $user = $this->createUser('archive-cascade@example.com');
        $parent = $this->createProject($user, 'Parent');
        $child = $this->createProject($user, 'Child', null, false, $parent);
        $grandchild = $this->createProject($user, 'Grandchild', null, false, $child);

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/projects/'.$parent->getId().'/archive?cascade=true'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);
        $meta = $this->getResponseMeta($response);

        $this->assertTrue($data['isArchived']);
        $this->assertArrayHasKey('affectedProjects', $meta);
        $this->assertCount(2, $meta['affectedProjects']);
        $this->assertContains($child->getId(), $meta['affectedProjects']);
        $this->assertContains($grandchild->getId(), $meta['affectedProjects']);
    }

    public function testArchiveWithoutCascadeDoesNotArchiveDescendants(): void
    {
        $user = $this->createUser('archive-no-cascade@example.com');
        $parent = $this->createProject($user, 'Parent');
        $child = $this->createProject($user, 'Child', null, false, $parent);

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/projects/'.$parent->getId().'/archive'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);
        $meta = $this->getResponseMeta($response);

        $this->assertTrue($data['isArchived']);
        $this->assertFalse(isset($meta['affectedProjects']) && count($meta['affectedProjects']) > 0);

        // Verify child is not archived
        $showResponse = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/'.$child->getId());
        $childData = $this->getResponseData($showResponse);
        $this->assertFalse($childData['isArchived']);
    }

    // ========================================
    // Archive with Promote Children Tests
    // ========================================

    public function testArchiveWithPromoteChildrenMovesChildrenToParent(): void
    {
        $user = $this->createUser('archive-promote@example.com');
        $grandparent = $this->createProject($user, 'Grandparent');
        $parent = $this->createProject($user, 'Parent', null, false, $grandparent);
        $child1 = $this->createProject($user, 'Child 1', null, false, $parent);
        $child2 = $this->createProject($user, 'Child 2', null, false, $parent);

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/projects/'.$parent->getId().'/archive?promote_children=true'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $meta = $this->getResponseMeta($response);

        $this->assertArrayHasKey('affectedProjects', $meta);
        $this->assertCount(2, $meta['affectedProjects']);

        // Verify children are now under grandparent
        $child1Response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/'.$child1->getId());
        $child1Data = $this->getResponseData($child1Response);
        $this->assertEquals($grandparent->getId(), $child1Data['parentId']);

        $child2Response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/'.$child2->getId());
        $child2Data = $this->getResponseData($child2Response);
        $this->assertEquals($grandparent->getId(), $child2Data['parentId']);
    }

    public function testArchiveRootWithPromoteChildrenMovesChildrenToRoot(): void
    {
        $user = $this->createUser('archive-promote-root@example.com');
        $parent = $this->createProject($user, 'Parent');
        $child = $this->createProject($user, 'Child', null, false, $parent);

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/projects/'.$parent->getId().'/archive?promote_children=true'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        // Verify child is now a root project
        $childResponse = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/'.$child->getId());
        $childData = $this->getResponseData($childResponse);
        $this->assertNull($childData['parentId']);
        $this->assertEquals(0, $childData['depth']);
    }

    // ========================================
    // Unarchive with Cascade Tests
    // ========================================

    public function testUnarchiveWithCascadeUnarchivesDescendants(): void
    {
        $user = $this->createUser('unarchive-cascade@example.com');
        $parent = $this->createProject($user, 'Parent', null, true);
        $child = $this->createProject($user, 'Child', null, true, $parent);
        $grandchild = $this->createProject($user, 'Grandchild', null, true, $child);

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/projects/'.$parent->getId().'/unarchive?cascade=true'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);
        $meta = $this->getResponseMeta($response);

        $this->assertFalse($data['isArchived']);
        $this->assertArrayHasKey('affectedProjects', $meta);
        $this->assertCount(2, $meta['affectedProjects']);

        // Verify descendants are unarchived
        $childResponse = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/'.$child->getId());
        $childData = $this->getResponseData($childResponse);
        $this->assertFalse($childData['isArchived']);

        $grandchildResponse = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/'.$grandchild->getId());
        $grandchildData = $this->getResponseData($grandchildResponse);
        $this->assertFalse($grandchildData['isArchived']);
    }

    public function testUnarchiveWithoutCascadeDoesNotUnarchiveDescendants(): void
    {
        $user = $this->createUser('unarchive-no-cascade@example.com');
        $parent = $this->createProject($user, 'Parent', null, true);
        $child = $this->createProject($user, 'Child', null, true, $parent);

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/projects/'.$parent->getId().'/unarchive'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);

        $this->assertFalse($data['isArchived']);

        // Verify child is still archived - need to use include_archived in tree to see it
        $treeResponse = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/tree?include_archived=true');
        $treeData = $this->getResponseData($treeResponse);

        $parentNode = $treeData['projects'][0];
        $childNode = $parentNode['children'][0];
        $this->assertTrue($childNode['isArchived']);
    }

    // ========================================
    // Archive Returns Undo Token Tests
    // ========================================

    public function testArchiveReturnsUndoToken(): void
    {
        $user = $this->createUser('archive-undo@example.com');
        $project = $this->createProject($user, 'Project');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/projects/'.$project->getId().'/archive'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $meta = $this->getResponseMeta($response);

        $this->assertArrayHasKey('undoToken', $meta);
        $this->assertArrayHasKey('undoExpiresIn', $meta);
    }

    public function testUnarchiveReturnsUndoToken(): void
    {
        $user = $this->createUser('unarchive-undo@example.com');
        $project = $this->createProject($user, 'Project', null, true);

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/projects/'.$project->getId().'/unarchive'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $meta = $this->getResponseMeta($response);

        $this->assertArrayHasKey('undoToken', $meta);
    }

    // ========================================
    // Breadcrumb (Path) Tests
    // ========================================

    public function testProjectShowIncludesPathWithArchivedAncestors(): void
    {
        $user = $this->createUser('path-archived@example.com');
        $grandparent = $this->createProject($user, 'Grandparent', null, true);
        $parent = $this->createProject($user, 'Parent', null, false, $grandparent);
        $child = $this->createProject($user, 'Child', null, false, $parent);

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/projects/'.$child->getId());

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('path', $data);
        $this->assertCount(3, $data['path']);

        // Verify path includes archived status
        $this->assertEquals('Grandparent', $data['path'][0]['name']);
        $this->assertTrue($data['path'][0]['isArchived']);

        $this->assertEquals('Parent', $data['path'][1]['name']);
        $this->assertFalse($data['path'][1]['isArchived']);

        $this->assertEquals('Child', $data['path'][2]['name']);
        $this->assertFalse($data['path'][2]['isArchived']);
    }

    // ========================================
    // Cannot Move to Archived Parent Tests
    // ========================================

    public function testCannotMoveProjectToArchivedParent(): void
    {
        $user = $this->createUser('move-archived@example.com');
        $archivedParent = $this->createProject($user, 'Archived Parent', null, true);
        $project = $this->createProject($user, 'Project');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects/'.$project->getId().'/move',
            ['parentId' => $archivedParent->getId()]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'PROJECT_CANNOT_MOVE_TO_ARCHIVED_PARENT');
    }
}
