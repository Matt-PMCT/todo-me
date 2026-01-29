<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordResetService
{
    private const TOKEN_EXPIRY_MINUTES = 60;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly EmailService $emailService,
        private readonly TokenGenerator $tokenGenerator,
        private readonly PasswordPolicyValidator $passwordPolicyValidator,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Requests a password reset.
     * Always succeeds to prevent email enumeration.
     */
    public function requestReset(string $email): void
    {
        $user = $this->userRepository->findByEmail($email);

        if ($user === null) {
            // Silently return to prevent email enumeration
            return;
        }

        // Generate token and hash for storage
        $plainToken = $this->tokenGenerator->generatePasswordResetToken();
        $hashedToken = hash('sha256', $plainToken);

        $user->setPasswordResetToken($hashedToken);
        $user->setPasswordResetExpiresAt(
            new \DateTimeImmutable('+'.self::TOKEN_EXPIRY_MINUTES.' minutes')
        );

        $this->entityManager->flush();

        // Send email with plain token
        $this->emailService->sendPasswordResetEmail($user, $plainToken);
    }

    /**
     * Validates a reset token without consuming it.
     */
    public function validateToken(string $token): bool
    {
        $hashedToken = hash('sha256', $token);
        $user = $this->userRepository->findByPasswordResetToken($hashedToken);

        return $user !== null && $user->isPasswordResetTokenValid();
    }

    /**
     * Resets the password using a valid token.
     *
     * @throws ValidationException If token is invalid/expired or password doesn't meet policy
     */
    public function resetPassword(string $token, string $newPassword): User
    {
        $hashedToken = hash('sha256', $token);
        $user = $this->userRepository->findByPasswordResetToken($hashedToken);

        if ($user === null || !$user->isPasswordResetTokenValid()) {
            throw ValidationException::forField('token', 'Invalid or expired reset token');
        }

        // Validate password strength
        $errors = $this->passwordPolicyValidator->validate(
            $newPassword,
            $user->getEmail(),
            $user->getUsername()
        );

        if (!empty($errors)) {
            throw ValidationException::forFields(['password' => $errors]);
        }

        // Update password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPasswordHash($hashedPassword);

        // Clear reset token
        $user->setPasswordResetToken(null);
        $user->setPasswordResetExpiresAt(null);

        // Invalidate API token (security measure)
        $user->setApiTokenHash(null);

        $this->entityManager->flush();

        // Send notification
        $this->emailService->sendPasswordChangedNotification($user);

        return $user;
    }
}
