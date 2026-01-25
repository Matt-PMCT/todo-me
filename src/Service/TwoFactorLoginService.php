<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;

final class TwoFactorLoginService
{
    private const CHALLENGE_TTL = 300; // 5 minutes
    private const CHALLENGE_KEY_PREFIX = '2fa_challenge';

    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly RedisService $redisService,
        private readonly UserRepository $userRepository,
    ) {
    }

    /**
     * Check if user requires 2FA for login.
     */
    public function requires2fa(User $user): bool
    {
        return $user->isTwoFactorEnabled();
    }

    /**
     * Create a 2FA challenge for login.
     *
     * @return array{challengeToken: string, expiresIn: int}
     */
    public function createChallenge(User $user): array
    {
        $challengeToken = bin2hex(random_bytes(32));

        $this->redisService->setJson(
            $this->buildChallengeKey($challengeToken),
            ['userId' => $user->getId()],
            self::CHALLENGE_TTL
        );

        return [
            'challengeToken' => $challengeToken,
            'expiresIn' => self::CHALLENGE_TTL,
        ];
    }

    /**
     * Verify a 2FA challenge using TOTP or backup code.
     *
     * @return User|null The user if verification succeeds, null otherwise
     */
    public function verifyChallenge(string $challengeToken, string $code): ?User
    {
        // Get and consume the challenge token
        $challengeData = $this->redisService->getJsonAndDelete($this->buildChallengeKey($challengeToken));
        if ($challengeData === null) {
            return null;
        }

        $userId = $challengeData['userId'] ?? null;
        if ($userId === null) {
            return null;
        }

        $user = $this->userRepository->find($userId);
        if ($user === null) {
            return null;
        }

        // Try TOTP verification first
        if ($this->twoFactorService->verify($user, $code)) {
            return $user;
        }

        // Try backup code verification
        if ($this->twoFactorService->verifyWithBackupCode($user, $code)) {
            return $user;
        }

        return null;
    }

    private function buildChallengeKey(string $challengeToken): string
    {
        return sprintf('%s:%s', self::CHALLENGE_KEY_PREFIX, $challengeToken);
    }
}
