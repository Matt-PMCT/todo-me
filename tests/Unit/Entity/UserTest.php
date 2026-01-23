<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\User;
use App\Tests\Unit\UnitTestCase;

class UserTest extends UnitTestCase
{
    // ========================================
    // Password Hash Tests
    // ========================================

    public function testGetPasswordHashReturnsSetValue(): void
    {
        $user = new User();
        $hash = 'hashed_password_value';

        $user->setPasswordHash($hash);

        $this->assertEquals($hash, $user->getPasswordHash());
    }

    public function testSetPasswordHashReturnsSelf(): void
    {
        $user = new User();

        $result = $user->setPasswordHash('hash');

        $this->assertSame($user, $result);
    }

    public function testGetPasswordReturnsPasswordHash(): void
    {
        $user = new User();
        $hash = 'hashed_password';

        $user->setPasswordHash($hash);

        // getPassword() is required by PasswordAuthenticatedUserInterface
        $this->assertEquals($hash, $user->getPassword());
    }

    // ========================================
    // API Token Tests
    // ========================================

    public function testGetApiTokenReturnsSetValue(): void
    {
        $user = new User();
        $token = 'api_token_123';

        $user->setApiToken($token);

        $this->assertEquals($token, $user->getApiToken());
    }

    public function testSetApiTokenReturnsSelf(): void
    {
        $user = new User();

        $result = $user->setApiToken('token');

        $this->assertSame($user, $result);
    }

    public function testApiTokenDefaultsToNull(): void
    {
        $user = new User();

        $this->assertNull($user->getApiToken());
    }

    public function testSetApiTokenCanSetNull(): void
    {
        $user = new User();
        $user->setApiToken('some_token');

        $user->setApiToken(null);

        $this->assertNull($user->getApiToken());
    }

    // ========================================
    // Roles Tests
    // ========================================

    public function testGetRolesReturnsRoleUser(): void
    {
        $user = new User();

        $roles = $user->getRoles();

        $this->assertIsArray($roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User();

        // Even for a fresh user, ROLE_USER should be present
        $roles = $user->getRoles();

        $this->assertEquals(['ROLE_USER'], $roles);
    }

    // ========================================
    // User Identifier Tests
    // ========================================

    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new User();
        $email = 'test@example.com';

        $user->setEmail($email);

        $this->assertEquals($email, $user->getUserIdentifier());
    }

    public function testUserIdentifierIsEmail(): void
    {
        $user = new User();
        $user->setEmail('unique@example.com');

        // UserInterface requires getUserIdentifier
        $this->assertEquals('unique@example.com', $user->getUserIdentifier());
        $this->assertEquals($user->getEmail(), $user->getUserIdentifier());
    }

    // ========================================
    // Erase Credentials Tests
    // ========================================

    public function testEraseCredentialsDoesNotThrow(): void
    {
        $user = new User();

        // eraseCredentials is called by Symfony after authentication
        // It should not throw and should clear any temporary sensitive data
        $user->eraseCredentials();

        $this->assertTrue(true);
    }

    public function testEraseCredentialsPreservesPasswordHash(): void
    {
        $user = new User();
        $user->setPasswordHash('stored_hash');

        $user->eraseCredentials();

        // Password hash should still be available (it's not temporary)
        $this->assertEquals('stored_hash', $user->getPasswordHash());
    }

    // ========================================
    // Email Tests
    // ========================================

    public function testGetEmailReturnsSetValue(): void
    {
        $user = new User();
        $email = 'user@example.com';

        $user->setEmail($email);

        $this->assertEquals($email, $user->getEmail());
    }

    public function testSetEmailReturnsSelf(): void
    {
        $user = new User();

        $result = $user->setEmail('test@example.com');

        $this->assertSame($user, $result);
    }

    // ========================================
    // ID Tests
    // ========================================

