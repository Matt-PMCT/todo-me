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
    // API Token Hash Tests
    // ========================================

    public function testGetApiTokenHashReturnsSetValue(): void
    {
        $user = new User();
        $tokenHash = hash('sha256', 'api_token_123');

        $user->setApiTokenHash($tokenHash);

        $this->assertEquals($tokenHash, $user->getApiTokenHash());
    }

    public function testSetApiTokenHashReturnsSelf(): void
    {
        $user = new User();

        $result = $user->setApiTokenHash(hash('sha256', 'token'));

        $this->assertSame($user, $result);
    }

    public function testApiTokenHashDefaultsToNull(): void
    {
        $user = new User();

        $this->assertNull($user->getApiTokenHash());
    }

    public function testSetApiTokenHashCanSetNull(): void
    {
        $user = new User();
        $user->setApiTokenHash(hash('sha256', 'some_token'));

        $user->setApiTokenHash(null);

        $this->assertNull($user->getApiTokenHash());
    }

    // ========================================
    // API Token Expiration Tests
    // ========================================

    public function testIsApiTokenExpiredReturnsTrueWhenNoExpiration(): void
    {
        $user = new User();
        $user->setApiTokenHash(hash('sha256', 'token'));
        // No expiration set

        $this->assertTrue($user->isApiTokenExpired());
    }

    public function testIsApiTokenExpiredReturnsTrueWhenExpired(): void
    {
        $user = new User();
        $user->setApiTokenHash(hash('sha256', 'token'));
        $user->setApiTokenExpiresAt(new \DateTimeImmutable('-1 hour'));

        $this->assertTrue($user->isApiTokenExpired());
    }

    public function testIsApiTokenExpiredReturnsFalseWhenNotExpired(): void
    {
        $user = new User();
        $user->setApiTokenHash(hash('sha256', 'token'));
        $user->setApiTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        $this->assertFalse($user->isApiTokenExpired());
    }

    public function testSetApiTokenHashNullClearsExpiration(): void
    {
        $user = new User();
        $user->setApiTokenHash(hash('sha256', 'token'));
        $user->setApiTokenIssuedAt(new \DateTimeImmutable());
        $user->setApiTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        $user->setApiTokenHash(null);

        $this->assertNull($user->getApiTokenHash());
        $this->assertNull($user->getApiTokenIssuedAt());
        $this->assertNull($user->getApiTokenExpiresAt());
    }

    public function testGetApiTokenIssuedAtReturnsSetValue(): void
    {
        $user = new User();
        $date = new \DateTimeImmutable('2024-01-15 10:00:00');

        $user->setApiTokenIssuedAt($date);

        $this->assertSame($date, $user->getApiTokenIssuedAt());
    }

    public function testGetApiTokenExpiresAtReturnsSetValue(): void
    {
        $user = new User();
        $date = new \DateTimeImmutable('2024-01-17 10:00:00');

        $user->setApiTokenExpiresAt($date);

        $this->assertSame($date, $user->getApiTokenExpiresAt());
    }

    public function testSetApiTokenIssuedAtReturnsSelf(): void
    {
        $user = new User();

        $result = $user->setApiTokenIssuedAt(new \DateTimeImmutable());

        $this->assertSame($user, $result);
    }

    public function testSetApiTokenExpiresAtReturnsSelf(): void
    {
        $user = new User();

        $result = $user->setApiTokenExpiresAt(new \DateTimeImmutable());

        $this->assertSame($user, $result);
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
        $this->expectNotToPerformAssertions();

        $user = new User();

        // eraseCredentials is called by Symfony after authentication
        // It should not throw and should clear any temporary sensitive data
        $user->eraseCredentials();
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
