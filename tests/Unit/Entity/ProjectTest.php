<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
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
}
