<?php

declare(strict_types=1);

namespace App\DTO;

final class TwoFactorSetupResponse
{
    public function __construct(
        public readonly string $setupToken,
        public readonly string $secret,
        public readonly string $qrCodeUri,
        public readonly int $expiresIn,
    ) {
    }

    /**
     * @return array{setupToken: string, secret: string, qrCodeUri: string, expiresIn: int}
     */
    public function toArray(): array
    {
        return [
            'setupToken' => $this->setupToken,
            'secret' => $this->secret,
            'qrCodeUri' => $this->qrCodeUri,
            'expiresIn' => $this->expiresIn,
        ];
    }
}
