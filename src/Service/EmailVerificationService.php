<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

final class EmailVerificationService
{
    private const RESEND_COOLDOWN_MINUTES = 5;
    private const TOKEN_EXPIRY_HOURS = 24;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly EmailService $emailService,
        private readonly TokenGenerator $tokenGenerator,
    ) {
    }

    /**
     * Sends verification email with cooldown check.
     *
     * @throws ValidationException If email is already verified or cooldown not elapsed
     */
    public function sendVerificationEmail(User $user): void
    {
        if ($user->isEmailVerified()) {
            throw ValidationException::forField('email', 'Email is already verified');
        }

        // Check cooldown
        $lastSent = $user->getEmailVerificationSentAt();
        if ($lastSent !== null) {
            $cooldownEnd = $lastSent->modify('+'.self::RESEND_COOLDOWN_MINUTES.' minutes');
            if (new \DateTimeImmutable() < $cooldownEnd) {
                $remaining = $cooldownEnd->getTimestamp() - time();

                throw ValidationException::forField(
                    'email',
                    sprintf('Please wait %d seconds before requesting another verification email', $remaining)
                );
            }
        }

        // Generate token and hash for storage
        $plainToken = $this->tokenGenerator->generateEmailVerificationToken();
        $hashedToken = hash('sha256', $plainToken);

        $user->setEmailVerificationToken($hashedToken);
        $user->setEmailVerificationSentAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        // Send email with plain token
        $this->emailService->sendEmailVerification($user, $plainToken);
    }

    /**
     * Verifies email with the provided token.
     *
     * @throws ValidationException If token is invalid or expired
     */
    public function verifyEmail(string $token): User
    {
        $hashedToken = hash('sha256', $token);
        $user = $this->userRepository->findByEmailVerificationToken($hashedToken);

        if ($user === null) {
            throw ValidationException::forField('token', 'Invalid verification token');
        }

        // Check expiry
        $sentAt = $user->getEmailVerificationSentAt();
        if ($sentAt !== null) {
            $expiresAt = $sentAt->modify('+'.self::TOKEN_EXPIRY_HOURS.' hours');
            if (new \DateTimeImmutable() > $expiresAt) {
                throw ValidationException::forField('token', 'Verification token has expired');
            }
        }

        $user->setEmailVerified(true);
        $user->setEmailVerificationToken(null);
        $user->setEmailVerificationSentAt(null);

        $this->entityManager->flush();

        return $user;
    }

    /**
     * Checks if email verification is required for this user.
     */
    public function isVerificationRequired(User $user): bool
    {
        return !$user->isEmailVerified();
    }
}
