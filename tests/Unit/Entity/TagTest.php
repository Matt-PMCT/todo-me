<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\User;
use App\Tests\Unit\UnitTestCase;

class TagTest extends UnitTestCase
{
    // ========================================
    // Name Tests
    // ========================================

    public function testSetNameReturnsSelf(): void
    {
        $tag = new Tag();

        $result = $tag->setName('Test Tag');

        $this->assertSame($tag, $result);
    }

    public function testGetNameReturnsSetValue(): void
    {
        $tag = new Tag();

        $tag->setName('My Tag');

        $this->assertEquals('My Tag', $tag->getName());
    }

    // ========================================
    // Color Tests
    // ========================================

    public function testGetColorReturnsDefault(): void
    {
        $tag = new Tag();

        $this->assertEquals('#6B7280', $tag->getColor());
    }

    public function testSetColorReturnsSelf(): void
    {
        $tag = new Tag();

        $result = $tag->setColor('#FF0000');

        $this->assertSame($tag, $result);
    }

    public function testSetColorAcceptsValidHexColor(): void
    {
        $tag = new Tag();

        $tag->setColor('#FF5733');

        $this->assertEquals('#FF5733', $tag->getColor());
    }

    public function testSetColorConvertsToUppercase(): void
    {
        $tag = new Tag();

        $tag->setColor('#ff5733');

        $this->assertEquals('#FF5733', $tag->getColor());
    }

    public function testSetColorRejectsInvalidFormat(): void
    {
        $tag = new Tag();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid color format');
        $tag->setColor('red');
    }

    public function testSetColorRejectsShortHex(): void
    {
        $tag = new Tag();

        $this->expectException(\InvalidArgumentException::class);
        $tag->setColor('#FFF');
    }

    public function testSetColorRejectsMissingHash(): void
    {
        $tag = new Tag();

        $this->expectException(\InvalidArgumentException::class);
        $tag->setColor('FF5733');
    }

    public function testSetColorRejectsInvalidCharacters(): void
    {
        $tag = new Tag();

        $this->expectException(\InvalidArgumentException::class);
        $tag->setColor('#GGGGGG');
    }

    // ========================================
    // Owner Tests
    // ========================================

    public function testGetOwnerReturnsNullByDefault(): void
    {
        $tag = new Tag();

        $this->assertNull($tag->getOwner());
    }

    public function testSetOwnerAssignsOwner(): void
    {
        $tag = new Tag();
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hashed');
        $user->setUsername('testuser');

        $tag->setOwner($user);

        $this->assertSame($user, $tag->getOwner());
    }

    public function testSetOwnerReturnsSelf(): void
    {
        $tag = new Tag();
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hashed');
        $user->setUsername('testuser');

        $result = $tag->setOwner($user);

        $this->assertSame($tag, $result);
    }

    public function testSetOwnerCanSetNull(): void
    {
        $tag = new Tag();
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPasswordHash('hashed');
        $user->setUsername('testuser');
        $tag->setOwner($user);

        $tag->setOwner(null);

        $this->assertNull($tag->getOwner());
    }

    // ========================================
    // Task Collection Tests
    // ========================================

    public function testGetTasksReturnsEmptyCollectionByDefault(): void
    {
        $tag = new Tag();

        $tasks = $tag->getTasks();

        $this->assertCount(0, $tasks);
    }

    public function testAddTaskAddsToCollection(): void
    {
        $tag = new Tag();
        $tag->setName('Test');
        $task = new Task();
        $task->setTitle('Test Task');

        $tag->addTask($task);

        $this->assertCount(1, $tag->getTasks());
        $this->assertTrue($tag->getTasks()->contains($task));
    }

    public function testAddTaskReturnsSelf(): void
    {
        $tag = new Tag();
        $tag->setName('Test');
        $task = new Task();
        $task->setTitle('Test Task');

        $result = $tag->addTask($task);

        $this->assertSame($tag, $result);
    }

    public function testAddTaskDoesNotAddDuplicates(): void
    {
        $tag = new Tag();
        $tag->setName('Test');
        $task = new Task();
        $task->setTitle('Test Task');

        $tag->addTask($task);
        $tag->addTask($task);

        $this->assertCount(1, $tag->getTasks());
    }

    public function testAddTaskAddsTagToTask(): void
    {
        $tag = new Tag();
        $tag->setName('Test');
        $task = new Task();
        $task->setTitle('Test Task');

        $tag->addTask($task);

        $this->assertTrue($task->getTags()->contains($tag));
    }

    public function testRemoveTaskRemovesFromCollection(): void
    {
        $tag = new Tag();
        $tag->setName('Test');
        $task = new Task();
        $task->setTitle('Test Task');
        $tag->addTask($task);

        $tag->removeTask($task);

        $this->assertCount(0, $tag->getTasks());
        $this->assertFalse($tag->getTasks()->contains($task));
    }

    public function testRemoveTaskReturnsSelf(): void
    {
        $tag = new Tag();
        $tag->setName('Test');
        $task = new Task();
        $task->setTitle('Test Task');
        $tag->addTask($task);

        $result = $tag->removeTask($task);

        $this->assertSame($tag, $result);
    }

    public function testRemoveTaskRemovesTagFromTask(): void
    {
        $tag = new Tag();
        $tag->setName('Test');
        $task = new Task();
        $task->setTitle('Test Task');
        $tag->addTask($task);

        $tag->removeTask($task);

        $this->assertFalse($task->getTags()->contains($tag));
    }

    // ========================================
    // Timestamp Tests
    // ========================================

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $tag = new Tag();
        $after = new \DateTimeImmutable();

        $this->assertInstanceOf(\DateTimeImmutable::class, $tag->getCreatedAt());
        $this->assertGreaterThanOrEqual($before, $tag->getCreatedAt());
        $this->assertLessThanOrEqual($after, $tag->getCreatedAt());
    }

    public function testSetCreatedAtReturnsSelf(): void
    {
        $tag = new Tag();
        $date = new \DateTimeImmutable('2024-01-01');

        $result = $tag->setCreatedAt($date);

        $this->assertSame($tag, $result);
        $this->assertEquals($date, $tag->getCreatedAt());
    }

    // ========================================
    // ID Tests
    // ========================================

    public function testGetIdReturnsNullWhenNotPersisted(): void
    {
        $tag = new Tag();

        $this->assertNull($tag->getId());
    }
}
