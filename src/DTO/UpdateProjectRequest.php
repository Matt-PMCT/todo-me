<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for updating an existing project.
 * All fields are optional for partial updates.
 */
final class UpdateProjectRequest
{
    public function __construct(
        #[Assert\Length(
            max: 100,
            maxMessage: 'Name cannot be longer than {{ limit }} characters'
        )]
        public readonly ?string $name = null,

        #[Assert\Length(
            max: 500,
            maxMessage: 'Description cannot be longer than {{ limit }} characters'
        )]
        public readonly ?string $description = null,
    ) {
    }

    /**
     * Creates an UpdateProjectRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? (string) $data['name'] : null,
            description: array_key_exists('description', $data)
                ? ($data['description'] !== null ? (string) $data['description'] : null)
                : null,
        );
    }

    /**
     * Check if any fields were provided for update.
     */
    public function hasChanges(): bool
    {
        return $this->name !== null || $this->description !== null;
    }
}
