<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for creating a new project.
 */
final class CreateProjectRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name is required', normalizer: 'trim')]
        #[Assert\Length(
            max: 100,
            maxMessage: 'Name cannot be longer than {{ limit }} characters'
        )]
        public readonly string $name = '',
        #[Assert\Length(
            max: 500,
            maxMessage: 'Description cannot be longer than {{ limit }} characters'
        )]
        public readonly ?string $description = null,
        #[Assert\Uuid(message: 'Parent ID must be a valid UUID')]
        public readonly ?string $parentId = null,
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
     * Creates a CreateProjectRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            parentId: isset($data['parentId']) && $data['parentId'] !== '' ? (string) $data['parentId'] : null,
            color: isset($data['color']) && $data['color'] !== '' ? (string) $data['color'] : null,
            icon: isset($data['icon']) && $data['icon'] !== '' ? (string) $data['icon'] : null,
        );
    }
}
