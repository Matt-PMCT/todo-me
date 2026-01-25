<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;

/**
 * Tracks which reminders have been sent to prevent duplicate notifications.
 * Uses Redis for fast lookups with a 7-day TTL.
 */
final class ReminderTrackingService
{
    private const KEY_PREFIX = 'reminder:';
    private const TTL_SECONDS = 604800; // 7 days

    public function __construct(
        private readonly RedisService $redisService,
    ) {
    }

    /**
     * Check if a reminder has already been sent for this task and type.
     */
    public function hasBeenSent(Task $task, string $reminderType): bool
    {
        $key = $this->buildKey($task, $reminderType);

        return $this->redisService->get($key) !== null;
    }

    /**
     * Mark a reminder as sent for this task and type.
     */
    public function markAsSent(Task $task, string $reminderType): void
    {
        $key = $this->buildKey($task, $reminderType);
        $this->redisService->set($key, (string) time(), self::TTL_SECONDS);
    }

    /**
     * Check if a due-soon reminder should be sent.
     * Returns false if one has already been sent within the due-soon window.
     */
    public function shouldSendDueSoonReminder(Task $task): bool
    {
        return !$this->hasBeenSent($task, 'due_soon');
    }

    /**
     * Check if an overdue reminder should be sent.
     * Allows one overdue reminder per day by including the date in the key.
     */
    public function shouldSendOverdueReminder(Task $task): bool
    {
        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $key = $this->buildKey($task, 'overdue:'.$today);

        return $this->redisService->get($key) === null;
    }

    /**
     * Mark an overdue reminder as sent for today.
     */
    public function markOverdueReminderSent(Task $task): void
    {
        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $key = $this->buildKey($task, 'overdue:'.$today);
        $this->redisService->set($key, (string) time(), self::TTL_SECONDS);
    }

    /**
     * Check if a due-today reminder should be sent.
     */
    public function shouldSendDueTodayReminder(Task $task): bool
    {
        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $key = $this->buildKey($task, 'due_today:'.$today);

        return $this->redisService->get($key) === null;
    }

    /**
     * Mark a due-today reminder as sent.
     */
    public function markDueTodayReminderSent(Task $task): void
    {
        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $key = $this->buildKey($task, 'due_today:'.$today);
        $this->redisService->set($key, (string) time(), self::TTL_SECONDS);
    }

    /**
     * Clear all reminders for a task (useful when task is updated).
     */
    public function clearReminders(Task $task): void
    {
        $pattern = self::KEY_PREFIX.$task->getId().':*';
        $keys = $this->redisService->keys($pattern);

        foreach ($keys as $key) {
            $this->redisService->delete($key);
        }
    }

    /**
     * Build the Redis key for a reminder.
     */
    private function buildKey(Task $task, string $reminderType): string
    {
        return self::KEY_PREFIX.$task->getId().':'.$reminderType;
    }
}
