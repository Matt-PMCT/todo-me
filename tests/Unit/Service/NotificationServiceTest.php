<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Message\SendEmailNotification;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use App\Tests\Unit\UnitTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class NotificationServiceTest extends UnitTestCase
{
    private NotificationRepository&MockObject $notificationRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private MessageBusInterface&MockObject $messageBus;
    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationRepository = $this->createMock(NotificationRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->notificationService = new NotificationService(
            $this->notificationRepository,
            $this->entityManager,
            $this->messageBus,
        );
    }

    // ========================================
    // notifyTaskDueSoon Tests
    // ========================================

    public function testNotifyTaskDueSoonCreatesNotification(): void
    {
        $user = $this->createUserWithNotificationSettings([
            'emailEnabled' => true,
            'taskDueSoon' => true,
        ]);
        $task = $this->createTaskWithId('task-1', $user, 'Test Task');

        $this->notificationRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Notification::class), true)
            ->willReturnCallback(function (Notification $notification) {
                // Simulate the database setting the ID
                $this->setEntityId($notification, 'notification-123');
            });

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SendEmailNotification::class))
            ->willReturn(new Envelope(new \stdClass()));

        $notification = $this->notificationService->notifyTaskDueSoon($task);

        $this->assertNotNull($notification);
        $this->assertEquals(Notification::TYPE_TASK_DUE_SOON, $notification->getType());
        $this->assertStringContainsString('Test Task', $notification->getMessage());
    }

    public function testNotifyTaskDueSoonReturnsNullForTaskWithoutOwner(): void
    {
        $task = $this->createTaskWithId('task-1', null, 'Test Task');

        $this->notificationRepository->expects($this->never())->method('save');
        $this->messageBus->expects($this->never())->method('dispatch');

        $notification = $this->notificationService->notifyTaskDueSoon($task);

        $this->assertNull($notification);
    }

    // ========================================
    // notifyTaskOverdue Tests
    // ========================================

    public function testNotifyTaskOverdueCreatesNotification(): void
    {
        $user = $this->createUserWithNotificationSettings([
            'emailEnabled' => true,
            'taskOverdue' => true,
        ]);
        $task = $this->createTaskWithId('task-1', $user, 'Overdue Task');

        $this->notificationRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Notification::class), true)
            ->willReturnCallback(function (Notification $notification) {
                $this->setEntityId($notification, 'notification-456');
            });

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $notification = $this->notificationService->notifyTaskOverdue($task);

        $this->assertNotNull($notification);
        $this->assertEquals(Notification::TYPE_TASK_OVERDUE, $notification->getType());
    }

    // ========================================
    // notifyTaskDueToday Tests
    // ========================================

    public function testNotifyTaskDueTodayCreatesNotification(): void
    {
        $user = $this->createUserWithNotificationSettings([
            'emailEnabled' => true,
            'taskDueToday' => true,
        ]);
        $task = $this->createTaskWithId('task-1', $user, 'Today Task');

        $this->notificationRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Notification::class), true)
            ->willReturnCallback(function (Notification $notification) {
                $this->setEntityId($notification, 'notification-789');
            });

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $notification = $this->notificationService->notifyTaskDueToday($task);

        $this->assertNotNull($notification);
        $this->assertEquals(Notification::TYPE_TASK_DUE_TODAY, $notification->getType());
    }

    // ========================================
    // Notification Retrieval Tests
    // ========================================

    public function testGetUnreadCount(): void
    {
        $user = $this->createUserWithId();

        $this->notificationRepository->expects($this->once())
            ->method('countUnreadByOwner')
            ->with($user)
            ->willReturn(5);

        $count = $this->notificationService->getUnreadCount($user);

        $this->assertEquals(5, $count);
    }

    public function testMarkAsRead(): void
    {
        $notification = new Notification();
        $notification->setType(Notification::TYPE_SYSTEM)
            ->setTitle('Test');

        $this->entityManager->expects($this->once())->method('flush');

        $this->notificationService->markAsRead($notification);

        $this->assertTrue($notification->isRead());
        $this->assertNotNull($notification->getReadAt());
    }

    public function testMarkAllAsRead(): void
    {
        $user = $this->createUserWithId();

        $this->notificationRepository->expects($this->once())
            ->method('markAllAsReadByOwner')
            ->with($user)
            ->willReturn(3);

        $count = $this->notificationService->markAllAsRead($user);

        $this->assertEquals(3, $count);
    }

    // ========================================
    // Quiet Hours Tests
    // ========================================

    public function testNotificationNotDispatchedDuringQuietHours(): void
    {
        $user = $this->createUserWithNotificationSettings([
            'emailEnabled' => true,
            'taskDueSoon' => true,
            'quietHoursEnabled' => true,
            'quietHoursStart' => '00:00',
            'quietHoursEnd' => '23:59',
        ]);
        $task = $this->createTaskWithId('task-1', $user, 'Test Task');

        $this->notificationRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Notification::class), true);

        // Should NOT dispatch message during quiet hours
        $this->messageBus->expects($this->never())->method('dispatch');

        $notification = $this->notificationService->notifyTaskDueSoon($task);

        $this->assertNotNull($notification);
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createUserWithNotificationSettings(array $settings): User
    {
        $user = $this->createUserWithId('user-123', 'test@example.com');
        $user->setNotificationSettings(array_merge(
            User::getDefaultNotificationSettings(),
            $settings
        ));

        return $user;
    }
}
