<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;

/**
 * Interface for entities that are owned by a user.
 *
 * Entities implementing this interface can be checked for ownership
 * using the OwnershipChecker service.
 */
interface UserOwnedInterface
{
    /**
     * Gets the owner of this entity.
     */
    public function getOwner(): ?User;

    /**
     * Sets the owner of this entity.
     */
    public function setOwner(?User $owner): static;
}
