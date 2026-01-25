<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class TwoFactorRecoveryService
{
    private const RECOVERY_TOKEN_TTL = 86400; // 24 hours
    private const RECOVERY_KEY_PREFIX = '2fa_recovery';

    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly RedisService $redisService,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailService $emailService,
    ) {
    }

    /**
     * Request a 2FA recovery email.
     *
     * Always succeeds (to prevent user enumeration).
     */
    public function requestRecovery(string $email): void
    {
        $user = $this->userRepository->findOneBy(['email' => $email]);

        // Only send if user exists and has 2FA enabled
        if ($user !== null && $user->isTwoFactorEnabled()) {
            $token = bin2hex(random_bytes(32));

            $this->redisService->setJson(
                $this->buildRecoveryKey($token),
                ['userId' => $user->getId()],
                self::RECOVERY_TOKEN_TTL
            );

            $this->emailService->send2faRecoveryEmail($user, $token);
        }
    }

    /**
     * Complete 2FA recovery by disabling 2FA.
     *
     * @return bool True if recovery was successful
     */
    public function completeRecovery(string $token): bool
    {
        $recoveryData = $this->redisService->getJsonAndDelete($this->buildRecoveryKey($token));
        if ($recoveryData === null) {
            return false;
        }

        $userId = $recoveryData['userId'] ?? null;
        if ($userId === null) {
            return false;
        }

        $user = $this->userRepository->find($userId);
        if ($user === null) {
            return false;
        }

        // Disable 2FA
        $this->twoFactorService->disable($user);

        // Invalidate API token
        $user->setApiToken(null);
        $this->entityManager->flush();

        return true;
    }

    private function buildRecoveryKey(string $token): string
    {
        return sprintf('%s:%s', self::RECOVERY_KEY_PREFIX, $token);
    }
}
