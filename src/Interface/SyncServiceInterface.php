<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\User;

/**
 * Interface for real-time sync change tracking.
 *
 * Tracks mutations across tasks, projects, and tags so that
 * other browser sessions can poll for changes.
 */
interface SyncServiceInterface
{
    /**
     * Record a change for sync tracking.
     *
     * @param User|null   $user        The user who made the change (null is silently ignored)
     * @param string      $entityType  The entity type (task, project, tag)
     * @param string      $action      The action (created, updated, deleted)
     * @param string|null $entityId    The entity ID (null for unpersisted entities, which are skipped)
     * @param string|null $originTabId The tab ID that originated the change
     */
    public function recordChange(?User $user, string $entityType, string $action, ?string $entityId, ?string $originTabId = null): void;

    /**
     * Get the current sync version for a user.
     *
     * @return int The current version (0 if no changes recorded)
     */
    public function getCurrentVersion(User $user): int;

    /**
     * Get changes since a given version.
     *
     * @param User $user         The user
     * @param int  $sinceVersion The version to get changes after
     *
     * @return array{version: int, changes: array<int, array<string, mixed>>}
     */
    public function getChangesSince(User $user, int $sinceVersion): array;
}
