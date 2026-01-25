<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Task;
use App\ValueObject\TaskParseResult;

/**
 * Result DTO for natural language task creation.
 *
 * Combines the created task with parse result information
 * including highlights and warnings.
 */
final class TaskCreationResult
{
    public function __construct(
        public readonly Task $task,
        public readonly TaskParseResult $parseResult,
        public readonly ?string $undoToken = null,
    ) {
    }

    /**
     * Converts the result to an array suitable for API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $project = null;
        if ($this->task->getProject() !== null) {
            $project = [
                'id' => $this->task->getProject()->getId(),
                'name' => $this->task->getProject()->getName(),
            ];
        }

        $tags = [];
        foreach ($this->task->getTags() as $tag) {
            $tags[] = [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'color' => $tag->getColor(),
            ];
        }

        $data = [
            'id' => $this->task->getId(),
            'title' => $this->task->getTitle(),
            'description' => $this->task->getDescription(),
            'status' => $this->task->getStatus(),
            'priority' => $this->task->getPriority(),
            'dueDate' => $this->task->getDueDate()?->format('Y-m-d'),
            'position' => $this->task->getPosition(),
            'createdAt' => $this->task->getCreatedAt()->format(\DateTimeInterface::RFC3339),
            'updatedAt' => $this->task->getUpdatedAt()->format(\DateTimeInterface::RFC3339),
            'completedAt' => $this->task->getCompletedAt()?->format(\DateTimeInterface::RFC3339),
            'project' => $project,
            'tags' => $tags,
            'parseResult' => [
                'highlights' => array_map(
                    fn ($h) => $h->toArray(),
                    $this->parseResult->highlights
                ),
                'warnings' => $this->parseResult->warnings,
            ],
        ];

        if ($this->undoToken !== null) {
            $data['undoToken'] = $this->undoToken;
        }

        return $data;
    }
}
