<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Exception\ProjectCannotBeOwnParentException;
use App\Exception\ProjectCircularReferenceException;
use App\Exception\ProjectHierarchyTooDeepException;
use App\Tests\Unit\UnitTestCase;

class ProjectTest extends UnitTestCase
{
    // ========================================
    // Is Archived Tests
    // ========================================

    public function testIsArchivedDefaultsToFalse(): void
    {
        $project = new Project();

        $this->assertFalse($project->isArchived());
    }

    public function testSetIsArchivedToTrue(): void
    {
        $project = new Project();

        $project->setIsArchived(true);

        $this->assertTrue($project->isArchived());
    }

    public function testSetIsArchivedToFalse(): void
    {
        $project = new Project();
        $project->setIsArchived(true);

        $project->setIsArchived(false);

        $this->assertFalse($project->isArchived());
    }

    public function testSetIsArchivedReturnsSelf(): void
    {
        $project = new Project();

        $result = $project->setIsArchived(true);

        $this->assertSame($project, $result);
    }

    // ========================================
    // Archive / Unarchive Helper Tests
    // ========================================

    public function testArchiveProject(): void
    {
        $project = new Project();
        $this->assertFalse($project->isArchived());

        $project->setIsArchived(true);

        $this->assertTrue($project->isArchived());
    }

    public function testUnarchiveProject(): void
    {
        $project = new Project();
        $project->setIsArchived(true);
        $this->assertTrue($project->isArchived());

        $project->setIsArchived(false);

        $this->assertFalse($project->isArchived());
    }

    // ========================================
    // Task Collection Tests
    // ========================================

    public function testGetTasksReturnsEmptyCollectionByDefault(): void
    {
        $project = new Project();

        $tasks = $project->getTasks();

        $this->assertCount(0, $tasks);
    }

    public function testAddTaskAddsToCollection(): void
    {
        $project = new Project();
        $project->setName('Test Project');
        $task = new Task();
        $task->setTitle('Test Task');

        $project->addTask($task);

        $this->assertCount(1, $project->getTasks());
        $this->assertTrue($project->getTasks()->contains($task));
    }

    public function testAddTaskSetsProjectOnTask(): void
    {
        $project = new Project();
        $project->setName('Test Project');
        $task = new Task();
        $task->setTitle('Test Task');

        $project->addTask($task);

        $this->assertSame($project, $task->getProject());
    }

    public function testAddTaskReturnsSelf(): void
    {
        $project = new Project();
        $project->setName('Test Project');
        $task = new Task();
        $task->setTitle('Test Task');

        $result = $project->addTask($task);

        $this->assertSame($project, $result);
    }

    public function testAddTaskDoesNotAddDuplicates(): void
    {
        $project = new Project();
        $project->setName('Test Project');
        $task = new Task();
        $task->setTitle('Test Task');

        $project->addTask($task);
        $project->addTask($task);

        $this->assertCount(1, $project->getTasks());
    }

    public function testRemoveTaskRemovesFromCollection(): void
    {
        $project = new Project();
        $project->setName('Test Project');
        $task = new Task();
        $task->setTitle('Test Task');
        $project->addTask($task);

        $project->removeTask($task);

        $this->assertCount(0, $project->getTasks());
        $this->assertFalse($project->getTasks()->contains($task));
    }

    public function testRemoveTaskClearsProjectOnTask(): void
    {
        $project = new Project();
        $project->setName('Test Project');
        $task = new Task();
        $task->setTitle('Test Task');
        $project->addTask($task);

        $project->removeTask($task);

        $this->assertNull($task->getProject());
    }

    public function testRemoveTaskReturnsSelf(): void
    {
        $project = new Project();
        $project->setName('Test Project');
        $task = new Task();
        $task->setTitle('Test Task');
        $project->addTask($task);

        $result = $project->removeTask($task);

        $this->assertSame($project, $result);
    }

    public function testCanAddMultipleTasks(): void
    {
        $project = new Project();
        $project->setName('Test Project');
        $task1 = new Task();
        $task1->setTitle('Task 1');
        $task2 = new Task();
        $task2->setTitle('Task 2');
        $task3 = new Task();
        $task3->setTitle('Task 3');

        $project->addTask($task1);
        $project->addTask($task2);
        $project->addTask($task3);

        $this->assertCount(3, $project->getTasks());
    }

    // ========================================
    // Name Tests
    // ========================================

    public function testSetNameReturnsSelf(): void
    {
        $project = new Project();

        $result = $project->setName('Test Project');

        $this->assertSame($project, $result);
    }

