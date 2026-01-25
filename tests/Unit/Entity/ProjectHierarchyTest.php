<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Project;
use App\Exception\ProjectCannotBeOwnParentException;
use App\Exception\ProjectCircularReferenceException;
use App\Tests\Unit\UnitTestCase;

class ProjectHierarchyTest extends UnitTestCase
{
    // ========================================
    // Depth Tests
    // ========================================

    public function testGetDepthReturnsZeroForRootProject(): void
    {
        $project = new Project();
        $project->setName('Root Project');

        $this->assertEquals(0, $project->getDepth());
    }

    public function testGetDepthReturnsOneForFirstLevelChild(): void
    {
        $parent = new Project();
        $parent->setName('Parent');

        $child = new Project();
        $child->setName('Child');
        $child->setParent($parent);

        $this->assertEquals(1, $child->getDepth());
    }

    public function testGetDepthReturnsTwoForSecondLevelChild(): void
    {
        $grandparent = new Project();
        $grandparent->setName('Grandparent');

        $parent = new Project();
        $parent->setName('Parent');
        $parent->setParent($grandparent);

        $child = new Project();
        $child->setName('Child');
        $child->setParent($parent);

        $this->assertEquals(2, $child->getDepth());
    }

    // ========================================
    // Ancestors Tests
    // ========================================

    public function testGetAncestorsReturnsEmptyArrayForRootProject(): void
    {
        $project = new Project();
        $project->setName('Root');

        $ancestors = $project->getAncestors();

        $this->assertIsArray($ancestors);
        $this->assertEmpty($ancestors);
    }

    public function testGetAncestorsReturnsParentForFirstLevelChild(): void
    {
        $parent = new Project();
        $parent->setName('Parent');

        $child = new Project();
        $child->setName('Child');
        $child->setParent($parent);

        $ancestors = $child->getAncestors();

        $this->assertCount(1, $ancestors);
        $this->assertSame($parent, $ancestors[0]);
    }

    public function testGetAncestorsReturnsOrderedFromRootToParent(): void
    {
        $grandparent = new Project();
        $grandparent->setName('Grandparent');

        $parent = new Project();
        $parent->setName('Parent');
        $parent->setParent($grandparent);

        $child = new Project();
        $child->setName('Child');
        $child->setParent($parent);

        $ancestors = $child->getAncestors();

        $this->assertCount(2, $ancestors);
        $this->assertSame($grandparent, $ancestors[0]);
        $this->assertSame($parent, $ancestors[1]);
    }

    // ========================================
    // Path Tests
    // ========================================

    public function testGetPathReturnsOwnNameForRootProject(): void
    {
        $project = new Project();
        $project->setName('Root');

        $path = $project->getPath();

        $this->assertEquals(['Root'], $path);
    }

    public function testGetPathReturnsFullPathFromRoot(): void
    {
        $grandparent = new Project();
        $grandparent->setName('Work');

        $parent = new Project();
        $parent->setName('Backend');
        $parent->setParent($grandparent);

        $child = new Project();
        $child->setName('API');
        $child->setParent($parent);

        $path = $child->getPath();

        $this->assertEquals(['Work', 'Backend', 'API'], $path);
    }

    // ========================================
    // isDescendantOf Tests
    // ========================================

    public function testIsDescendantOfReturnsFalseForSameProject(): void
    {
        $project = $this->createProjectWithId('project-1');
        $project->setName('Project');

        $this->assertFalse($project->isDescendantOf($project));
    }

    public function testIsDescendantOfReturnsTrueForDirectChild(): void
    {
        $parent = $this->createProjectWithId('parent-1');
        $parent->setName('Parent');

        $child = $this->createProjectWithId('child-1');
        $child->setName('Child');
        $child->setParent($parent);

        $this->assertTrue($child->isDescendantOf($parent));
    }

    public function testIsDescendantOfReturnsTrueForIndirectDescendant(): void
    {
        $grandparent = $this->createProjectWithId('grandparent-1');
        $grandparent->setName('Grandparent');

        $parent = $this->createProjectWithId('parent-1');
        $parent->setName('Parent');
        $parent->setParent($grandparent);

        $child = $this->createProjectWithId('child-1');
        $child->setName('Child');
        $child->setParent($parent);

        $this->assertTrue($child->isDescendantOf($grandparent));
    }

    public function testIsDescendantOfReturnsFalseForUnrelatedProjects(): void
    {
        $project1 = $this->createProjectWithId('project-1');
        $project1->setName('Project 1');

        $project2 = $this->createProjectWithId('project-2');
        $project2->setName('Project 2');

        $this->assertFalse($project1->isDescendantOf($project2));
        $this->assertFalse($project2->isDescendantOf($project1));
    }

    public function testIsDescendantOfReturnsFalseForAncestor(): void
    {
        $parent = $this->createProjectWithId('parent-1');
        $parent->setName('Parent');

        $child = $this->createProjectWithId('child-1');
        $child->setName('Child');
        $child->setParent($parent);

        $this->assertFalse($parent->isDescendantOf($child));
    }

