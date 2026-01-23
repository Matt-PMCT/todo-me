<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Response DTO for token operations.
 */
final class TokenResponse
{
    public function __construct(
        public readonly string $token,
        public readonly ?\DateTimeImmutable $expiresAt = null,
    ) {
    }

    /**
     * Converts the DTO to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'expiresAt' => $this->expiresAt?->format(\DateTimeInterface::RFC3339),
        ];
    }
}
