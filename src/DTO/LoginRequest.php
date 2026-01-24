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
        // Email: trim is appropriate - users commonly copy-paste emails with accidental whitespace
        #[Assert\NotBlank(message: 'Email is required', normalizer: 'trim')]
        #[Assert\Email(message: 'Email must be a valid email address')]
        public readonly string $email = '',

        // Password: NO trim normalizer - passwords may intentionally contain leading/trailing spaces.
        // While uncommon, some users and password generators create passwords with spaces.
        // Silently trimming could cause login failures for valid credentials.
        // Decision made 2026-01-24 during Phase 3 review to preserve password integrity.
        #[Assert\NotBlank(message: 'Password is required')]
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
