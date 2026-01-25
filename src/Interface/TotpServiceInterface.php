<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;

interface TotpServiceInterface
{
    public function generateSecret(): string;

    public function getProvisioningUri(User $user, string $secret): string;

    public function verifyCode(string $secret, string $code): bool;

    public function getCurrentCode(string $secret): string;
}
