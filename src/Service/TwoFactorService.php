<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class TwoFactorService
{
    private const SETUP_TOKEN_TTL = 600; // 10 minutes
    private const SETUP_KEY_PREFIX = '2fa_setup';

    public function __construct(
        private readonly TotpService $totpService,
        private readonly BackupCodeService $backupCodeService,
        private readonly RedisService $redisService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Initialize 2FA setup for a user.
     *
     * @return array{setupToken: string, secret: string, qrCodeUri: string, expiresIn: int}
     */
    public function initializeSetup(User $user): array
    {
        $secret = $this->totpService->generateSecret();
        $qrCodeUri = $this->totpService->getProvisioningUri($user, $secret);
        $setupToken = bin2hex(random_bytes(32));

        // Store setup data in Redis with TTL
        $this->redisService->setJson(
            $this->buildSetupKey($user, $setupToken),
            ['secret' => $secret],
            self::SETUP_TOKEN_TTL
        );

        return [
            'setupToken' => $setupToken,
            'secret' => $secret,
            'qrCodeUri' => $qrCodeUri,
            'expiresIn' => self::SETUP_TOKEN_TTL,
        ];
    }

    /**
     * Complete 2FA setup by verifying a TOTP code.
     *
     * @return array{enabled: bool, backupCodes: string[]}|null
     */
    public function completeSetup(User $user, string $setupToken, string $code): ?array
    {
        // Retrieve and consume the setup token
        $setupData = $this->redisService->getJsonAndDelete($this->buildSetupKey($user, $setupToken));
        if ($setupData === null) {
            return null;
        }

        $secret = $setupData['secret'] ?? null;
        if ($secret === null) {
            return null;
        }

        // Verify the TOTP code
        if (!$this->totpService->verifyCode($secret, $code)) {
            return null;
        }

        // Generate backup codes
        $backupCodeData = $this->backupCodeService->generateBackupCodes();

        // Enable 2FA
        $user->setTwoFactorEnabled(true);
        $user->setTotpSecret($secret);
        $user->setBackupCodes($backupCodeData['hashedCodes']);
        $user->setTwoFactorEnabledAt(new \DateTimeImmutable());
        $user->setBackupCodesGeneratedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return [
            'enabled' => true,
            'backupCodes' => $backupCodeData['codes'],
        ];
    }

    /**
     * Disable 2FA for a user.
     */
    public function disable(User $user): void
    {
        $user->setTwoFactorEnabled(false);
        $user->setTotpSecret(null);
        $user->setBackupCodes(null);
        $user->setTwoFactorEnabledAt(null);
        $user->setBackupCodesGeneratedAt(null);

        $this->entityManager->flush();
    }

    /**
     * Verify a TOTP code during login.
     */
    public function verify(User $user, string $code): bool
    {
        $secret = $user->getTotpSecret();
        if ($secret === null) {
            return false;
        }

        return $this->totpService->verifyCode($secret, $code);
    }

    /**
     * Verify a backup code during login.
     */
    public function verifyWithBackupCode(User $user, string $code): bool
    {
        $result = $this->backupCodeService->verifyBackupCode($user, $code);
        if ($result) {
            $this->entityManager->flush();
        }

        return $result;
    }

    /**
     * Regenerate backup codes for a user.
     *
     * @return string[] The new backup codes
     */
    public function regenerateBackupCodes(User $user): array
    {
        $backupCodeData = $this->backupCodeService->generateBackupCodes();

        $user->setBackupCodes($backupCodeData['hashedCodes']);
        $user->setBackupCodesGeneratedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $backupCodeData['codes'];
    }

    /**
     * Get 2FA status for a user.
     *
     * @return array{enabled: bool, enabledAt: string|null, backupCodesRemaining: int}
     */
    public function getStatus(User $user): array
    {
        return [
            'enabled' => $user->isTwoFactorEnabled(),
            'enabledAt' => $user->getTwoFactorEnabledAt()?->format(\DateTimeInterface::ATOM),
            'backupCodesRemaining' => $user->getBackupCodesRemaining(),
        ];
    }

    private function buildSetupKey(User $user, string $setupToken): string
    {
        return sprintf('%s:%s:%s', self::SETUP_KEY_PREFIX, $user->getId(), $setupToken);
    }
}
