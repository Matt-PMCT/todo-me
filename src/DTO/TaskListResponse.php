<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Task;

/**
 * Response DTO for a paginated list of tasks.
 */
final class TaskListResponse
{
    /**
     * @param TaskResponse[]                                            $items
     * @param array{total: int, page: int, limit: int, totalPages: int} $meta
     */
    public function __construct(
        public readonly array $items,
        public readonly array $meta,
    ) {
    }

    /**
     * Creates a TaskListResponse from an array of Task entities.
     *
     * @param Task[]                                                    $tasks
     * @param array{total: int, page: int, limit: int, totalPages: int} $meta
     */
    public static function fromTasks(array $tasks, array $meta): self
    {
        $items = array_map(
            fn (Task $task) => TaskResponse::fromTask($task),
            $tasks
        );

        return new self(
            items: $items,
            meta: $meta,
        );
    }

    /**
     * Converts the response to an array.
     *
     * @return array{items: array<array<string, mixed>>, meta: array{total: int, page: int, limit: int, totalPages: int}}
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(
                fn (TaskResponse $item) => $item->toArray(),
                $this->items
            ),
            'meta' => $this->meta,
        ];
    }
}
