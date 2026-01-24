<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Task;

/**
 * Result DTO for task status changes, particularly for recurring tasks.
 */
final class TaskStatusResult
{
    public function __construct(
        public readonly Task $task,
        public readonly ?Task $nextTask = null,
        public readonly ?string $undoToken = null,
    ) {
    }

    /**
     * Check if a next task was created (for recurring tasks).
     */
    public function hasNextTask(): bool
    {
        return $this->nextTask !== null;
    }

    /**
     * Converts the result to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = TaskResponse::fromTask($this->task, $this->undoToken)->toArray();

        if ($this->nextTask !== null) {
            $data['nextTask'] = TaskResponse::fromTask($this->nextTask)->toArray();
        }

        return $data;
    }
}
