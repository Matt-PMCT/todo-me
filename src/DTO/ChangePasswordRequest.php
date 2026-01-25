<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for changing user password.
 */
final class ChangePasswordRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Current password is required')]
        public readonly string $currentPassword = '',

        #[Assert\NotBlank(message: 'New password is required')]
        #[Assert\Length(min: 12, minMessage: 'Password must be at least {{ limit }} characters')]
        public readonly string $newPassword = '',
    ) {
    }

    /**
     * Creates a ChangePasswordRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            currentPassword: (string) ($data['current_password'] ?? ''),
            newPassword: (string) ($data['new_password'] ?? ''),
        );
    }
}
