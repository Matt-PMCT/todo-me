<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for creating a new saved filter.
 */
final class CreateSavedFilterRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name is required')]
        #[Assert\Length(max: 100, maxMessage: 'Name must be at most {{ limit }} characters')]
        public readonly string $name = '',

        #[Assert\NotNull(message: 'Criteria is required')]
        public readonly array $criteria = [],

        public readonly bool $isDefault = false,
    ) {
    }

    /**
     * Creates a CreateSavedFilterRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            criteria: is_array($data['criteria'] ?? null) ? $data['criteria'] : [],
            isDefault: (bool) ($data['isDefault'] ?? false),
        );
    }
}
