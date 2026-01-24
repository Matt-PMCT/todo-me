<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for updating an existing saved filter.
 */
final class UpdateSavedFilterRequest
{
    public function __construct(
        #[Assert\Length(max: 100, maxMessage: 'Name must be at most {{ limit }} characters')]
        public readonly ?string $name = null,

        public readonly ?array $criteria = null,

        public readonly ?bool $isDefault = null,

        #[Assert\Length(max: 50, maxMessage: 'Icon must be at most {{ limit }} characters')]
        public readonly ?string $icon = null,

        #[Assert\Length(max: 7, maxMessage: 'Color must be at most {{ limit }} characters')]
        #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Color must be a valid hex color (e.g., #FF5733)', groups: ['color_format'])]
        public readonly ?string $color = null,
    ) {
    }

    /**
     * Creates an UpdateSavedFilterRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) ? (string) $data['name'] : null,
            criteria: isset($data['criteria']) && is_array($data['criteria']) ? $data['criteria'] : null,
            isDefault: isset($data['isDefault']) ? (bool) $data['isDefault'] : null,
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
            color: isset($data['color']) ? (string) $data['color'] : null,
        );
    }
}
