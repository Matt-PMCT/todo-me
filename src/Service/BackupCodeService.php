<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Interface\BackupCodeServiceInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class BackupCodeService implements BackupCodeServiceInterface
{
    private const CODE_COUNT = 10;
    private const CODE_LENGTH = 8;

    public function __construct(
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
    ) {
    }

    /**
     * Generate backup codes for a user.
     *
     * @return array{codes: string[], hashedCodes: array<array{hash: string, used: bool}>}
     */
    public function generateBackupCodes(): array
    {
        $hasher = $this->passwordHasherFactory->getPasswordHasher('backup_code');

        $codes = [];
        $hashedCodes = [];

        for ($i = 0; $i < self::CODE_COUNT; $i++) {
            $code = $this->generateCode();
            $codes[] = $code;
            // Hash the normalized (dash-removed) code for consistent verification
            $normalizedCode = str_replace('-', '', $code);
            $hashedCodes[] = [
                'hash' => $hasher->hash($normalizedCode),
                'used' => false,
            ];
        }

        return [
            'codes' => $codes,
            'hashedCodes' => $hashedCodes,
        ];
    }

    /**
     * Verify a backup code and mark it as used if valid.
     *
     * @return bool True if code was valid and unused, false otherwise
     */
    public function verifyBackupCode(User $user, string $code): bool
    {
        $backupCodes = $user->getBackupCodes();
        if ($backupCodes === null) {
            return false;
        }

        // Normalize code (remove any dashes the user might include)
        $normalizedCode = str_replace('-', '', $code);

        $hasher = $this->passwordHasherFactory->getPasswordHasher('backup_code');

        foreach ($backupCodes as $index => $storedCode) {
            if ($storedCode['used']) {
                continue;
            }

            if ($hasher->verify($storedCode['hash'], $normalizedCode)) {
                // Mark as used
                $backupCodes[$index]['used'] = true;
                $user->setBackupCodes($backupCodes);

                return true;
            }
        }

        return false;
    }

    /**
     * Generate a single backup code in XXXX-XXXX format.
     */
    private function generateCode(): string
    {
        $bytes = random_bytes(self::CODE_LENGTH / 2);
        $hex = strtoupper(bin2hex($bytes));

        return substr($hex, 0, 4) . '-' . substr($hex, 4, 4);
    }
}
