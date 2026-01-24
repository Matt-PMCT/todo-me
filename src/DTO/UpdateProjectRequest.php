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
    /**
     * @param string|null $name Project name
     * @param string|null $description Project description
     * @param string|null|false $parentId Parent ID (null = move to root, false = not specified)
     * @param string|null $color Project color in hex format
     * @param string|null $icon Project icon identifier
     */
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

        public readonly string|null|false $parentId = false,

        #[Assert\Regex(
            pattern: '/^#[0-9A-Fa-f]{6}$/',
            message: 'Color must be a valid hex color'
        )]
        public readonly ?string $color = null,

        #[Assert\Regex(
            pattern: '/^[a-zA-Z0-9_-]*$/',
            message: 'Icon must contain only alphanumeric characters, dashes, and underscores'
        )]
        public readonly ?string $icon = null,
    ) {
    }

    /**
     * Creates an UpdateProjectRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $parentId = false;
        if (array_key_exists('parentId', $data)) {
            $parentId = $data['parentId'] !== null && $data['parentId'] !== ''
                ? (string) $data['parentId']
                : null;
        }

        return new self(
            name: isset($data['name']) ? (string) $data['name'] : null,
            description: array_key_exists('description', $data)
                ? ($data['description'] !== null ? (string) $data['description'] : null)
                : null,
            parentId: $parentId,
            color: isset($data['color']) && $data['color'] !== '' ? (string) $data['color'] : null,
            icon: isset($data['icon']) && $data['icon'] !== '' ? (string) $data['icon'] : null,
        );
    }

    /**
     * Check if any fields were provided for update.
     */
    public function hasChanges(): bool
    {
        return $this->name !== null || $this->description !== null || $this->parentId !== false;
    }

    /**
     * Check if parentId was explicitly provided in the request.
     */
    public function hasParentIdChange(): bool
    {
        return $this->parentId !== false;
    }
}
