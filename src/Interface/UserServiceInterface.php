<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;

/**
 * Interface for user-related operations needed by authentication.
 */
interface UserServiceInterface
{
    /**
     * Finds a user by API token without checking expiration.
     */
    public function findByApiTokenIgnoreExpiration(string $token): ?User;
}
