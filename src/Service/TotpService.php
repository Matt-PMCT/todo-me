<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use OTPHP\TOTP;

class TotpService
{
    private const ISSUER = 'TodoMe';
    private const PERIOD = 30;
    private const DIGITS = 6;
    private const ALGORITHM = 'sha1';
    private const DRIFT_TOLERANCE = 1;

    public function generateSecret(): string
    {
        return TOTP::generate()->getSecret();
    }

    public function getProvisioningUri(User $user, string $secret): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($user->getEmail());
        $totp->setIssuer(self::ISSUER);
        $totp->setPeriod(self::PERIOD);
        $totp->setDigits(self::DIGITS);
        $totp->setDigest(self::ALGORITHM);

        return $totp->getProvisioningUri();
    }

    public function verifyCode(string $secret, string $code): bool
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setPeriod(self::PERIOD);
        $totp->setDigits(self::DIGITS);
        $totp->setDigest(self::ALGORITHM);

        // Allow 1-period drift tolerance (codes from previous/next period accepted)
        return $totp->verify($code, null, self::DRIFT_TOLERANCE);
    }

    public function getCurrentCode(string $secret): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setPeriod(self::PERIOD);
        $totp->setDigits(self::DIGITS);
        $totp->setDigest(self::ALGORITHM);

        return $totp->now();
    }
}
