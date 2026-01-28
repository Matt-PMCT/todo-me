<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Tag;

/**
 * Response DTO for tag information.
 */
final class TagResponse
{
    /**
     * @param string             $id        Tag ID
     * @param string             $name      Tag name
     * @param string             $color     Tag color (hex)
     * @param \DateTimeImmutable $createdAt Creation timestamp
     * @param int                $taskCount Number of tasks with this tag
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $color,
        public readonly \DateTimeImmutable $createdAt,
        public readonly int $taskCount = 0,
    ) {
    }

    /**
     * Creates a TagResponse from a Tag entity.
     *
     * @param Tag $tag       The tag entity
     * @param int $taskCount Number of tasks with this tag
     */
    public static function fromEntity(Tag $tag, int $taskCount = 0): self
    {
        return new self(
            id: $tag->getId() ?? '',
            name: $tag->getName(),
            color: $tag->getColor(),
            createdAt: $tag->getCreatedAt(),
            taskCount: $taskCount,
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
            'name' => $this->name,
            'color' => $this->color,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::RFC3339),
            'taskCount' => $this->taskCount,
        ];
    }
}
