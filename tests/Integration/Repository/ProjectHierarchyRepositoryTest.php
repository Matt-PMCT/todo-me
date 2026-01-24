<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Repository\ProjectRepository;
use App\Tests\Integration\IntegrationTestCase;

class ProjectHierarchyRepositoryTest extends IntegrationTestCase
{
    private ProjectRepository $projectRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectRepository = static::getContainer()->get(ProjectRepository::class);
    }

    // ========================================
    // getTreeByUser Tests
    // ========================================

    public function testGetTreeByUserReturnsEmptyArrayForNewUser(): void
    {
        $user = $this->createUser('tree-empty@example.com');

        $tree = $this->projectRepository->getTreeByUser($user);

        $this->assertIsArray($tree);
        $this->assertEmpty($tree);
    }

    public function testGetTreeByUserReturnsAllProjects(): void
    {
        $user = $this->createUser('tree-all@example.com');
        $project1 = $this->createProject($user, 'Project 1');
        $project2 = $this->createProject($user, 'Project 2');

        $tree = $this->projectRepository->getTreeByUser($user);

        $this->assertCount(2, $tree);
    }

    public function testGetTreeByUserExcludesArchivedByDefault(): void
    {
        $user = $this->createUser('tree-archived@example.com');
        $this->createProject($user, 'Active Project', false);
        $this->createProject($user, 'Archived Project', true);

        $tree = $this->projectRepository->getTreeByUser($user, false);

        $this->assertCount(1, $tree);
        $this->assertEquals('Active Project', $tree[0]->getName());
    }

    public function testGetTreeByUserIncludesArchivedWhenRequested(): void
    {
        $user = $this->createUser('tree-include-archived@example.com');
        $this->createProject($user, 'Active Project', false);
        $this->createProject($user, 'Archived Project', true);

        $tree = $this->projectRepository->getTreeByUser($user, true);

        $this->assertCount(2, $tree);
    }

    // ========================================
    // getDescendantIds Tests
    // ========================================

    public function testGetDescendantIdsReturnsEmptyForLeafProject(): void
    {
        $user = $this->createUser('descendants-leaf@example.com');
        $project = $this->createProject($user, 'Leaf Project');

        $descendants = $this->projectRepository->getDescendantIds($project);

        $this->assertIsArray($descendants);
        $this->assertEmpty($descendants);
    }

    public function testGetDescendantIdsReturnsDirectChildren(): void
    {
        $user = $this->createUser('descendants-direct@example.com');
        $parent = $this->createProject($user, 'Parent');
        $child1 = $this->createProject($user, 'Child 1', false, $parent);
        $child2 = $this->createProject($user, 'Child 2', false, $parent);

        $descendants = $this->projectRepository->getDescendantIds($parent);

        $this->assertCount(2, $descendants);
        $this->assertContains($child1->getId(), $descendants);
        $this->assertContains($child2->getId(), $descendants);
    }

    public function testGetDescendantIdsReturnsAllNestedDescendants(): void
    {
        $user = $this->createUser('descendants-nested@example.com');
        $root = $this->createProject($user, 'Root');
        $child = $this->createProject($user, 'Child', false, $root);
        $grandchild = $this->createProject($user, 'Grandchild', false, $child);

        $descendants = $this->projectRepository->getDescendantIds($root);

        $this->assertCount(2, $descendants);
        $this->assertContains($child->getId(), $descendants);
        $this->assertContains($grandchild->getId(), $descendants);
    }

    // ========================================
    // getAncestorIds Tests
    // ========================================

    public function testGetAncestorIdsReturnsEmptyForRootProject(): void
    {
        $user = $this->createUser('ancestors-root@example.com');
        $project = $this->createProject($user, 'Root Project');

        $ancestors = $this->projectRepository->getAncestorIds($project);

        $this->assertIsArray($ancestors);
        $this->assertEmpty($ancestors);
    }

    public function testGetAncestorIdsReturnsParent(): void
    {
        $user = $this->createUser('ancestors-parent@example.com');
        $parent = $this->createProject($user, 'Parent');
        $child = $this->createProject($user, 'Child', false, $parent);

        $ancestors = $this->projectRepository->getAncestorIds($child);

        $this->assertCount(1, $ancestors);
        $this->assertContains($parent->getId(), $ancestors);
    }

    public function testGetAncestorIdsReturnsAllAncestors(): void
    {
        $user = $this->createUser('ancestors-all@example.com');
        $grandparent = $this->createProject($user, 'Grandparent');
        $parent = $this->createProject($user, 'Parent', false, $grandparent);
        $child = $this->createProject($user, 'Child', false, $parent);

        $ancestors = $this->projectRepository->getAncestorIds($child);

        $this->assertCount(2, $ancestors);
        $this->assertContains($grandparent->getId(), $ancestors);
        $this->assertContains($parent->getId(), $ancestors);
    }

    // ========================================
    // findRootsByOwner Tests
    // ========================================

    public function testFindRootsByOwnerReturnsOnlyRootProjects(): void
    {
        $user = $this->createUser('roots-only@example.com');
        $root1 = $this->createProject($user, 'Root 1');
        $root2 = $this->createProject($user, 'Root 2');
        $child = $this->createProject($user, 'Child', false, $root1);

        $roots = $this->projectRepository->findRootsByOwner($user);

        $this->assertCount(2, $roots);
        $rootIds = array_map(fn($p) => $p->getId(), $roots);
        $this->assertContains($root1->getId(), $rootIds);
        $this->assertContains($root2->getId(), $rootIds);
        $this->assertNotContains($child->getId(), $rootIds);
    }

    public function testFindRootsByOwnerExcludesArchivedByDefault(): void
    {
        $user = $this->createUser('roots-archived@example.com');
        $this->createProject($user, 'Active Root', false);
        $this->createProject($user, 'Archived Root', true);

        $roots = $this->projectRepository->findRootsByOwner($user, false);

        $this->assertCount(1, $roots);
        $this->assertEquals('Active Root', $roots[0]->getName());
    }

    // ========================================
    // getMaxPositionInParent Tests
    // ========================================

    public function testGetMaxPositionInParentReturnsNegativeOneForEmpty(): void
    {
        $user = $this->createUser('max-pos-empty@example.com');

        $maxPosition = $this->projectRepository->getMaxPositionInParent($user, null);

        $this->assertEquals(-1, $maxPosition);
    }

    public function testGetMaxPositionInParentReturnsMaxPositionForRoots(): void
    {
        $user = $this->createUser('max-pos-roots@example.com');
        $project1 = $this->createProject($user, 'Project 1');
        $project1->setPosition(0);
        $project2 = $this->createProject($user, 'Project 2');
        $project2->setPosition(5);
        $this->entityManager->flush();

        $maxPosition = $this->projectRepository->getMaxPositionInParent($user, null);

        $this->assertEquals(5, $maxPosition);
    }

    public function testGetMaxPositionInParentReturnsMaxPositionForParent(): void
    {
        $user = $this->createUser('max-pos-parent@example.com');
        $parent = $this->createProject($user, 'Parent');
        $child1 = $this->createProject($user, 'Child 1', false, $parent);
        $child1->setPosition(0);
        $child2 = $this->createProject($user, 'Child 2', false, $parent);
        $child2->setPosition(3);
        $this->entityManager->flush();

        $maxPosition = $this->projectRepository->getMaxPositionInParent($user, $parent->getId());

        $this->assertEquals(3, $maxPosition);
    }

    // ========================================
    // normalizePositions Tests
    // ========================================

    public function testNormalizePositionsMakesPositionsSequential(): void
    {
        $user = $this->createUser('normalize-pos@example.com');
        $project1 = $this->createProject($user, 'Project 1');
        $project1->setPosition(5);
        $project2 = $this->createProject($user, 'Project 2');
        $project2->setPosition(10);
        $project3 = $this->createProject($user, 'Project 3');
        $project3->setPosition(15);
        $this->entityManager->flush();

        $this->projectRepository->normalizePositions($user, null);

        $this->entityManager->clear();
        $projects = $this->projectRepository->findRootsByOwner($user);

        $positions = array_map(fn($p) => $p->getPosition(), $projects);
        sort($positions);

        $this->assertEquals([0, 1, 2], $positions);
    }

    // ========================================
    // findChildrenByParent Tests
    // ========================================

    public function testFindChildrenByParentReturnsOnlyDirectChildren(): void
    {
        $user = $this->createUser('children-direct@example.com');
        $parent = $this->createProject($user, 'Parent');
        $child1 = $this->createProject($user, 'Child 1', false, $parent);
        $child2 = $this->createProject($user, 'Child 2', false, $parent);
        $grandchild = $this->createProject($user, 'Grandchild', false, $child1);

        $children = $this->projectRepository->findChildrenByParent($parent);

        $this->assertCount(2, $children);
        $childIds = array_map(fn($p) => $p->getId(), $children);
        $this->assertContains($child1->getId(), $childIds);
        $this->assertContains($child2->getId(), $childIds);
        $this->assertNotContains($grandchild->getId(), $childIds);
    }

    public function testFindChildrenByParentExcludesArchivedByDefault(): void
    {
        $user = $this->createUser('children-archived@example.com');
        $parent = $this->createProject($user, 'Parent');
        $this->createProject($user, 'Active Child', false, $parent);
        $this->createProject($user, 'Archived Child', true, $parent);

        $children = $this->projectRepository->findChildrenByParent($parent, false);

        $this->assertCount(1, $children);
        $this->assertEquals('Active Child', $children[0]->getName());
    }

    // ========================================
    // getTreeWithTaskCounts Tests
    // ========================================

    public function testGetTreeWithTaskCountsReturnsTaskCounts(): void
    {
        $user = $this->createUser('tree-counts@example.com');
        $project = $this->createProject($user, 'Project');

        // Create some tasks
        $this->createTask($user, 'Task 1', 'pending', 3, $project);
        $this->createTask($user, 'Task 2', 'completed', 3, $project);
        $this->createTask($user, 'Task 3', 'completed', 3, $project);

        $taskCounts = $this->projectRepository->getTreeWithTaskCounts($user);

        $this->assertArrayHasKey($project->getId(), $taskCounts);
        $this->assertEquals(3, $taskCounts[$project->getId()]['total']);
        $this->assertEquals(2, $taskCounts[$project->getId()]['completed']);
    }
}
