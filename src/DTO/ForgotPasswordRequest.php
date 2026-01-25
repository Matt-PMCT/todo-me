<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for initiating password reset.
 */
final class ForgotPasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(message: 'Email must be a valid email address')]
        public readonly string $email = '',
    ) {
    }

    /**
     * Creates a ForgotPasswordRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: (string) ($data['email'] ?? ''),
        );
    }
}
