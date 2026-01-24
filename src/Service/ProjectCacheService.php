<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Cache service for project tree data.
 */
final class ProjectCacheService
{
    private const KEY_PREFIX = 'project_tree';
    private const TTL = 300; // 5 minutes

    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Build a cache key for the project tree.
     *
     * @param string $userId The user ID
     * @param bool $includeArchived Whether archived projects are included
     * @param bool $includeTaskCounts Whether task counts are included
     * @return string The cache key
     */
    public function buildKey(string $userId, bool $includeArchived, bool $includeTaskCounts): string
    {
        return sprintf(
            '%s:%s:%d:%d',
            self::KEY_PREFIX,
            $userId,
            (int) $includeArchived,
            (int) $includeTaskCounts
        );
    }

    /**
     * Get cached project tree data.
     *
     * @param string $userId The user ID
     * @param bool $includeArchived Whether archived projects are included
     * @param bool $includeTaskCounts Whether task counts are included
     * @return array|null The cached tree data or null if not cached
     */
    public function get(string $userId, bool $includeArchived, bool $includeTaskCounts): ?array
    {
        $key = $this->buildKey($userId, $includeArchived, $includeTaskCounts);

        try {
            $item = $this->cache->get($key, function (ItemInterface $item) {
                // Return null marker if not found - the callback should not be called if cached
                return ['__not_found__' => true];
            });

            if (isset($item['__not_found__'])) {
                return null;
            }

            return $item;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Set cached project tree data.
     *
     * @param string $userId The user ID
     * @param bool $includeArchived Whether archived projects are included
     * @param bool $includeTaskCounts Whether task counts are included
     * @param array $tree The tree data to cache
     */
    public function set(string $userId, bool $includeArchived, bool $includeTaskCounts, array $tree): void
    {
        $key = $this->buildKey($userId, $includeArchived, $includeTaskCounts);

        try {
            $this->cache->delete($key);
            $this->cache->get($key, function (ItemInterface $item) use ($tree) {
                $item->expiresAfter(self::TTL);
                return $tree;
            });
        } catch (\Exception) {
            // Silently fail - cache is optional
        }
    }

    /**
     * Invalidate all cache variants for a user.
     *
     * @param string $userId The user ID
     */
    public function invalidate(string $userId): void
    {
        // Clear all 4 variants (archived: true/false, taskCounts: true/false)
        $variants = [
            $this->buildKey($userId, false, false),
            $this->buildKey($userId, false, true),
            $this->buildKey($userId, true, false),
            $this->buildKey($userId, true, true),
        ];

        foreach ($variants as $key) {
            try {
                $this->cache->delete($key);
            } catch (\Exception) {
                // Silently fail
            }
        }
    }
}
