<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Task;

/**
 * Response DTO for a single task.
 */
final class TaskResponse
{
    /**
     * @param array{id: string, name: string}|null                  $project
     * @param array<array{id: string, name: string, color: string}> $tags
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $status,
        public readonly int $priority,
        public readonly ?string $dueDate,
        public readonly ?string $dueTime,
        public readonly int $position,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $completedAt,
        public readonly ?array $project,
        public readonly array $tags,
        public readonly ?string $undoToken = null,
        public readonly bool $isRecurring = false,
        public readonly ?string $recurrenceRule = null,
        public readonly ?string $recurrenceType = null,
        public readonly ?string $recurrenceEndDate = null,
        public readonly ?string $originalTaskId = null,
        public readonly ?string $parentTaskId = null,
        public readonly int $subtaskCount = 0,
        public readonly int $completedSubtaskCount = 0,
    ) {
    }

    /**
     * Creates a TaskResponse from a Task entity.
     *
     * @param Task                                   $task          The task entity
     * @param string|null                            $undoToken     Optional undo token
     * @param array{total: int, completed: int}|null $subtaskCounts Optional subtask counts
     */
    public static function fromTask(
        Task $task,
        ?string $undoToken = null,
        ?array $subtaskCounts = null
    ): self {
        $project = null;
        if ($task->getProject() !== null) {
            $project = [
                'id' => $task->getProject()->getId(),
                'name' => $task->getProject()->getName(),
            ];
        }

        $tags = [];
        foreach ($task->getTags() as $tag) {
            $tags[] = [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'color' => $tag->getColor(),
            ];
        }

        return new self(
            id: $task->getId(),
            title: $task->getTitle(),
            description: $task->getDescription(),
            status: $task->getStatus(),
            priority: $task->getPriority(),
            dueDate: $task->getDueDate()?->format('Y-m-d'),
            dueTime: $task->getDueTime()?->format('H:i'),
            position: $task->getPosition(),
            createdAt: $task->getCreatedAt()->format(\DateTimeInterface::RFC3339),
            updatedAt: $task->getUpdatedAt()->format(\DateTimeInterface::RFC3339),
            completedAt: $task->getCompletedAt()?->format(\DateTimeInterface::RFC3339),
            project: $project,
            tags: $tags,
            undoToken: $undoToken,
            isRecurring: $task->isRecurring(),
            recurrenceRule: $task->getRecurrenceRule(),
            recurrenceType: $task->getRecurrenceType(),
            recurrenceEndDate: $task->getRecurrenceEndDate()?->format('Y-m-d'),
            originalTaskId: $task->getOriginalTask()?->getId(),
            parentTaskId: $task->getParentTask()?->getId(),
            subtaskCount: $subtaskCounts['total'] ?? 0,
            completedSubtaskCount: $subtaskCounts['completed'] ?? 0,
        );
    }

    /**
     * Converts the response to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'dueDate' => $this->dueDate,
            'dueTime' => $this->dueTime,
            'position' => $this->position,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
            'completedAt' => $this->completedAt,
            'project' => $this->project,
            'tags' => $this->tags,
            'isRecurring' => $this->isRecurring,
        ];

        if ($this->undoToken !== null) {
            $data['undoToken'] = $this->undoToken;
        }

        if ($this->isRecurring) {
            $data['recurrenceRule'] = $this->recurrenceRule;
            $data['recurrenceType'] = $this->recurrenceType;
            $data['recurrenceEndDate'] = $this->recurrenceEndDate;
        }

        if ($this->originalTaskId !== null) {
            $data['originalTaskId'] = $this->originalTaskId;
        }

        if ($this->parentTaskId !== null) {
            $data['parentTaskId'] = $this->parentTaskId;
        }

        $data['subtaskCount'] = $this->subtaskCount;
        $data['completedSubtaskCount'] = $this->completedSubtaskCount;

        return $data;
    }
}
