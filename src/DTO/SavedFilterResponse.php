<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\SavedFilter;

/**
 * Response DTO for a saved filter.
 */
final class SavedFilterResponse
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $criteria,
        public readonly bool $isDefault,
        public readonly int $position,
        public readonly ?string $icon,
        public readonly ?string $color,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /**
     * Creates a SavedFilterResponse from a SavedFilter entity.
     */
    public static function fromEntity(SavedFilter $filter): self
    {
        return new self(
            id: $filter->getId(),
            name: $filter->getName(),
            criteria: $filter->getCriteria(),
            isDefault: $filter->isDefault(),
            position: $filter->getPosition(),
            icon: $filter->getIcon(),
            color: $filter->getColor(),
            createdAt: $filter->getCreatedAt()->format(\DateTimeInterface::RFC3339),
            updatedAt: $filter->getUpdatedAt()->format(\DateTimeInterface::RFC3339),
        );
    }

    /**
     * Converts the response to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'criteria' => $this->criteria,
            'isDefault' => $this->isDefault,
            'position' => $this->position,
            'icon' => $this->icon,
            'color' => $this->color,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
