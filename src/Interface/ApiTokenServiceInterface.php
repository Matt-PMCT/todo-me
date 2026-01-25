<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Exception\EntityNotFoundException;
use App\Exception\ForbiddenException;

/**
 * Interface for API token management service.
 */
interface ApiTokenServiceInterface
{
    /**
     * Creates a new API token.
     *
     * Returns the plain token - it can only be shown once!
     *
     * @param User $user The token owner
     * @param string $name User-provided name for the token
     * @param string[] $scopes Token scopes (default: ['*'] for all access)
     * @param \DateTimeImmutable|null $expiresAt Optional expiration date
     * @return array{token: ApiToken, plainToken: string}
     */
    public function createToken(
        User $user,
        string $name,
        array $scopes = ['*'],
        ?\DateTimeImmutable $expiresAt = null
    ): array;

    /**
     * Lists all tokens for a user.
     *
     * @return ApiToken[]
     */
    public function listTokens(User $user): array;

    /**
     * Revokes (deletes) a token.
     *
     * @throws EntityNotFoundException If token not found
     * @throws ForbiddenException If user doesn't own the token
     */
    public function revokeToken(User $user, string $tokenId): void;

    /**
     * Finds a valid token by its plain token value.
     *
     * Used during authentication to look up and validate tokens.
     */
    public function findValidToken(string $plainToken): ?ApiToken;

    /**
     * Updates the last used timestamp for a token.
     */
    public function updateLastUsed(ApiToken $token): void;

    /**
     * Finds a token by ID and verifies ownership.
     *
     * @throws EntityNotFoundException If token not found
     * @throws ForbiddenException If user doesn't own the token
     */
    public function findByIdOrFail(string $id, User $user): ApiToken;
}
