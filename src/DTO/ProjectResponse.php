<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Project;

/**
 * Response DTO for project information.
 */
final class ProjectResponse
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $isArchived,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        public readonly int $taskCount = 0,
        public readonly int $completedTaskCount = 0,
    ) {
    }

    /**
     * Creates a ProjectResponse from a Project entity.
     *
     * @param Project $project The project entity
     * @param int $taskCount Total number of tasks in the project
     * @param int $completedTaskCount Number of completed tasks in the project
     */
    public static function fromEntity(
        Project $project,
        int $taskCount = 0,
        int $completedTaskCount = 0,
    ): self {
        return new self(
            id: $project->getId() ?? '',
            name: $project->getName(),
            description: $project->getDescription(),
            isArchived: $project->isArchived(),
            createdAt: $project->getCreatedAt(),
            updatedAt: $project->getUpdatedAt(),
            taskCount: $taskCount,
            completedTaskCount: $completedTaskCount,
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
            'description' => $this->description,
            'isArchived' => $this->isArchived,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::RFC3339),
            'updatedAt' => $this->updatedAt->format(\DateTimeInterface::RFC3339),
            'taskCount' => $this->taskCount,
            'completedTaskCount' => $this->completedTaskCount,
        ];
    }
}
