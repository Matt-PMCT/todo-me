<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Cache service for project tree data.
 */
final class ProjectCacheService
{
    private const KEY_PREFIX = 'project_tree';
    private const TTL = 300; // 5 minutes

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Build a cache key for the project tree.
     *
     * @param string $userId            The user ID
     * @param bool   $includeArchived   Whether archived projects are included
     * @param bool   $includeTaskCounts Whether task counts are included
     *
     * @return string The cache key
     */
    public function buildKey(string $userId, bool $includeArchived, bool $includeTaskCounts): string
    {
        return sprintf(
            '%s_%s_%d_%d',
            self::KEY_PREFIX,
            $userId,
            (int) $includeArchived,
            (int) $includeTaskCounts
        );
    }

    /**
     * Get cached project tree data.
     *
     * @param string $userId            The user ID
     * @param bool   $includeArchived   Whether archived projects are included
     * @param bool   $includeTaskCounts Whether task counts are included
     *
     * @return array|null The cached tree data or null if not cached
     */
    public function get(string $userId, bool $includeArchived, bool $includeTaskCounts): ?array
    {
        $key = $this->buildKey($userId, $includeArchived, $includeTaskCounts);

        try {
            if (!$this->cache instanceof AdapterInterface) {
                return null;
            }

            $item = $this->cache->getItem($key);
            if (!$item->isHit()) {
                return null;
            }

            return $item->get();
        } catch (\Exception $e) {
            $this->logger->warning('Failed to get project tree cache', [
                'key' => $key,
                'userId' => $userId,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Set cached project tree data.
     *
     * @param string $userId            The user ID
     * @param bool   $includeArchived   Whether archived projects are included
     * @param bool   $includeTaskCounts Whether task counts are included
     * @param array  $tree              The tree data to cache
     */
    public function set(string $userId, bool $includeArchived, bool $includeTaskCounts, array $tree): void
    {
        $key = $this->buildKey($userId, $includeArchived, $includeTaskCounts);

        try {
            if (!$this->cache instanceof AdapterInterface) {
                return;
            }

            $item = $this->cache->getItem($key);
            $item->set($tree);
            $item->expiresAfter(self::TTL);
            $this->cache->save($item);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to set project tree cache', [
                'key' => $key,
                'userId' => $userId,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Invalidate all cache variants for a user.
     *
     * @param string $userId The user ID
     */
    public function invalidate(string $userId): void
    {
        if (!$this->cache instanceof AdapterInterface) {
            return;
        }

        // Clear all 4 variants (archived: true/false, taskCounts: true/false)
        $variants = [
            $this->buildKey($userId, false, false),
            $this->buildKey($userId, false, true),
            $this->buildKey($userId, true, false),
            $this->buildKey($userId, true, true),
        ];

        foreach ($variants as $key) {
            try {
                $this->cache->deleteItem($key);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to invalidate project tree cache', [
                    'key' => $key,
                    'userId' => $userId,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
