<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;

interface BackupCodeServiceInterface
{
    /**
     * Generate backup codes for a user.
     *
     * @return array{codes: string[], hashedCodes: array<array{hash: string, used: bool}>}
     */
    public function generateBackupCodes(): array;

    /**
     * Verify a backup code and mark it as used if valid.
     *
     * @return bool True if code was valid and unused, false otherwise
     */
    public function verifyBackupCode(User $user, string $code): bool;
}
