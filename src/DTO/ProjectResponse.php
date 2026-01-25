<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Project;

/**
 * Response DTO for project information.
 */
final class ProjectResponse
{
    /**
     * @param string                                                   $id                 Project ID
     * @param string                                                   $name               Project name
     * @param string|null                                              $description        Project description
     * @param bool                                                     $isArchived         Whether the project is archived
     * @param \DateTimeImmutable                                       $createdAt          Creation timestamp
     * @param \DateTimeImmutable                                       $updatedAt          Last update timestamp
     * @param int                                                      $taskCount          Total number of tasks in the project
     * @param int                                                      $completedTaskCount Number of completed tasks
     * @param int                                                      $pendingTaskCount   Number of pending (non-completed) tasks
     * @param string|null                                              $parentId           Parent project ID (null for root projects)
     * @param int                                                      $depth              Depth in the hierarchy (0 for root projects)
     * @param array<array{id: string, name: string, isArchived: bool}> $path               Path from root to this project
     * @param bool                                                     $showChildrenTasks  Whether to show children's tasks
     * @param string|null                                              $color              Project color
     * @param string|null                                              $icon               Project icon
     * @param int                                                      $position           Position within parent
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly bool $isArchived,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        public readonly int $taskCount = 0,
        public readonly int $completedTaskCount = 0,
        public readonly int $pendingTaskCount = 0,
        public readonly ?string $parentId = null,
        public readonly int $depth = 0,
        public readonly array $path = [],
        public readonly bool $showChildrenTasks = true,
        public readonly ?string $color = null,
        public readonly ?string $icon = null,
        public readonly int $position = 0,
    ) {
    }

    /**
     * Creates a ProjectResponse from a Project entity.
     *
     * @param Project $project            The project entity
     * @param int     $taskCount          Total number of tasks in the project
     * @param int     $completedTaskCount Number of completed tasks in the project
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
            pendingTaskCount: $taskCount - $completedTaskCount,
            parentId: $project->getParent()?->getId(),
            depth: $project->getDepth(),
            path: $project->getPathDetails(),
            showChildrenTasks: $project->isShowChildrenTasks(),
            color: $project->getColor(),
            icon: $project->getIcon(),
            position: $project->getPosition(),
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
            'pendingTaskCount' => $this->pendingTaskCount,
            'parentId' => $this->parentId,
            'depth' => $this->depth,
            'path' => $this->path,
            'showChildrenTasks' => $this->showChildrenTasks,
            'color' => $this->color,
            'icon' => $this->icon,
            'position' => $this->position,
        ];
    }
}
