<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\ForbiddenException;
use App\Exception\UnauthorizedException;
use App\Interface\UserOwnedInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Service for checking entity ownership.
 *
 * Provides methods to verify that the current authenticated user
 * owns a specific entity.
 */
class OwnershipChecker
{
    public function __construct(
        private Security $security,
    ) {
    }

    /**
     * Checks if the current user owns the entity.
     *
     * @param UserOwnedInterface $entity The entity to check ownership of
     *
     * @throws UnauthorizedException If no user is authenticated
     * @throws ForbiddenException If the current user does not own the entity
     */
    public function checkOwnership(UserOwnedInterface $entity): void
    {
        $currentUser = $this->ensureAuthenticated();

        if (!$this->isOwner($entity, $currentUser)) {
            $entityType = $this->getEntityTypeName($entity);
            throw ForbiddenException::notOwner($entityType);
        }
    }

    /**
     * Checks if a specific user owns the entity.
     *
     * @param UserOwnedInterface $entity The entity to check
     * @param User $user The user to check ownership for
     *
     * @return bool True if the user owns the entity
     */
    public function isOwner(UserOwnedInterface $entity, User $user): bool
    {
        $owner = $entity->getOwner();

        if ($owner === null) {
            return false;
        }

        return $owner->getId() === $user->getId();
    }

    /**
     * Ensures a user is authenticated and returns the user.
     *
     * @throws UnauthorizedException If no user is authenticated
     *
     * @return User The authenticated user
     */
    public function ensureAuthenticated(): User
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw UnauthorizedException::missingCredentials();
        }

        return $user;
    }

    /**
     * Gets the current authenticated user or null.
     *
     * @return User|null The authenticated user or null if not authenticated
     */
    public function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    /**
     * Gets the short class name for an entity (e.g., "Task" from "App\Entity\Task").
     */
    private function getEntityTypeName(UserOwnedInterface $entity): string
    {
        $className = $entity::class;
        $parts = explode('\\', $className);

        return end($parts);
    }
}
