<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Interface\SyncServiceInterface;

/**
 * Redis-backed change tracking for real-time sync.
 *
 * Uses two Redis keys per user:
 * - sync:version:{userId} - integer counter (no TTL)
 * - sync:changes:{userId} - list of JSON change entries (5-min TTL)
 */
final class SyncService implements SyncServiceInterface
{
    private const VERSION_KEY_PREFIX = 'sync:version:';
    private const CHANGES_KEY_PREFIX = 'sync:changes:';
    private const CHANGES_TTL = 300; // 5 minutes
    private const MAX_CHANGES = 100;

    public function __construct(
        private readonly RedisService $redisService,
    ) {
    }

    public function recordChange(?User $user, string $entityType, string $action, ?string $entityId, ?string $originTabId = null): void
    {
        if ($user === null || $entityId === null) {
            return;
        }

        $userId = $user->getId();
        if ($userId === null) {
            return;
        }

        $versionKey = self::VERSION_KEY_PREFIX.$userId;
        $changesKey = self::CHANGES_KEY_PREFIX.$userId;

        // Increment version
        $version = $this->redisService->increment($versionKey);

        // Build change entry
        $change = [
            'version' => $version,
            'entityType' => $entityType,
            'action' => $action,
            'entityId' => $entityId,
            'originTabId' => $originTabId,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
        ];

        // Push to changes list
        $changeJson = json_encode($change, JSON_THROW_ON_ERROR);
        $this->redisService->listPush($changesKey, $changeJson);

        // Trim to last MAX_CHANGES entries
        $this->redisService->listTrim($changesKey, -self::MAX_CHANGES, -1);

        // Set TTL on changes list
        $this->redisService->expire($changesKey, self::CHANGES_TTL);
    }

    public function getCurrentVersion(User $user): int
    {
        $userId = $user->getId();
        if ($userId === null) {
            return 0;
        }

        $value = $this->redisService->get(self::VERSION_KEY_PREFIX.$userId);

        return $value !== null ? (int) $value : 0;
    }

    public function getChangesSince(User $user, int $sinceVersion): array
    {
        $currentVersion = $this->getCurrentVersion($user);

        if ($currentVersion <= $sinceVersion) {
            return [
                'version' => $currentVersion,
                'changes' => [],
            ];
        }

        $userId = $user->getId();
        if ($userId === null) {
            return ['version' => 0, 'changes' => []];
        }

        $changesKey = self::CHANGES_KEY_PREFIX.$userId;
        $rawChanges = $this->redisService->listRange($changesKey, 0, -1);

        $changes = [];
        foreach ($rawChanges as $rawChange) {
            $change = json_decode($rawChange, true);
            if (is_array($change) && ($change['version'] ?? 0) > $sinceVersion) {
                $changes[] = $change;
            }
        }

        return [
            'version' => $currentVersion,
            'changes' => $changes,
        ];
    }
}
