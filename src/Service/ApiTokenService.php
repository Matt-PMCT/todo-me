<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Exception\EntityNotFoundException;
use App\Exception\ForbiddenException;
use App\Interface\ApiTokenServiceInterface;
use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing API tokens.
 */
final class ApiTokenService implements ApiTokenServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ApiTokenRepository $tokenRepository,
    ) {
    }

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
    ): array {
        $plainToken = $this->generateToken();

        $token = new ApiToken();
        $token->setOwner($user);
        $token->setName($name);
        $token->setTokenHash(hash('sha256', $plainToken));
        $token->setTokenPrefix(substr($plainToken, 0, 8));
        $token->setScopes($scopes);
        $token->setExpiresAt($expiresAt);

        $this->entityManager->persist($token);
        $this->entityManager->flush();

        return [
            'token' => $token,
            'plainToken' => $plainToken,
        ];
    }

    /**
     * Generates a secure random token.
     *
     * Format: tm_ prefix + 64 hex characters from random_bytes(32)
     */
    private function generateToken(): string
    {
        return 'tm_' . bin2hex(random_bytes(32));
    }

    /**
     * Lists all tokens for a user.
     *
     * @return ApiToken[]
     */
    public function listTokens(User $user): array
    {
        return $this->tokenRepository->findByOwner($user);
    }

    /**
     * Revokes (deletes) a token.
     *
     * @throws EntityNotFoundException If token not found
     * @throws ForbiddenException If user doesn't own the token
     */
    public function revokeToken(User $user, string $tokenId): void
    {
        $token = $this->tokenRepository->find($tokenId);

        if ($token === null) {
            throw EntityNotFoundException::forResource('ApiToken', $tokenId);
        }

        if ($token->getOwner()?->getId() !== $user->getId()) {
            throw ForbiddenException::notOwner('ApiToken');
        }

        $this->entityManager->remove($token);
        $this->entityManager->flush();
    }

    /**
     * Finds a valid token by its plain token value.
     *
     * Used during authentication to look up and validate tokens.
     */
    public function findValidToken(string $plainToken): ?ApiToken
    {
        $tokenHash = hash('sha256', $plainToken);

        return $this->tokenRepository->findValidByTokenHash($tokenHash);
    }

    /**
     * Updates the last used timestamp for a token.
     */
    public function updateLastUsed(ApiToken $token): void
    {
        $token->setLastUsedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    /**
     * Finds a token by ID and verifies ownership.
     *
     * @throws EntityNotFoundException If token not found
     * @throws ForbiddenException If user doesn't own the token
     */
    public function findByIdOrFail(string $id, User $user): ApiToken
    {
        $token = $this->tokenRepository->find($id);

        if ($token === null) {
            throw EntityNotFoundException::forResource('ApiToken', $id);
        }

        if ($token->getOwner()?->getId() !== $user->getId()) {
            throw ForbiddenException::notOwner('ApiToken');
        }

        return $token;
    }
}