    // ========================================
    // isAncestorOf Tests
    // ========================================

    public function testIsAncestorOfReturnsTrueForDirectParent(): void
    {
        $parent = $this->createProjectWithId('parent-1');
        $parent->setName('Parent');

        $child = $this->createProjectWithId('child-1');
        $child->setName('Child');
        $child->setParent($parent);

        $this->assertTrue($parent->isAncestorOf($child));
    }

    public function testIsAncestorOfReturnsTrueForIndirectAncestor(): void
    {
        $grandparent = $this->createProjectWithId('grandparent-1');
        $grandparent->setName('Grandparent');

        $parent = $this->createProjectWithId('parent-1');
        $parent->setName('Parent');
        $parent->setParent($grandparent);

        $child = $this->createProjectWithId('child-1');
        $child->setName('Child');
        $child->setParent($parent);

        $this->assertTrue($grandparent->isAncestorOf($child));
    }

    public function testIsAncestorOfReturnsFalseForDescendant(): void
    {
        $parent = $this->createProjectWithId('parent-1');
        $parent->setName('Parent');

        $child = $this->createProjectWithId('child-1');
        $child->setName('Child');
        $child->setParent($parent);

        $this->assertFalse($child->isAncestorOf($parent));
    }

    // ========================================
    // getAllDescendants Tests
    // ========================================

    public function testGetAllDescendantsReturnsEmptyArrayForLeafProject(): void
    {
        $project = new Project();
        $project->setName('Leaf');

        $descendants = $project->getAllDescendants();

        $this->assertIsArray($descendants);
        $this->assertEmpty($descendants);
    }

    public function testGetAllDescendantsReturnsDirectChildren(): void
    {
        $parent = new Project();
        $parent->setName('Parent');

        $child1 = new Project();
        $child1->setName('Child 1');
        $parent->addChild($child1);

        $child2 = new Project();
        $child2->setName('Child 2');
        $parent->addChild($child2);

        $descendants = $parent->getAllDescendants();

        $this->assertCount(2, $descendants);
        $this->assertContains($child1, $descendants);
        $this->assertContains($child2, $descendants);
    }

    public function testGetAllDescendantsReturnsAllNestedDescendants(): void
    {
        $root = new Project();
        $root->setName('Root');

        $child = new Project();
        $child->setName('Child');
        $root->addChild($child);

        $grandchild = new Project();
        $grandchild->setName('Grandchild');
        $child->addChild($grandchild);

        $descendants = $root->getAllDescendants();

        $this->assertCount(2, $descendants);
        $this->assertContains($child, $descendants);
        $this->assertContains($grandchild, $descendants);
    }

    // ========================================
    // setParent Circular Reference Prevention Tests
    // ========================================

    public function testSetParentToNullSucceeds(): void
    {
        $parent = $this->createProjectWithId('parent-1');
        $parent->setName('Parent');

        $child = $this->createProjectWithId('child-1');
        $child->setName('Child');
        $child->setParent($parent);

        $child->setParent(null);

        $this->assertNull($child->getParent());
    }

    public function testSetParentToSelfThrowsException(): void
    {
        $project = $this->createProjectWithId('project-1');
        $project->setName('Project');

        $this->expectException(ProjectCannotBeOwnParentException::class);
        $project->setParent($project);
    }

    public function testSetParentToDescendantThrowsException(): void
    {
        $parent = $this->createProjectWithId('parent-1');
        $parent->setName('Parent');

        $child = $this->createProjectWithId('child-1');
        $child->setName('Child');
        $child->setParent($parent);

        $grandchild = $this->createProjectWithId('grandchild-1');
        $grandchild->setName('Grandchild');
        $grandchild->setParent($child);

        $this->expectException(ProjectCircularReferenceException::class);
        $parent->setParent($grandchild);
    }

    public function testSetParentToValidProjectSucceeds(): void
    {
        $parent = $this->createProjectWithId('parent-1');
        $parent->setName('Parent');

        $child = $this->createProjectWithId('child-1');
        $child->setName('Child');

        $child->setParent($parent);

        $this->assertSame($parent, $child->getParent());
    }

    // ========================================
    // getPathDetails Tests
    // ========================================

    public function testGetPathDetailsReturnsCorrectStructure(): void
    {
        $parent = $this->createProjectWithId('parent-1');
        $parent->setName('Parent');
        $parent->setIsArchived(false);

        $child = $this->createProjectWithId('child-1');
        $child->setName('Child');
        $child->setIsArchived(true);
        $child->setParent($parent);

        $pathDetails = $child->getPathDetails();

        $this->assertCount(2, $pathDetails);

        $this->assertEquals('parent-1', $pathDetails[0]['id']);
        $this->assertEquals('Parent', $pathDetails[0]['name']);
        $this->assertFalse($pathDetails[0]['isArchived']);

        $this->assertEquals('child-1', $pathDetails[1]['id']);
        $this->assertEquals('Child', $pathDetails[1]['name']);
        $this->assertTrue($pathDetails[1]['isArchived']);
    }
}
