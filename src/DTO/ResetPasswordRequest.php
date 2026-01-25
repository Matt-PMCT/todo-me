<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for completing password reset with token.
 */
final class ResetPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Token is required')]
        public readonly string $token = '',

        #[Assert\NotBlank(message: 'Password is required')]
        #[Assert\Length(min: 12, minMessage: 'Password must be at least {{ limit }} characters')]
        public readonly string $password = '',
    ) {
    }

    /**
     * Creates a ResetPasswordRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            token: (string) ($data['token'] ?? ''),
            password: (string) ($data['password'] ?? ''),
        );
    }
}
