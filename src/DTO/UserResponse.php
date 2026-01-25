<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\User;

/**
 * Response DTO for user information.
 */
final class UserResponse
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Creates a UserResponse from a User entity.
     */
    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->getId() ?? '',
            email: $user->getEmail(),
            createdAt: $user->getCreatedAt(),
        );
    }

    /**
     * Converts the DTO to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::RFC3339),
        ];
    }
}
