<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for user login.
 */
final class LoginRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required', normalizer: 'trim')]
        #[Assert\Email(message: 'Email must be a valid email address')]
        public readonly string $email = '',

        #[Assert\NotBlank(message: 'Password is required', normalizer: 'trim')]
        public readonly string $password = '',
    ) {
    }

    /**
     * Creates a LoginRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: (string) ($data['email'] ?? ''),
            password: (string) ($data['password'] ?? ''),
        );
    }
}
