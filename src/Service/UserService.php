<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service for user management operations.
 */
final class UserService
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
     * @param string $email The user's email address
     * @param string $plainPassword The user's plain text password
     * @return User The newly created user
     */
    public function register(string $email, string $plainPassword): User
    {
        $user = new User();
        $user->setEmail($email);

        // Generate username from email prefix with random suffix for uniqueness
        $emailPrefix = explode('@', $email)[0];
        $user->setUsername($emailPrefix . '_' . substr(bin2hex(random_bytes(4)), 0, 8));

        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPasswordHash($hashedPassword);

        $this->setNewApiToken($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Generates a new API token for the user.
     *
     * @param User $user The user to generate a new token for
     * @return string The new API token
     */
    public function generateNewApiToken(User $user): string
    {
        $this->setNewApiToken($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user->getApiToken();
    }

    /**
     * Sets a new API token with expiration on the user.
     */
    private function setNewApiToken(User $user): void
    {
        $apiToken = $this->tokenGenerator->generateApiToken();
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify("+{$this->apiTokenTtlHours} hours");

        $user->setApiToken($apiToken);
        $user->setApiTokenIssuedAt($now);
        $user->setApiTokenExpiresAt($expiresAt);
    }

    /**
     * Revokes the user's API token.
     *
     * @param User $user The user whose token should be revoked
     */
    public function revokeApiToken(User $user): void
    {
        $user->setApiToken(null);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Validates a user's password.
     *
     * @param User $user The user to validate
     * @param string $plainPassword The plain text password to check
     * @return bool True if password is valid, false otherwise
     */
    public function validatePassword(User $user, string $plainPassword): bool
    {
        return $this->passwordHasher->isPasswordValid($user, $plainPassword);
    }

    /**
     * Changes a user's password.
     *
     * @param User $user The user whose password should be changed
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
     * @param string $token The API token to search for
     * @return User|null The user if found and token is valid, null otherwise
     */
    public function findByApiToken(string $token): ?User
    {
        $user = $this->userRepository->findByApiToken($token);

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
     * @param string $token The API token to search for
     * @return User|null The user if found, null otherwise
     */
    public function findByApiTokenIgnoreExpiration(string $token): ?User
    {
        return $this->userRepository->findByApiToken($token);
    }

    /**
     * Gets the token expiration time for a user.
     */
    public function getTokenExpiresAt(User $user): ?\DateTimeImmutable
    {
        return $user->getApiTokenExpiresAt();
    }
}