    public function testGetIdReturnsNullForNewUser(): void
    {
        $user = new User();

        $this->assertNull($user->getId());
    }

    public function testGetIdReturnsSetValue(): void
    {
        $user = $this->createUserWithId('user-123');

        $this->assertEquals('user-123', $user->getId());
    }

    // ========================================
    // Timestamp Tests
    // ========================================

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $user = new User();
        $after = new \DateTimeImmutable();

        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
        $this->assertGreaterThanOrEqual($before, $user->getCreatedAt());
        $this->assertLessThanOrEqual($after, $user->getCreatedAt());
    }

    public function testUpdatedAtIsSetOnConstruction(): void
    {
        $user = new User();

        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getUpdatedAt());
    }

    public function testSetCreatedAtReturnsSelf(): void
    {
        $user = new User();
        $date = new \DateTimeImmutable('2024-01-01');

        $result = $user->setCreatedAt($date);

        $this->assertSame($user, $result);
    }

    public function testSetUpdatedAtReturnsSelf(): void
    {
        $user = new User();
        $date = new \DateTimeImmutable('2024-01-01');

        $result = $user->setUpdatedAt($date);

        $this->assertSame($user, $result);
    }

    // ========================================
    // Project Collection Tests
    // ========================================

    public function testGetProjectsReturnsEmptyCollectionByDefault(): void
    {
        $user = new User();

        $projects = $user->getProjects();

        $this->assertCount(0, $projects);
    }

    public function testAddProjectAddsToCollection(): void
    {
        $user = new User();
        $project = new Project();

        $user->addProject($project);

        $this->assertCount(1, $user->getProjects());
        $this->assertTrue($user->getProjects()->contains($project));
    }

    public function testAddProjectSetsOwnerOnProject(): void
    {
        $user = new User();
        $project = new Project();

        $user->addProject($project);

        $this->assertSame($user, $project->getOwner());
    }

    public function testRemoveProjectRemovesFromCollection(): void
    {
        $user = new User();
        $project = new Project();
        $user->addProject($project);

        $user->removeProject($project);

        $this->assertCount(0, $user->getProjects());
    }

    // ========================================
    // Task Collection Tests
    // ========================================

    public function testGetTasksReturnsEmptyCollectionByDefault(): void
    {
        $user = new User();

        $tasks = $user->getTasks();

        $this->assertCount(0, $tasks);
    }

    public function testAddTaskAddsToCollection(): void
    {
        $user = new User();
        $task = new Task();
        $task->setTitle('Test');

        $user->addTask($task);

        $this->assertCount(1, $user->getTasks());
        $this->assertTrue($user->getTasks()->contains($task));
    }

    public function testAddTaskSetsOwnerOnTask(): void
    {
        $user = new User();
        $task = new Task();
        $task->setTitle('Test');

        $user->addTask($task);

        $this->assertSame($user, $task->getOwner());
    }

    public function testRemoveTaskRemovesFromCollection(): void
    {
        $user = new User();
        $task = new Task();
        $task->setTitle('Test');
        $user->addTask($task);

        $user->removeTask($task);

        $this->assertCount(0, $user->getTasks());
    }

    // ========================================
    // Tag Collection Tests
    // ========================================

    public function testGetTagsReturnsEmptyCollectionByDefault(): void
    {
        $user = new User();

        $tags = $user->getTags();

        $this->assertCount(0, $tags);
    }

    public function testAddTagAddsToCollection(): void
    {
        $user = new User();
        $tag = new Tag();
        $tag->setName('Test');

        $user->addTag($tag);

        $this->assertCount(1, $user->getTags());
        $this->assertTrue($user->getTags()->contains($tag));
    }

    public function testRemoveTagRemovesFromCollection(): void
    {
        $user = new User();
        $tag = new Tag();
        $tag->setName('Test');
        $user->addTag($tag);

        $user->removeTag($tag);

        $this->assertCount(0, $user->getTags());
    }
}
