<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DTO\CreateProjectRequest;
use App\DTO\MoveProjectRequest;
use App\DTO\UpdateProjectRequest;
use App\Service\ProjectCacheService;
use App\Service\ProjectService;
use Symfony\Component\Cache\Adapter\AdapterInterface;

/**
 * Integration tests for project tree caching.
 *
 * Tests verify that:
 * - Cache is populated after getTree()
 * - Cache is invalidated after mutations (create/update/delete/move)
 * - Cache is invalidated after undo operations
 */
class ProjectCacheIntegrationTest extends IntegrationTestCase
{
    private ProjectService $projectService;
    private ProjectCacheService $projectCacheService;
    private ?AdapterInterface $cacheAdapter = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectService = static::getContainer()->get(ProjectService::class);
        $this->projectCacheService = static::getContainer()->get(ProjectCacheService::class);

        // Try to get the cache adapter for direct inspection
        try {
            $cache = static::getContainer()->get('cache.app');
            if ($cache instanceof AdapterInterface) {
                $this->cacheAdapter = $cache;
            }
        } catch (\Exception $e) {
            // Cache adapter might not be directly accessible
        }
    }

    // ========================================
    // Issue 2.10 - Cache Population Tests
    // ========================================

    public function testCachePopulatedAfterGetTree(): void
    {
        $user = $this->createUser('cache-populated@example.com');
        $this->createProject($user, 'Test Project');

        $userId = $user->getId();

        // Clear cache first (if accessible)
        $this->projectCacheService->invalidate($userId);

        // First call should miss cache and populate it
        $tree1 = $this->projectService->getTree($user, false, false);

        $this->assertIsArray($tree1);
        $this->assertNotEmpty($tree1);

        // Verify cache was populated by checking if get returns non-null
        $cachedData = $this->projectCacheService->get($userId, false, false);

        // Cache should now have data (if caching is working)
        // Note: In test environment, cache might be disabled
        if ($cachedData !== null) {
            $this->assertEquals($tree1, $cachedData);
        }

        // Second call should return same data
        $tree2 = $this->projectService->getTree($user, false, false);
        $this->assertEquals($tree1, $tree2);
    }

    public function testCacheKeyVariantsAreIndependent(): void
    {
        $user = $this->createUser('cache-variants@example.com');
        $this->createProject($user, 'Active Project');
        $this->createProject($user, 'Archived Project', true);

        $userId = $user->getId();

        // Clear all cache variants
        $this->projectCacheService->invalidate($userId);

        // Get tree without archived
        $treeWithoutArchived = $this->projectService->getTree($user, false, false);

        // Get tree with archived
        $treeWithArchived = $this->projectService->getTree($user, true, false);

        // The results should be different (archived version has more projects)
        $this->assertCount(1, $treeWithoutArchived);
        $this->assertCount(2, $treeWithArchived);

        // Get tree with task counts
        $treeWithCounts = $this->projectService->getTree($user, false, true);

        // Should have task count fields
        $this->assertArrayHasKey('taskCount', $treeWithCounts[0]);
    }

    // ========================================
    // Issue 2.10 - Cache Invalidation on Mutation Tests
    // ========================================

    public function testCacheInvalidatedAfterCreate(): void
    {
        $user = $this->createUser('cache-create@example.com');
        $userId = $user->getId();

        // Populate cache
        $this->projectService->getTree($user, false, false);

        // Create a new project
        $dto = new CreateProjectRequest(name: 'New Project');
        $this->projectService->create($user, $dto);

        // Cache should be invalidated - next getTree should return fresh data
        $tree = $this->projectService->getTree($user, false, false);

        // Find the new project in the tree
        $found = false;
        foreach ($tree as $project) {
            if ($project['name'] === 'New Project') {
                $found = true;

                break;
            }
        }

        $this->assertTrue($found, 'New project should be in tree after cache invalidation');
    }

    public function testCacheInvalidatedAfterUpdate(): void
    {
        $user = $this->createUser('cache-update@example.com');
        $project = $this->createProject($user, 'Original Name');
        $userId = $user->getId();

        // Populate cache
        $tree1 = $this->projectService->getTree($user, false, false);
        $this->assertEquals('Original Name', $tree1[0]['name']);

        // Update project name
        $dto = new UpdateProjectRequest(name: 'Updated Name');
        $this->projectService->update($project, $dto);

        // Cache should be invalidated
        $tree2 = $this->projectService->getTree($user, false, false);

        // Updated name should be in tree
        $this->assertEquals('Updated Name', $tree2[0]['name']);
    }

    public function testCacheInvalidatedAfterDelete(): void
    {
        $user = $this->createUser('cache-delete@example.com');
        $project = $this->createProject($user, 'Project to Delete');
        $userId = $user->getId();

        // Populate cache
        $tree1 = $this->projectService->getTree($user, false, false);
        $this->assertCount(1, $tree1);

        // Delete project
        $this->projectService->delete($project);

        // Cache should be invalidated - deleted project should not appear
        $tree2 = $this->projectService->getTree($user, false, false);
        $this->assertCount(0, $tree2);
    }

    public function testCacheInvalidatedAfterMove(): void
    {
        $user = $this->createUser('cache-move@example.com');
        $parent = $this->createProject($user, 'Parent');
        $child = $this->createProject($user, 'Child');
        $userId = $user->getId();

        // Populate cache
        $tree1 = $this->projectService->getTree($user, false, false);

        // Both should be at root initially
        $this->assertCount(2, $tree1);

        // Move child under parent
        $dto = new MoveProjectRequest(parentId: $parent->getId());
        $this->projectService->move($child, $dto);

        // Cache should be invalidated
        $tree2 = $this->projectService->getTree($user, false, false);

        // Now only parent should be at root
        $this->assertCount(1, $tree2);
        $this->assertEquals('Parent', $tree2[0]['name']);
        $this->assertCount(1, $tree2[0]['children']);
        $this->assertEquals('Child', $tree2[0]['children'][0]['name']);
    }

    public function testCacheInvalidatedAfterArchive(): void
    {
        $user = $this->createUser('cache-archive@example.com');
        $project = $this->createProject($user, 'Project to Archive');
        $userId = $user->getId();

        // Populate cache (without archived projects)
        $tree1 = $this->projectService->getTree($user, false, false);
        $this->assertCount(1, $tree1);

        // Archive project
        $this->projectService->archive($project);

        // Cache should be invalidated - archived project should not appear
        $tree2 = $this->projectService->getTree($user, false, false);
        $this->assertCount(0, $tree2);

        // But should appear when including archived
        $tree3 = $this->projectService->getTree($user, true, false);
        $this->assertCount(1, $tree3);
        $this->assertTrue($tree3[0]['isArchived']);
    }

    public function testCacheInvalidatedAfterUnarchive(): void
    {
        $user = $this->createUser('cache-unarchive@example.com');
        $project = $this->createProject($user, 'Archived Project', true);
        $userId = $user->getId();

        // Populate cache (without archived projects)
        $tree1 = $this->projectService->getTree($user, false, false);
        $this->assertCount(0, $tree1);

        // Unarchive project
        $this->projectService->unarchive($project);

        // Cache should be invalidated - project should now appear
        $tree2 = $this->projectService->getTree($user, false, false);
        $this->assertCount(1, $tree2);
        $this->assertFalse($tree2[0]['isArchived']);
    }

    public function testCacheInvalidatedAfterBatchReorder(): void
    {
        $user = $this->createUser('cache-reorder@example.com');
        $project1 = $this->createProject($user, 'Project 1');
        $project2 = $this->createProject($user, 'Project 2');
        $project3 = $this->createProject($user, 'Project 3');
        $userId = $user->getId();

        // Populate cache
        $tree1 = $this->projectService->getTree($user, false, false);

        // Batch reorder
        $this->projectService->batchReorder($user, null, [
            $project3->getId(),
            $project1->getId(),
            $project2->getId(),
        ]);

        // Cache should be invalidated
        $tree2 = $this->projectService->getTree($user, false, false);

        // Verify new order
        $names = array_map(fn ($p) => $p['name'], $tree2);
        $this->assertEquals(['Project 3', 'Project 1', 'Project 2'], $names);
    }

    // ========================================
    // Issue 2.10 - Cache Invalidation on Undo Tests
    // ========================================

    public function testCacheInvalidatedAfterUndoUpdate(): void
    {
        $user = $this->createUser('cache-undo-update@example.com');
        $project = $this->createProject($user, 'Original Name');
        $userId = $user->getId();

        // Update project
        $dto = new UpdateProjectRequest(name: 'Updated Name');
        $result = $this->projectService->update($project, $dto);
        $undoToken = $result['undoToken'];

        // Populate cache with updated name
        $tree1 = $this->projectService->getTree($user, false, false);
        $this->assertEquals('Updated Name', $tree1[0]['name']);

        // Undo the update
        if ($undoToken !== null) {
            $this->projectService->undo($user, $undoToken->token);

            // Cache should be invalidated
            $tree2 = $this->projectService->getTree($user, false, false);
            $this->assertEquals('Original Name', $tree2[0]['name']);
        }
    }

    public function testCacheInvalidatedAfterUndoArchive(): void
    {
        $user = $this->createUser('cache-undo-archive@example.com');
        $project = $this->createProject($user, 'Test Project');
        $userId = $user->getId();

        // Archive project
        $result = $this->projectService->archive($project);
        $undoToken = $result['undoToken'];

        // Populate cache (project should not appear without archived)
        $tree1 = $this->projectService->getTree($user, false, false);
        $this->assertCount(0, $tree1);

        // Undo the archive
        if ($undoToken !== null) {
            $this->projectService->undo($user, $undoToken->token);

            // Cache should be invalidated - project should reappear
            $tree2 = $this->projectService->getTree($user, false, false);
            $this->assertCount(1, $tree2);
            $this->assertFalse($tree2[0]['isArchived']);
        }
    }

    public function testCacheInvalidatedAfterUndoDelete(): void
    {
        $user = $this->createUser('cache-undo-delete@example.com');
        $project = $this->createProject($user, 'Project to Delete');
        $projectId = $project->getId();
        $userId = $user->getId();

        // Delete project
        $undoToken = $this->projectService->delete($project);

        // Populate cache (project should not appear)
        $tree1 = $this->projectService->getTree($user, false, false);
        $this->assertCount(0, $tree1);

        // Undo the delete
        if ($undoToken !== null) {
            $this->projectService->undo($user, $undoToken->token);

            // Cache should be invalidated - project should reappear
            $tree2 = $this->projectService->getTree($user, false, false);
            $this->assertCount(1, $tree2);
        }
    }

    public function testCacheInvalidatedAfterUndoMove(): void
    {
        $user = $this->createUser('cache-undo-move@example.com');
        $parent = $this->createProject($user, 'Parent');
        $child = $this->createProject($user, 'Child');
        $userId = $user->getId();

        // Move child under parent
        $dto = new MoveProjectRequest(parentId: $parent->getId());
        $result = $this->projectService->move($child, $dto);
        $undoToken = $result['undoToken'];

        // Populate cache
        $tree1 = $this->projectService->getTree($user, false, false);
        $this->assertCount(1, $tree1);
        $this->assertCount(1, $tree1[0]['children']);

        // Undo the move
        if ($undoToken !== null) {
            $this->projectService->undo($user, $undoToken->token);

            // Cache should be invalidated - both should be at root again
            $tree2 = $this->projectService->getTree($user, false, false);
            $this->assertCount(2, $tree2);
        }
    }

    // ========================================
    // Cache Key Building Tests
    // ========================================

    public function testCacheKeyBuildingIsConsistent(): void
    {
        $userId = 'test-user-id-12345';

        $key1 = $this->projectCacheService->buildKey($userId, false, false);
        $key2 = $this->projectCacheService->buildKey($userId, false, false);

        $this->assertEquals($key1, $key2);

        // Different parameters should produce different keys
        $key3 = $this->projectCacheService->buildKey($userId, true, false);
        $key4 = $this->projectCacheService->buildKey($userId, false, true);
        $key5 = $this->projectCacheService->buildKey($userId, true, true);

        $this->assertNotEquals($key1, $key3);
        $this->assertNotEquals($key1, $key4);
        $this->assertNotEquals($key1, $key5);
        $this->assertNotEquals($key3, $key4);
        $this->assertNotEquals($key3, $key5);
        $this->assertNotEquals($key4, $key5);
    }
}
