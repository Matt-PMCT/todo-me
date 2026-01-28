<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Response DTO for paginated tag list.
 */
final class TagListResponse
{
    /**
     * @param TagResponse[]                                                                                       $items
     * @param array{total: int, page: int, limit: int, totalPages: int, hasNextPage: bool, hasPreviousPage: bool} $meta
     */
    public function __construct(
        public readonly array $items,
        public readonly array $meta,
    ) {
    }

    /**
     * Creates a TagListResponse from tag responses with pagination info.
     *
     * @param TagResponse[] $items
     * @param int           $total Total number of tags
     * @param int           $page  Current page number
     * @param int           $limit Items per page
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
                'hasNextPage' => $page < $totalPages,
                'hasPreviousPage' => $page > 1,
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
            'items' => array_map(fn (TagResponse $tag) => $tag->toArray(), $this->items),
            'meta' => $this->meta,
        ];
    }
}
