<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class TwoFactorVerifyRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Setup token is required')]
        public readonly string $setupToken = '',

        #[Assert\NotBlank(message: 'Code is required')]
        #[Assert\Length(
            exactly: 6,
            exactMessage: 'Code must be exactly {{ limit }} digits'
        )]
        #[Assert\Regex(
            pattern: '/^\d{6}$/',
            message: 'Code must be 6 digits'
        )]
        public readonly string $code = '',
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            setupToken: (string) ($data['setupToken'] ?? ''),
            code: (string) ($data['code'] ?? ''),
        );
    }
}
