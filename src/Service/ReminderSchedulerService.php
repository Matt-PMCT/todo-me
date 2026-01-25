<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Repository\TaskRepository;
use Psr\Log\LoggerInterface;

/**
 * Service for processing scheduled reminders.
 * Checks for tasks that need notifications (due soon, overdue, due today)
 * and dispatches notifications while tracking what has been sent.
 */
final class ReminderSchedulerService
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly NotificationService $notificationService,
        private readonly ReminderTrackingService $reminderTrackingService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Process all reminders (due soon, overdue).
     *
     * @return array{dueSoon: int, overdue: int, dueToday: int}
     */
    public function processReminders(): array
    {
        $stats = [
            'dueSoon' => 0,
            'overdue' => 0,
            'dueToday' => 0,
        ];

        // Process due soon tasks
        $stats['dueSoon'] = $this->processDueSoonReminders();

        // Process overdue tasks
        $stats['overdue'] = $this->processOverdueReminders();

        return $stats;
    }

    /**
     * Process due-today notifications (typically run once in the morning).
     *
     * @return int Number of notifications sent
     */
    public function processDueTodayNotifications(): int
    {
        $count = 0;
        $today = new \DateTimeImmutable('today');

        // Get all users with tasks due today
        $tasks = $this->findTasksDueToday();

        foreach ($tasks as $task) {
            if ($this->shouldNotifyDueToday($task)) {
                $this->notificationService->notifyTaskDueToday($task);
                $this->reminderTrackingService->markDueTodayReminderSent($task);
                $count++;

                $this->logger->info('Due today notification sent', [
                    'taskId' => $task->getId(),
                    'taskTitle' => $task->getTitle(),
                    'userId' => $task->getOwner()?->getId(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Process due-soon reminders.
     *
     * @return int Number of notifications sent
     */
    private function processDueSoonReminders(): int
    {
        $count = 0;
        $tasks = $this->findTasksDueSoon();

        foreach ($tasks as $task) {
            if ($this->shouldNotifyDueSoon($task)) {
                $this->notificationService->notifyTaskDueSoon($task);
                $this->reminderTrackingService->markAsSent($task, 'due_soon');
                $count++;

                $this->logger->info('Due soon notification sent', [
                    'taskId' => $task->getId(),
                    'taskTitle' => $task->getTitle(),
                    'userId' => $task->getOwner()?->getId(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Process overdue reminders.
     *
     * @return int Number of notifications sent
     */
    private function processOverdueReminders(): int
    {
        $count = 0;
        $tasks = $this->findOverdueTasks();

        foreach ($tasks as $task) {
            if ($this->shouldNotifyOverdue($task)) {
                $this->notificationService->notifyTaskOverdue($task);
                $this->reminderTrackingService->markOverdueReminderSent($task);
                $count++;

                $this->logger->info('Overdue notification sent', [
                    'taskId' => $task->getId(),
                    'taskTitle' => $task->getTitle(),
                    'userId' => $task->getOwner()?->getId(),
                    'daysOverdue' => $task->getOverdueDays(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Find tasks that are due soon based on user preferences.
     *
     * @return Task[]
     */
    private function findTasksDueSoon(): array
    {
        // Find all incomplete tasks with a due date
        $now = new \DateTimeImmutable();
        $maxHours = 48; // Maximum look-ahead window

        return $this->taskRepository->createQueryBuilder('t')
            ->where('t.dueDate IS NOT NULL')
            ->andWhere('t.status != :completed')
            ->andWhere('t.dueDate >= :today')
            ->andWhere('t.dueDate <= :maxDate')
            ->setParameter('completed', Task::STATUS_COMPLETED)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->setParameter('maxDate', $now->modify("+{$maxHours} hours"))
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tasks that are due today.
     *
     * @return Task[]
     */
    private function findTasksDueToday(): array
    {
        $today = new \DateTimeImmutable('today');

        return $this->taskRepository->createQueryBuilder('t')
            ->where('t.dueDate = :today')
            ->andWhere('t.status != :completed')
            ->setParameter('today', $today)
            ->setParameter('completed', Task::STATUS_COMPLETED)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all overdue tasks.
     *
     * @return Task[]
     */
    private function findOverdueTasks(): array
    {
        $today = new \DateTimeImmutable('today');

        return $this->taskRepository->createQueryBuilder('t')
            ->where('t.dueDate < :today')
            ->andWhere('t.status != :completed')
            ->setParameter('today', $today)
            ->setParameter('completed', Task::STATUS_COMPLETED)
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if a due-soon notification should be sent for this task.
     */
    private function shouldNotifyDueSoon(Task $task): bool
    {
        $owner = $task->getOwner();
        if ($owner === null || $task->getDueDate() === null) {
            return false;
        }

        // Check if user has this notification type enabled
        if (!$owner->isNotificationEnabled('taskDueSoon', 'email') &&
            !$owner->isNotificationEnabled('taskDueSoon', 'push')) {
            return false;
        }

        // Check if task is within the user's due-soon window
        $dueSoonHours = $owner->getDueSoonHours();
        $dueDate = $task->getDueDate();
        $now = new \DateTimeImmutable();
        $dueSoonThreshold = $now->modify("+{$dueSoonHours} hours");

        if ($dueDate > $dueSoonThreshold) {
            return false;
        }

        // Check if reminder has already been sent
        return $this->reminderTrackingService->shouldSendDueSoonReminder($task);
    }

    /**
     * Check if an overdue notification should be sent for this task.
     */
    private function shouldNotifyOverdue(Task $task): bool
    {
        $owner = $task->getOwner();
        if ($owner === null) {
            return false;
        }

        // Check if user has this notification type enabled
        if (!$owner->isNotificationEnabled('taskOverdue', 'email') &&
            !$owner->isNotificationEnabled('taskOverdue', 'push')) {
            return false;
        }

        // Check if reminder has already been sent today
        return $this->reminderTrackingService->shouldSendOverdueReminder($task);
    }

    /**
     * Check if a due-today notification should be sent for this task.
     */
    private function shouldNotifyDueToday(Task $task): bool
    {
        $owner = $task->getOwner();
        if ($owner === null) {
            return false;
        }

        // Check if user has this notification type enabled
        if (!$owner->isNotificationEnabled('taskDueToday', 'email') &&
            !$owner->isNotificationEnabled('taskDueToday', 'push')) {
            return false;
        }

        // Check if reminder has already been sent today
        return $this->reminderTrackingService->shouldSendDueTodayReminder($task);
    }
}
