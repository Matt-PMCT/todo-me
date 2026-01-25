<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Exception\AccountLockedException;
use Doctrine\ORM\EntityManagerInterface;

final class AccountLockoutService
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_DURATION_MINUTES = 15;
    private const ATTEMPT_WINDOW_MINUTES = 30;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailService $emailService,
    ) {
    }

    /**
     * Checks if user is locked out.
     *
     * @throws AccountLockedException If account is locked
     */
    public function checkLockout(User $user): void
    {
        if ($user->isLocked()) {
            throw AccountLockedException::locked($user->getLockoutRemainingSeconds());
        }
    }

    /**
     * Records a failed login attempt.
     */
    public function recordFailedAttempt(User $user): void
    {
        // Reset counter if outside the attempt window
        if ($this->isOutsideAttemptWindow($user)) {
            $user->resetFailedLoginAttempts();
        }

        $user->incrementFailedLoginAttempts();

        if ($user->getFailedLoginAttempts() >= self::MAX_FAILED_ATTEMPTS) {
            $this->lockAccount($user);
        }

        $this->entityManager->flush();
    }

    /**
     * Records a successful login.
     */
    public function recordSuccessfulLogin(User $user): void
    {
        if ($user->getFailedLoginAttempts() > 0) {
            $user->resetFailedLoginAttempts();
            $this->entityManager->flush();
        }
    }

    private function isOutsideAttemptWindow(User $user): bool
    {
        $lastFailed = $user->getLastFailedLoginAt();
        if ($lastFailed === null) {
            return true;
        }

        $windowEnd = $lastFailed->modify('+' . self::ATTEMPT_WINDOW_MINUTES . ' minutes');
        return new \DateTimeImmutable() > $windowEnd;
    }

    private function lockAccount(User $user): void
    {
        $user->setLockedUntil(new \DateTimeImmutable('+' . self::LOCKOUT_DURATION_MINUTES . ' minutes'));

        // Send notification email
        $this->emailService->sendAccountLockedNotification($user, self::LOCKOUT_DURATION_MINUTES);
    }
}