    public function testGetNameReturnsSetValue(): void
    {
        $project = new Project();

        $project->setName('My Project');

        $this->assertEquals('My Project', $project->getName());
    }

    // ========================================
    // Description Tests
    // ========================================

    public function testDescriptionDefaultsToNull(): void
    {
        $project = new Project();
        $project->setName('Test');

        $this->assertNull($project->getDescription());
    }

    public function testSetDescriptionReturnsSelf(): void
    {
        $project = new Project();
        $project->setName('Test');

        $result = $project->setDescription('A description');

        $this->assertSame($project, $result);
    }

    public function testSetDescriptionCanSetNull(): void
    {
        $project = new Project();
        $project->setName('Test');
        $project->setDescription('Some description');

        $project->setDescription(null);

        $this->assertNull($project->getDescription());
    }

    public function testGetDescriptionReturnsSetValue(): void
    {
        $project = new Project();
        $project->setName('Test');

        $project->setDescription('Project description');

        $this->assertEquals('Project description', $project->getDescription());
    }

    // ========================================
    // Owner Tests
    // ========================================

    public function testGetOwnerReturnsNullByDefault(): void
    {
        $project = new Project();
        $project->setName('Test');

        $this->assertNull($project->getOwner());
    }

    public function testSetOwnerAssignsOwner(): void
    {
        $project = new Project();
        $project->setName('Test');
        $user = new User();
        $user->setEmail('test@example.com');

        $project->setOwner($user);

        $this->assertSame($user, $project->getOwner());
    }

    public function testSetOwnerReturnsSelf(): void
    {
        $project = new Project();
        $project->setName('Test');
        $user = new User();
        $user->setEmail('test@example.com');

        $result = $project->setOwner($user);

        $this->assertSame($project, $result);
    }

    public function testSetOwnerCanSetNull(): void
    {
        $project = new Project();
        $project->setName('Test');
        $user = new User();
        $user->setEmail('test@example.com');
        $project->setOwner($user);

        $project->setOwner(null);

        $this->assertNull($project->getOwner());
    }

    // ========================================
    // ID Tests
    // ========================================

    public function testGetIdReturnsNullForNewProject(): void
    {
        $project = new Project();
        $project->setName('Test');

        $this->assertNull($project->getId());
    }

    public function testGetIdReturnsSetValue(): void
    {
        $project = $this->createProjectWithId('project-123');

        $this->assertEquals('project-123', $project->getId());
    }

    // ========================================
    // Timestamp Tests
    // ========================================

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $project = new Project();
        $after = new \DateTimeImmutable();

