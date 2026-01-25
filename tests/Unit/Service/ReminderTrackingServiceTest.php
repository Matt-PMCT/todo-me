<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\RedisService;
use App\Service\ReminderTrackingService;
use App\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ReminderTrackingServiceTest extends UnitTestCase
{
    private RedisService&MockObject $redisService;
    private ReminderTrackingService $reminderTrackingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redisService = $this->createMock(RedisService::class);
        $this->reminderTrackingService = new ReminderTrackingService($this->redisService);
    }

    // ========================================
    // hasBeenSent Tests
    // ========================================

    public function testHasBeenSentReturnsTrueWhenKeyExists(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $this->redisService->expects($this->once())
            ->method('get')
            ->with('reminder:task-123:due_soon')
            ->willReturn('1234567890');

        $result = $this->reminderTrackingService->hasBeenSent($task, 'due_soon');

        $this->assertTrue($result);
    }

    public function testHasBeenSentReturnsFalseWhenKeyDoesNotExist(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $this->redisService->expects($this->once())
            ->method('get')
            ->with('reminder:task-123:due_soon')
            ->willReturn(null);

        $result = $this->reminderTrackingService->hasBeenSent($task, 'due_soon');

        $this->assertFalse($result);
    }

    // ========================================
    // markAsSent Tests
    // ========================================

    public function testMarkAsSentSetsKeyWithTtl(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $this->redisService->expects($this->once())
            ->method('set')
            ->with(
                'reminder:task-123:due_soon',
                $this->isType('string'),
                604800 // 7 days
            );

        $this->reminderTrackingService->markAsSent($task, 'due_soon');
    }

    // ========================================
    // shouldSendDueSoonReminder Tests
    // ========================================

    public function testShouldSendDueSoonReminderReturnsTrueWhenNotSent(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $this->redisService->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $result = $this->reminderTrackingService->shouldSendDueSoonReminder($task);

        $this->assertTrue($result);
    }

    public function testShouldSendDueSoonReminderReturnsFalseWhenAlreadySent(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $this->redisService->expects($this->once())
            ->method('get')
            ->willReturn('1234567890');

        $result = $this->reminderTrackingService->shouldSendDueSoonReminder($task);

        $this->assertFalse($result);
    }

    // ========================================
    // Overdue Reminder Tests
    // ========================================

    public function testShouldSendOverdueReminderIncludesDateInKey(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->redisService->expects($this->once())
            ->method('get')
            ->with("reminder:task-123:overdue:{$today}")
            ->willReturn(null);

        $result = $this->reminderTrackingService->shouldSendOverdueReminder($task);

        $this->assertTrue($result);
    }

    public function testMarkOverdueReminderSentIncludesDateInKey(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->redisService->expects($this->once())
            ->method('set')
            ->with(
                "reminder:task-123:overdue:{$today}",
                $this->isType('string'),
                604800
            );

        $this->reminderTrackingService->markOverdueReminderSent($task);
    }

    // ========================================
    // Due Today Reminder Tests
    // ========================================

    public function testShouldSendDueTodayReminderIncludesDateInKey(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $this->redisService->expects($this->once())
            ->method('get')
            ->with("reminder:task-123:due_today:{$today}")
            ->willReturn(null);

        $result = $this->reminderTrackingService->shouldSendDueTodayReminder($task);

        $this->assertTrue($result);
    }

    // ========================================
    // clearReminders Tests
    // ========================================

    public function testClearRemindersDeletesAllKeysForTask(): void
    {
        $user = $this->createUserWithId();
        $task = $this->createTaskWithId('task-123', $user);

        $this->redisService->expects($this->once())
            ->method('keys')
            ->with('reminder:task-123:*')
            ->willReturn([
                'reminder:task-123:due_soon',
                'reminder:task-123:overdue:2024-01-01',
            ]);

        $this->redisService->expects($this->exactly(2))
            ->method('delete');

        $this->reminderTrackingService->clearReminders($task);
    }
}
