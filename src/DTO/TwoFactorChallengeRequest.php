<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class TwoFactorChallengeRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Challenge token is required')]
        public readonly string $challengeToken = '',
        #[Assert\NotBlank(message: 'Code is required')]
        public readonly string $code = '',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            challengeToken: (string) ($data['challengeToken'] ?? ''),
            code: (string) ($data['code'] ?? ''),
        );
    }
}