        $this->assertInstanceOf(\DateTimeImmutable::class, $project->getCreatedAt());
        $this->assertGreaterThanOrEqual($before, $project->getCreatedAt());
        $this->assertLessThanOrEqual($after, $project->getCreatedAt());
    }

    public function testUpdatedAtIsSetOnConstruction(): void
    {
        $project = new Project();

        $this->assertInstanceOf(\DateTimeImmutable::class, $project->getUpdatedAt());
    }

    public function testSetCreatedAtReturnsSelf(): void
    {
        $project = new Project();
        $project->setName('Test');
        $date = new \DateTimeImmutable('2024-01-01');

        $result = $project->setCreatedAt($date);

        $this->assertSame($project, $result);
        $this->assertEquals($date, $project->getCreatedAt());
    }

    public function testSetUpdatedAtReturnsSelf(): void
    {
        $project = new Project();
        $project->setName('Test');
        $date = new \DateTimeImmutable('2024-01-01');

        $result = $project->setUpdatedAt($date);

        $this->assertSame($project, $result);
        $this->assertEquals($date, $project->getUpdatedAt());
    }

    // ========================================
    // UserOwnedInterface Tests
    // ========================================

    public function testImplementsUserOwnedInterface(): void
    {
        $project = new Project();

        $this->assertInstanceOf(\App\Interface\UserOwnedInterface::class, $project);
    }

    public function testGetOwnerFromUserOwnedInterface(): void
    {
        $project = new Project();
        $project->setName('Test');
        $user = new User();
        $user->setEmail('test@example.com');
        $project->setOwner($user);

        // getOwner() is defined by UserOwnedInterface
        $this->assertSame($user, $project->getOwner());
    }

    // ========================================
    // Parent/Hierarchy Tests
    // ========================================

    public function testGetParentReturnsNullByDefault(): void
    {
        $project = new Project();
        $project->setName('Test');

        $this->assertNull($project->getParent());
    }

    public function testSetParentAssignsParent(): void
    {
        $user = $this->createUserWithId('user-123');
        $parent = $this->createProjectWithId('parent-123', $user);
        $child = $this->createProjectWithId('child-123', $user);

        $child->setParent($parent);

        $this->assertSame($parent, $child->getParent());
    }

    public function testSetParentToNullRemovesParent(): void
    {
        $user = $this->createUserWithId('user-123');
        $parent = $this->createProjectWithId('parent-123', $user);
        $child = $this->createProjectWithId('child-123', $user);
        $child->setParent($parent);

        $child->setParent(null);

        $this->assertNull($child->getParent());
    }

    public function testSetParentToSelfThrowsException(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user);

        $this->expectException(ProjectCannotBeOwnParentException::class);

        $project->setParent($project);
    }

    public function testSetParentToDescendantThrowsCircularReferenceException(): void
    {
        $user = $this->createUserWithId('user-123');
        $grandparent = $this->createProjectWithId('grandparent-123', $user);
        $parent = $this->createProjectWithId('parent-123', $user);
        $child = $this->createProjectWithId('child-123', $user);

        $parent->setParent($grandparent);
        $child->setParent($parent);

        $this->expectException(ProjectCircularReferenceException::class);

        $grandparent->setParent($child);
    }

    public function testSetParentBeyondMaxDepthThrowsException(): void
    {
        $user = $this->createUserWithId('user-123');

        // Create a chain of projects at MAX_HIERARCHY_DEPTH - 1
        $projects = [];
        $projects[0] = $this->createProjectWithId('project-0', $user);
        $projects[0]->setName('Root');

        for ($i = 1; $i < Project::MAX_HIERARCHY_DEPTH; $i++) {
            $projects[$i] = $this->createProjectWithId("project-{$i}", $user);
            $projects[$i]->setName("Level {$i}");
            $projects[$i]->setParent($projects[$i - 1]);
        }

        // Now try to add one more level - this should throw
        $tooDeep = $this->createProjectWithId('project-too-deep', $user);
        $tooDeep->setName('Too Deep');

        $this->expectException(ProjectHierarchyTooDeepException::class);

        $tooDeep->setParent($projects[Project::MAX_HIERARCHY_DEPTH - 1]);
    }

    public function testSetParentWithDifferentOwnerThrowsException(): void
    {
        $user1 = $this->createUserWithId('user-1');
        $user2 = $this->createUserWithId('user-2');
        $project1 = $this->createProjectWithId('project-1', $user1);
        $project2 = $this->createProjectWithId('project-2', $user2);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('same owner');

        $project2->setParent($project1);
    }

    public function testGetDepthReturnsZeroForRootProject(): void
    {
        $project = new Project();
        $project->setName('Root');

        $this->assertEquals(0, $project->getDepth());
    }

    public function testGetDepthReturnsCorrectDepth(): void
    {
        $user = $this->createUserWithId('user-123');
        $root = $this->createProjectWithId('root', $user);
        $level1 = $this->createProjectWithId('level1', $user);
        $level2 = $this->createProjectWithId('level2', $user);

        $level1->setParent($root);
        $level2->setParent($level1);

        $this->assertEquals(0, $root->getDepth());
        $this->assertEquals(1, $level1->getDepth());
        $this->assertEquals(2, $level2->getDepth());
    }

    public function testGetFullPath(): void
    {
        $user = $this->createUserWithId('user-123');
        $root = $this->createProjectWithId('root', $user);
        $root->setName('Work');
        $child = $this->createProjectWithId('child', $user);
        $child->setName('Backend');
        $child->setParent($root);

        $this->assertEquals('Work/Backend', $child->getFullPath());
    }

    public function testIsDescendantOf(): void
    {
        $user = $this->createUserWithId('user-123');
        $grandparent = $this->createProjectWithId('grandparent', $user);
        $parent = $this->createProjectWithId('parent', $user);
        $child = $this->createProjectWithId('child', $user);

        $parent->setParent($grandparent);
        $child->setParent($parent);

        $this->assertTrue($child->isDescendantOf($grandparent));
        $this->assertTrue($child->isDescendantOf($parent));
        $this->assertFalse($grandparent->isDescendantOf($child));
        $this->assertFalse($parent->isDescendantOf($child));
    }

    public function testIsAncestorOf(): void
    {
        $user = $this->createUserWithId('user-123');
        $grandparent = $this->createProjectWithId('grandparent', $user);
        $parent = $this->createProjectWithId('parent', $user);
        $child = $this->createProjectWithId('child', $user);

        $parent->setParent($grandparent);
        $child->setParent($parent);

        $this->assertTrue($grandparent->isAncestorOf($child));
        $this->assertTrue($parent->isAncestorOf($child));
        $this->assertFalse($child->isAncestorOf($grandparent));
        $this->assertFalse($child->isAncestorOf($parent));
    }

    public function testGetChildren(): void
    {
        $user = $this->createUserWithId('user-123');
        $parent = $this->createProjectWithId('parent', $user);
        $child1 = $this->createProjectWithId('child1', $user);
        $child2 = $this->createProjectWithId('child2', $user);

        $parent->addChild($child1);
        $parent->addChild($child2);

        $this->assertCount(2, $parent->getChildren());
        $this->assertTrue($parent->getChildren()->contains($child1));
        $this->assertTrue($parent->getChildren()->contains($child2));
    }

    public function testGetAncestors(): void
    {
        $user = $this->createUserWithId('user-123');
        $grandparent = $this->createProjectWithId('grandparent', $user);
        $grandparent->setName('GP');
        $parent = $this->createProjectWithId('parent', $user);
        $parent->setName('P');
        $child = $this->createProjectWithId('child', $user);
        $child->setName('C');

        $parent->setParent($grandparent);
        $child->setParent($parent);

        $ancestors = $child->getAncestors();

        $this->assertCount(2, $ancestors);
        $this->assertSame($grandparent, $ancestors[0]);
        $this->assertSame($parent, $ancestors[1]);
    }

    public function testGetAllDescendants(): void
    {
        $user = $this->createUserWithId('user-123');
        $grandparent = $this->createProjectWithId('grandparent', $user);
        $parent = $this->createProjectWithId('parent', $user);
        $child1 = $this->createProjectWithId('child1', $user);
        $child2 = $this->createProjectWithId('child2', $user);

        $grandparent->addChild($parent);
        $parent->addChild($child1);
        $parent->addChild($child2);

        $descendants = $grandparent->getAllDescendants();

        $this->assertCount(3, $descendants);
        $this->assertContains($parent, $descendants);
        $this->assertContains($child1, $descendants);
        $this->assertContains($child2, $descendants);
    }

    // ========================================
    // ShowChildrenTasks Tests
    // ========================================

    public function testIsShowChildrenTasksDefaultsToTrue(): void
    {
        $project = new Project();

        $this->assertTrue($project->isShowChildrenTasks());
    }

    public function testSetShowChildrenTasks(): void
    {
        $project = new Project();

        $project->setShowChildrenTasks(false);

        $this->assertFalse($project->isShowChildrenTasks());
    }

    public function testSetShowChildrenTasksReturnsSelf(): void
    {
        $project = new Project();

        $result = $project->setShowChildrenTasks(false);

        $this->assertSame($project, $result);
    }

    // ========================================
    // Position Tests
    // ========================================

    public function testGetPositionDefaultsToZero(): void
    {
        $project = new Project();

        $this->assertEquals(0, $project->getPosition());
    }

    public function testSetPosition(): void
    {
        $project = new Project();

        $project->setPosition(5);

        $this->assertEquals(5, $project->getPosition());
    }

    public function testSetPositionReturnsSelf(): void
    {
        $project = new Project();

        $result = $project->setPosition(3);

        $this->assertSame($project, $result);
    }

    // ========================================
    // Soft Delete Tests
    // ========================================

    public function testIsDeletedDefaultsToFalse(): void
    {
        $project = new Project();

        $this->assertFalse($project->isDeleted());
    }

    public function testSoftDelete(): void
    {
        $project = new Project();

        $project->softDelete();

        $this->assertTrue($project->isDeleted());
        $this->assertNotNull($project->getDeletedAt());
    }

    public function testRestore(): void
    {
        $project = new Project();
        $project->softDelete();

        $project->restore();

        $this->assertFalse($project->isDeleted());
        $this->assertNull($project->getDeletedAt());
    }

    public function testSoftDeleteReturnsSelf(): void
    {
        $project = new Project();

        $result = $project->softDelete();

        $this->assertSame($project, $result);
    }

    public function testRestoreReturnsSelf(): void
    {
        $project = new Project();
        $project->softDelete();

        $result = $project->restore();

        $this->assertSame($project, $result);
    }
}
