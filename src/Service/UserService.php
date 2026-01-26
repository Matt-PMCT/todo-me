<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SavedFilter;
use App\Entity\User;
use App\Interface\UserServiceInterface;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service for user management operations.
 */
final class UserService implements UserServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TokenGenerator $tokenGenerator,
        private readonly UserRepository $userRepository,
        private readonly int $apiTokenTtlHours = 48,
    ) {
    }

    /**
     * Registers a new user.
     *
     * @param string $email         The user's email address
     * @param string $plainPassword The user's plain text password
     *
     * @return array{user: User, token: string} The newly created user and their plaintext API token
     */
    public function register(string $email, string $plainPassword): array
    {
        $user = new User();
        $user->setEmail($email);

        // Generate username from email prefix with random suffix for uniqueness
        $emailPrefix = explode('@', $email)[0];
        $user->setUsername($emailPrefix.'_'.substr(bin2hex(random_bytes(4)), 0, 8));

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPasswordHash($hashedPassword);

        $plainToken = $this->setNewApiToken($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return ['user' => $user, 'token' => $plainToken];
    }

    /**
     * Generates a new API token for the user.
     *
     * @param User $user The user to generate a new token for
     *
     * @return string The new API token (plaintext, only returned once)
     */
    public function generateNewApiToken(User $user): string
    {
        $plainToken = $this->setNewApiToken($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $plainToken;
    }

    /**
     * Sets a new API token with expiration on the user.
     *
     * @return string The plaintext token (only returned once, never stored)
     */
    private function setNewApiToken(User $user): string
    {
        $plainToken = $this->tokenGenerator->generateApiToken();
        $hashedToken = hash('sha256', $plainToken);
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify("+{$this->apiTokenTtlHours} hours");

        $user->setApiTokenHash($hashedToken);
        $user->setApiTokenIssuedAt($now);
        $user->setApiTokenExpiresAt($expiresAt);

        return $plainToken;
    }

    /**
     * Revokes the user's API token.
     *
     * @param User $user The user whose token should be revoked
     */
    public function revokeApiToken(User $user): void
    {
        $user->setApiTokenHash(null);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Validates a user's password.
     *
     * @param User   $user          The user to validate
     * @param string $plainPassword The plain text password to check
     *
     * @return bool True if password is valid, false otherwise
     */
    public function validatePassword(User $user, string $plainPassword): bool
    {
        return $this->passwordHasher->isPasswordValid($user, $plainPassword);
    }

    /**
     * Changes a user's password.
     *
     * @param User   $user             The user whose password should be changed
     * @param string $newPlainPassword The new plain text password
     */
    public function changePassword(User $user, string $newPlainPassword): void
    {
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPlainPassword);
        $user->setPasswordHash($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Finds a user by email address.
     *
     * @param string $email The email address to search for
     *
     * @return User|null The user if found, null otherwise
     */
    public function findByEmail(string $email): ?User
    {
        return $this->userRepository->findByEmail($email);
    }

    /**
     * Finds a user by API token.
     * Returns null if token is not found OR if token is expired.
     *
     * @param string $token The plaintext API token to search for
     *
     * @return User|null The user if found and token is valid, null otherwise
     */
    public function findByApiToken(string $token): ?User
    {
        $hashedToken = hash('sha256', $token);
        $user = $this->userRepository->findByApiTokenHash($hashedToken);

        if ($user === null) {
            return null;
        }

        // Return null if token is expired - authenticator will handle as invalid token
        if ($user->isApiTokenExpired()) {
            return null;
        }

        return $user;
    }

    /**
     * Finds a user by API token without checking expiration.
     * Used for token refresh where expired tokens are allowed.
     *
     * @param string $token The plaintext API token to search for
     *
     * @return User|null The user if found, null otherwise
     */
    public function findByApiTokenIgnoreExpiration(string $token): ?User
    {
        $hashedToken = hash('sha256', $token);

        return $this->userRepository->findByApiTokenHash($hashedToken);
    }

    /**
     * Gets the token expiration time for a user.
     */
    public function getTokenExpiresAt(User $user): ?\DateTimeImmutable
    {
        return $user->getApiTokenExpiresAt();
    }

    /**
     * Deletes a user and all associated data (GDPR right to erasure).
     *
     * Projects, tasks, and tags are automatically deleted via orphanRemoval.
     * SavedFilters must be deleted manually as they don't have orphanRemoval.
     */
    public function deleteUser(User $user): void
    {
        // Delete saved filters (not cascade deleted via orphanRemoval)
        $this->entityManager->createQueryBuilder()
            ->delete(SavedFilter::class, 'sf')
            ->where('sf.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        // Delete user (cascades to projects, tasks, tags via orphanRemoval)
        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }
}
