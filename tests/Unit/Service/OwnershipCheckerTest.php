<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\User;
use App\Exception\ForbiddenException;
use App\Exception\UnauthorizedException;
use App\Interface\UserOwnedInterface;
use App\Service\OwnershipChecker;
use App\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Unit tests for OwnershipChecker service.
 *
 * Tests multi-tenant access control logic including:
 * - Ownership verification
 * - Authentication enforcement
 * - Current user retrieval
 */
class OwnershipCheckerTest extends UnitTestCase
{
    private Security&MockObject $security;
    private OwnershipChecker $ownershipChecker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->security = $this->createMock(Security::class);
        $this->ownershipChecker = new OwnershipChecker($this->security);
    }

    // =========================================================================
    // isOwner() Method Tests
    // =========================================================================

    public function testIsOwnerReturnsTrueWhenUserOwnsEntity(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-456', $user);

        $result = $this->ownershipChecker->isOwner($task, $user);

        $this->assertTrue($result);
    }

    public function testIsOwnerReturnsFalseWhenUserDoesNotOwnEntity(): void
    {
        $owner = $this->createUserWithId('user-123');
        $otherUser = $this->createUserWithId('user-456', 'other@example.com');
        $task = $this->createTaskWithId('task-789', $owner);

        $result = $this->ownershipChecker->isOwner($task, $otherUser);

        $this->assertFalse($result);
    }

    public function testIsOwnerReturnsFalseWhenEntityHasNullOwner(): void
    {
        $user = $this->createUserWithId('user-123');

        // Create entity with null owner using mock
        $entity = $this->createMock(UserOwnedInterface::class);
        $entity->method('getOwner')->willReturn(null);

        $result = $this->ownershipChecker->isOwner($entity, $user);

        $this->assertFalse($result);
    }

    public function testIsOwnerWorksWithTaskEntity(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-456', $user);

        $this->assertTrue($this->ownershipChecker->isOwner($task, $user));
    }

    public function testIsOwnerWorksWithProjectEntity(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-456', $user);

        $this->assertTrue($this->ownershipChecker->isOwner($project, $user));
    }

    public function testIsOwnerWithDifferentUserObjectsSameId(): void
    {
        // Create two different User objects with the same ID
        $user1 = $this->createUserWithId('user-123', 'user1@example.com');
        $user2 = $this->createUserWithId('user-123', 'user2@example.com');

        $task = $this->createTaskWithId('task-456', $user1);

        // Should return true because IDs match, not object references
        $result = $this->ownershipChecker->isOwner($task, $user2);

        $this->assertTrue($result);
    }

    // =========================================================================
    // ensureAuthenticated() Method Tests
    // =========================================================================

    public function testEnsureAuthenticatedReturnsUserWhenAuthenticated(): void
    {
        $user = $this->createUserWithId('user-123');
        $this->security->method('getUser')->willReturn($user);

        $result = $this->ownershipChecker->ensureAuthenticated();

        $this->assertSame($user, $result);
    }

    public function testEnsureAuthenticatedThrowsExceptionWhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Authentication credentials were not provided');

        $this->ownershipChecker->ensureAuthenticated();
    }

    public function testEnsureAuthenticatedThrowsExceptionWhenNonUserPrincipal(): void
    {
        // Mock a generic UserInterface that is NOT our User entity
        $nonUserPrincipal = $this->createMock(UserInterface::class);
        $this->security->method('getUser')->willReturn($nonUserPrincipal);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage('Authentication credentials were not provided');

        $this->ownershipChecker->ensureAuthenticated();
    }

    // =========================================================================
    // getCurrentUser() Method Tests
    // =========================================================================

    public function testGetCurrentUserReturnsUserWhenAuthenticated(): void
    {
        $user = $this->createUserWithId('user-123');
        $this->security->method('getUser')->willReturn($user);

        $result = $this->ownershipChecker->getCurrentUser();

        $this->assertSame($user, $result);
    }

    public function testGetCurrentUserReturnsNullWhenNotAuthenticated(): void
    {
        $this->security->method('getUser')->willReturn(null);

        $result = $this->ownershipChecker->getCurrentUser();

        $this->assertNull($result);
    }

    public function testGetCurrentUserReturnsNullForNonUserPrincipal(): void
    {
        $nonUserPrincipal = $this->createMock(UserInterface::class);
        $this->security->method('getUser')->willReturn($nonUserPrincipal);

        $result = $this->ownershipChecker->getCurrentUser();

        $this->assertNull($result);
    }

    // =========================================================================
    // checkOwnership() Method Tests
    // =========================================================================

    public function testCheckOwnershipSucceedsForValidOwner(): void
    {
        $this->expectNotToPerformAssertions();

        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-456', $user);

        $this->security->method('getUser')->willReturn($user);

        // Should not throw any exception
        $this->ownershipChecker->checkOwnership($task);
    }

    public function testCheckOwnershipThrowsForbiddenExceptionForNonOwner(): void
    {
        $owner = $this->createUserWithId('user-123');
        $currentUser = $this->createUserWithId('user-456', 'other@example.com');
        $task = $this->createTaskWithId('task-789', $owner);

        $this->security->method('getUser')->willReturn($currentUser);

        $this->expectException(ForbiddenException::class);

        $this->ownershipChecker->checkOwnership($task);
    }

    public function testCheckOwnershipThrowsUnauthorizedExceptionWhenNotAuthenticated(): void
    {
        $owner = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-456', $owner);

        $this->security->method('getUser')->willReturn(null);

        $this->expectException(UnauthorizedException::class);

        $this->ownershipChecker->checkOwnership($task);
    }

    public function testCheckOwnershipExceptionContainsTaskTypeName(): void
    {
        $owner = $this->createUserWithId('user-123');
        $currentUser = $this->createUserWithId('user-456', 'other@example.com');
        $task = $this->createTaskWithId('task-789', $owner);

        $this->security->method('getUser')->willReturn($currentUser);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('You do not have permission to access this Task');

        $this->ownershipChecker->checkOwnership($task);
    }

    public function testCheckOwnershipExceptionContainsProjectTypeName(): void
    {
        $owner = $this->createUserWithId('user-123');
        $currentUser = $this->createUserWithId('user-456', 'other@example.com');
        $project = $this->createProjectWithId('project-789', $owner);

        $this->security->method('getUser')->willReturn($currentUser);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('You do not have permission to access this Project');

        $this->ownershipChecker->checkOwnership($project);
    }

    public function testCheckOwnershipWithNullEntityOwnerThrowsForbiddenException(): void
    {
        $currentUser = $this->createUserWithId('user-123');

        // Create entity with null owner using mock
        $entity = $this->createMock(UserOwnedInterface::class);
        $entity->method('getOwner')->willReturn(null);

        $this->security->method('getUser')->willReturn($currentUser);

        $this->expectException(ForbiddenException::class);

        $this->ownershipChecker->checkOwnership($entity);
    }
}
