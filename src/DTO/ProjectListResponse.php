<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Response DTO for paginated project list.
 */
final class ProjectListResponse
{
    /**
     * @param ProjectResponse[] $items
     * @param array{total: int, page: int, limit: int, totalPages: int} $meta
     */
    public function __construct(
        public readonly array $items,
        public readonly array $meta,
    ) {
    }

    /**
     * Creates a ProjectListResponse from project responses with pagination info.
     *
     * @param ProjectResponse[] $items
     * @param int $total Total number of projects
     * @param int $page Current page number
     * @param int $limit Items per page
     */
    public static function create(array $items, int $total, int $page, int $limit): self
    {
        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 0;

        return new self(
            items: $items,
            meta: [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => $totalPages,
            ],
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
            'items' => array_map(fn(ProjectResponse $project) => $project->toArray(), $this->items),
            'meta' => $this->meta,
        ];
    }
}
