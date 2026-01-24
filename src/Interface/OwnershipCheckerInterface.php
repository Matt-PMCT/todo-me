<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;

/**
 * Interface for ownership checking services.
 */
interface OwnershipCheckerInterface
{
    /**
     * Checks if the current user owns the entity.
     *
     * @param UserOwnedInterface $entity The entity to check ownership of
     *
     * @throws \App\Exception\UnauthorizedException If no user is authenticated
     * @throws \App\Exception\ForbiddenException If the current user does not own the entity
     */
    public function checkOwnership(UserOwnedInterface $entity): void;

    /**
     * Checks if a specific user owns the entity.
     *
     * @param UserOwnedInterface $entity The entity to check
     * @param User $user The user to check ownership for
     *
     * @return bool True if the user owns the entity
     */
    public function isOwner(UserOwnedInterface $entity, User $user): bool;

    /**
     * Ensures a user is authenticated and returns the user.
     *
     * @throws \App\Exception\UnauthorizedException If no user is authenticated
     *
     * @return User The authenticated user
     */
    public function ensureAuthenticated(): User;

    /**
     * Gets the current authenticated user or null.
     *
     * @return User|null The authenticated user or null if not authenticated
     */
    public function getCurrentUser(): ?User;
}
