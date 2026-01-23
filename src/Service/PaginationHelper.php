<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Helper service for pagination operations.
 */
final class PaginationHelper
{
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;

    /**
     * Paginates a QueryBuilder and returns items with total count.
     *
     * @param QueryBuilder $qb The query builder to paginate
     * @param int $page The page number (1-indexed)
     * @param int $limit The number of items per page
     * @return array{items: array<mixed>, total: int}
     */
    public function paginate(QueryBuilder $qb, int $page = self::DEFAULT_PAGE, int $limit = self::DEFAULT_LIMIT): array
    {
        $page = $this->normalizePage($page);
        $limit = $this->normalizeLimit($limit);

        $offset = ($page - 1) * $limit;

        $qb->setFirstResult($offset)
            ->setMaxResults($limit);

        $paginator = new Paginator($qb, fetchJoinCollection: true);

        $total = count($paginator);
        $items = iterator_to_array($paginator);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Calculates pagination metadata.
     *
     * @param int $total Total number of items
     * @param int $page Current page number (1-indexed)
     * @param int $limit Items per page
     * @return array{total: int, page: int, limit: int, totalPages: int}
     */
    public function calculateMeta(int $total, int $page = self::DEFAULT_PAGE, int $limit = self::DEFAULT_LIMIT): array
    {
        $page = $this->normalizePage($page);
        $limit = $this->normalizeLimit($limit);

        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 0;

        return [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Normalizes the page number to be at least 1.
     */
    public function normalizePage(int $page): int
    {
        return max(self::DEFAULT_PAGE, $page);
    }

    /**
     * Normalizes the limit to be within valid bounds.
     */
    public function normalizeLimit(int $limit): int
    {
        if ($limit < 1) {
            return self::DEFAULT_LIMIT;
        }

        return min($limit, self::MAX_LIMIT);
    }

    /**
     * Gets the default page number.
     */
    public function getDefaultPage(): int
    {
        return self::DEFAULT_PAGE;
    }

    /**
     * Gets the default limit.
     */
    public function getDefaultLimit(): int
    {
        return self::DEFAULT_LIMIT;
    }

    /**
     * Gets the maximum allowed limit.
     */
    public function getMaxLimit(): int
    {
        return self::MAX_LIMIT;
    }
}
