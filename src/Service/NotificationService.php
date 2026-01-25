<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Task;
use App\Entity\User;
use App\Message\SendEmailNotification;
use App\Message\SendPushNotification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Create a notification for a task that is due soon.
     */
    public function notifyTaskDueSoon(Task $task): ?Notification
    {
        $owner = $task->getOwner();
        if ($owner === null) {
            return null;
        }

        $hours = $owner->getDueSoonHours();
        $title = sprintf('Task due in %d hours', $hours);
        $message = sprintf('"%s" is due soon.', $task->getTitle());

        return $this->createNotification(
            owner: $owner,
            type: Notification::TYPE_TASK_DUE_SOON,
            title: $title,
            message: $message,
            data: ['taskId' => $task->getId(), 'taskTitle' => $task->getTitle()]
        );
    }

    /**
     * Create a notification for an overdue task.
     */
    public function notifyTaskOverdue(Task $task): ?Notification
    {
        $owner = $task->getOwner();
        if ($owner === null) {
            return null;
        }

        $title = 'Task overdue';
        $message = sprintf('"%s" is now overdue.', $task->getTitle());

        return $this->createNotification(
            owner: $owner,
            type: Notification::TYPE_TASK_OVERDUE,
            title: $title,
            message: $message,
            data: ['taskId' => $task->getId(), 'taskTitle' => $task->getTitle()]
        );
    }

    /**
     * Create a notification for a task due today.
     */
    public function notifyTaskDueToday(Task $task): ?Notification
    {
        $owner = $task->getOwner();
        if ($owner === null) {
            return null;
        }

        $title = 'Task due today';
        $message = sprintf('"%s" is due today.', $task->getTitle());

        return $this->createNotification(
            owner: $owner,
            type: Notification::TYPE_TASK_DUE_TODAY,
            title: $title,
            message: $message,
            data: ['taskId' => $task->getId(), 'taskTitle' => $task->getTitle()]
        );
    }

    /**
     * Create a notification when a recurring task instance is created.
     */
    public function notifyRecurringTaskCreated(Task $task, Task $originalTask): ?Notification
    {
        $owner = $task->getOwner();
        if ($owner === null) {
            return null;
        }

        $title = 'Recurring task created';
        $message = sprintf('A new instance of "%s" has been created.', $originalTask->getTitle());

        return $this->createNotification(
            owner: $owner,
            type: Notification::TYPE_RECURRING_CREATED,
            title: $title,
            message: $message,
            data: [
                'taskId' => $task->getId(),
                'originalTaskId' => $originalTask->getId(),
                'taskTitle' => $task->getTitle(),
            ]
        );
    }

    /**
     * Create a system notification.
     */
    public function notifySystem(User $owner, string $title, ?string $message = null, array $data = []): Notification
    {
        return $this->createNotification(
            owner: $owner,
            type: Notification::TYPE_SYSTEM,
            title: $title,
            message: $message,
            data: $data
        );
    }

    /**
     * Create a notification and dispatch messages for email/push delivery.
     *
     * @param array<string, mixed> $data
     */
    private function createNotification(
        User $owner,
        string $type,
        string $title,
        ?string $message = null,
        array $data = []
    ): Notification {
        // Always create in-app notification
        $notification = new Notification();
        $notification->setOwner($owner)
            ->setType($type)
            ->setTitle($title)
            ->setMessage($message)
            ->setData($data);

        $this->notificationRepository->save($notification, true);

        // Dispatch async messages for email and push if enabled and not in quiet hours
        if (!$owner->isInQuietHours()) {
            $this->dispatchChannelMessages($notification);
        }

        return $notification;
    }

    /**
     * Dispatch messages to email and push channels based on user preferences.
     */
    private function dispatchChannelMessages(Notification $notification): void
    {
        $owner = $notification->getOwner();
        if ($owner === null) {
            return;
        }

        $type = $notification->getType();

        // Map notification type to setting key
        $settingKey = match ($type) {
            Notification::TYPE_TASK_DUE_SOON => 'taskDueSoon',
            Notification::TYPE_TASK_OVERDUE => 'taskOverdue',
            Notification::TYPE_TASK_DUE_TODAY => 'taskDueToday',
            Notification::TYPE_RECURRING_CREATED => 'recurringCreated',
            Notification::TYPE_SYSTEM => 'taskDueSoon', // System notifications always sent
            default => null,
        };

        // Dispatch email notification if enabled
        if ($owner->isNotificationEnabled($settingKey, 'email')) {
            $this->messageBus->dispatch(new SendEmailNotification(
                userId: $owner->getId(),
                notificationId: $notification->getId(),
                type: $notification->getType(),
                title: $notification->getTitle(),
                message: $notification->getMessage(),
                data: $notification->getData()
            ));
        }

        // Dispatch push notification if enabled
        if ($owner->isNotificationEnabled($settingKey, 'push')) {
            $this->messageBus->dispatch(new SendPushNotification(
                userId: $owner->getId(),
                notificationId: $notification->getId(),
                type: $notification->getType(),
                title: $notification->getTitle(),
                message: $notification->getMessage(),
                data: $notification->getData()
            ));
        }
    }

    /**
     * Get notifications for a user.
     *
     * @return Notification[]
     */
    public function getNotifications(User $owner, int $limit = 50, int $offset = 0): array
    {
        return $this->notificationRepository->findByOwner($owner, $limit, $offset);
    }

    /**
     * Get unread notifications for a user.
     *
     * @return Notification[]
     */
    public function getUnreadNotifications(User $owner, int $limit = 50): array
    {
        return $this->notificationRepository->findUnreadByOwner($owner, $limit);
    }

    /**
     * Get unread notification count for a user.
     */
    public function getUnreadCount(User $owner): int
    {
        return $this->notificationRepository->countUnreadByOwner($owner);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->markAsRead();
        $this->entityManager->flush();
    }

    /**
     * Mark all notifications as read for a user.
     *
     * @return int Number of notifications marked as read
     */
    public function markAllAsRead(User $owner): int
    {
        return $this->notificationRepository->markAllAsReadByOwner($owner);
    }
}
