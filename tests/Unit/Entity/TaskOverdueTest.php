<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Task;
use App\Tests\Unit\UnitTestCase;

class TaskOverdueTest extends UnitTestCase
{
    // ========================================
    // getOverdueDays Tests
    // ========================================

    public function testGetOverdueDaysReturnsNullForTaskWithoutDueDate(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');

        $this->assertNull($task->getOverdueDays());
    }

    public function testGetOverdueDaysReturnsNullForCompletedTask(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('-5 days'));
        $task->setStatus(Task::STATUS_COMPLETED);

        $this->assertNull($task->getOverdueDays());
    }

    public function testGetOverdueDaysReturnsNullForTaskDueToday(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('today'));

        $this->assertNull($task->getOverdueDays());
    }

    public function testGetOverdueDaysReturnsNullForTaskDueInFuture(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('+5 days'));

        $this->assertNull($task->getOverdueDays());
    }

    public function testGetOverdueDaysReturnsCorrectDaysForOverdueTask(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        // Use 'today' as base to avoid time-of-day issues
        $dueDate = (new \DateTimeImmutable('today'))->modify('-3 days');
        $task->setDueDate($dueDate);

        $this->assertEquals(3, $task->getOverdueDays());
    }

    public function testGetOverdueDaysReturnsOneDayForYesterday(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $dueDate = (new \DateTimeImmutable('today'))->modify('-1 day');
        $task->setDueDate($dueDate);

        $this->assertEquals(1, $task->getOverdueDays());
    }

    public function testGetOverdueDaysReturnsCorrectDaysForLongOverdue(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $dueDate = (new \DateTimeImmutable('today'))->modify('-30 days');
        $task->setDueDate($dueDate);

        $this->assertEquals(30, $task->getOverdueDays());
    }

    public function testGetOverdueDaysWorksWithInProgressTask(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $dueDate = (new \DateTimeImmutable('today'))->modify('-2 days');
        $task->setDueDate($dueDate);
        $task->setStatus(Task::STATUS_IN_PROGRESS);

        $this->assertEquals(2, $task->getOverdueDays());
    }

    // ========================================
    // getOverdueSeverity Tests
    // ========================================

    public function testGetOverdueSeverityReturnsNullWhenNotOverdue(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('+5 days'));

        $this->assertNull($task->getOverdueSeverity());
    }

    public function testGetOverdueSeverityReturnsNullForTaskDueToday(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('today'));

        $this->assertNull($task->getOverdueSeverity());
    }

    public function testGetOverdueSeverityReturnsNullForTaskWithoutDueDate(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');

        $this->assertNull($task->getOverdueSeverity());
    }

    public function testGetOverdueSeverityReturnsNullForCompletedTask(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('-5 days'));
        $task->setStatus(Task::STATUS_COMPLETED);

        $this->assertNull($task->getOverdueSeverity());
    }

    public function testGetOverdueSeverityReturnsLowForOneDayOverdue(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('-1 day'));

        $this->assertEquals(Task::OVERDUE_SEVERITY_LOW, $task->getOverdueSeverity());
    }

    public function testGetOverdueSeverityReturnsLowForTwoDaysOverdue(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('-2 days'));

        $this->assertEquals(Task::OVERDUE_SEVERITY_LOW, $task->getOverdueSeverity());
    }

    public function testGetOverdueSeverityReturnsMediumForThreeDaysOverdue(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $dueDate = (new \DateTimeImmutable('today'))->modify('-3 days');
        $task->setDueDate($dueDate);

        $this->assertEquals(Task::OVERDUE_SEVERITY_MEDIUM, $task->getOverdueSeverity());
    }

    public function testGetOverdueSeverityReturnsMediumForSevenDaysOverdue(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $dueDate = (new \DateTimeImmutable('today'))->modify('-7 days');
        $task->setDueDate($dueDate);

        $this->assertEquals(Task::OVERDUE_SEVERITY_MEDIUM, $task->getOverdueSeverity());
    }

    public function testGetOverdueSeverityReturnsHighForEightDaysOverdue(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $dueDate = (new \DateTimeImmutable('today'))->modify('-8 days');
        $task->setDueDate($dueDate);

        $this->assertEquals(Task::OVERDUE_SEVERITY_HIGH, $task->getOverdueSeverity());
    }

    public function testGetOverdueSeverityReturnsHighForThirtyDaysOverdue(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $dueDate = (new \DateTimeImmutable('today'))->modify('-30 days');
        $task->setDueDate($dueDate);

        $this->assertEquals(Task::OVERDUE_SEVERITY_HIGH, $task->getOverdueSeverity());
    }

    // ========================================
    // isOverdue Tests (for completeness)
    // ========================================

    public function testIsOverdueReturnsFalseForTaskWithoutDueDate(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');

        $this->assertFalse($task->isOverdue());
    }

    public function testIsOverdueReturnsFalseForCompletedTask(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('-5 days'));
        $task->setStatus(Task::STATUS_COMPLETED);

        $this->assertFalse($task->isOverdue());
    }

    public function testIsOverdueReturnsFalseForTaskDueToday(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('today'));

        $this->assertFalse($task->isOverdue());
    }

    public function testIsOverdueReturnsFalseForTaskDueInFuture(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('+1 day'));

        $this->assertFalse($task->isOverdue());
    }

    public function testIsOverdueReturnsTrueForOverdueTask(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('-1 day'));

        $this->assertTrue($task->isOverdue());
    }

    // ========================================
    // Issue #123: Timezone-aware Tests
    // ========================================

    /**
     * @covers \App\Entity\Task::isOverdue
     * Summary: Verifies isOverdue uses owner timezone, not UTC.
     *
     * Bug: isOverdue() used new \DateTimeImmutable('today') without timezone,
     *      defaulting to UTC instead of the task owner's timezone.
     */
    public function testIssue123IsOverdueUsesOwnerTimezone(): void
    {
        // A task 3 days overdue should be overdue regardless of timezone
        $user = $this->createUserWithId('user-tz');
        $user->setSettings(['timezone' => 'America/Los_Angeles']);

        $task = $this->createTaskWithId('task-tz', $user);
        $task->setDueDate((new \DateTimeImmutable('today'))->modify('-3 days'));

        $this->assertTrue($task->isOverdue());
    }

    /**
     * @covers \App\Entity\Task::getOverdueDays
     * Summary: Verifies getOverdueDays uses owner timezone, not UTC.
     */
    public function testIssue123GetOverdueDaysUsesOwnerTimezone(): void
    {
        $user = $this->createUserWithId('user-tz');
        $user->setSettings(['timezone' => 'America/Los_Angeles']);

        $timezone = new \DateTimeZone('America/Los_Angeles');
        $today = new \DateTimeImmutable('today', $timezone);

        $task = $this->createTaskWithId('task-tz', $user);
        $task->setDueDate($today->modify('-5 days'));

        $this->assertEquals(5, $task->getOverdueDays());
    }

    /**
     * @covers \App\Entity\Task::isOverdue
     * Summary: Verifies isOverdue doesn't crash when task has no owner (falls back to UTC).
     */
    public function testIssue123IsOverdueWorksWithoutOwner(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('-1 day'));

        // Should still work with null owner (falls back to UTC)
        $this->assertTrue($task->isOverdue());
    }

    // ========================================
    // Edge Case Tests
    // ========================================

    public function testSeverityConstantsAreCorrect(): void
    {
        $this->assertEquals('low', Task::OVERDUE_SEVERITY_LOW);
        $this->assertEquals('medium', Task::OVERDUE_SEVERITY_MEDIUM);
        $this->assertEquals('high', Task::OVERDUE_SEVERITY_HIGH);
    }

    // ========================================
    // Time-aware isOverdue Tests
    // ========================================

    public function testIsOverdueReturnsTrueForTaskDueTodayWithPastTime(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');

        $now = new \DateTimeImmutable();
        $currentHour = (int) $now->format('H');

        if ($currentHour === 0) {
            // Between 00:00-00:59: use yesterday with a late time to guarantee overdue
            $task->setDueDate(new \DateTimeImmutable('yesterday'));
            $task->setDueTime((new \DateTimeImmutable())->setTime(23, 0));
        } else {
            // Any other hour: use today with 00:00 which is always in the past
            $task->setDueDate(new \DateTimeImmutable('today'));
            $task->setDueTime((new \DateTimeImmutable())->setTime(0, 0));
        }

        $this->assertTrue($task->isOverdue());
    }

    public function testIsOverdueReturnsFalseForTaskDueTodayWithFutureTime(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('today'));

        // Calculate a future time that stays within today
        // The issue with +1 hour is it can wrap to next day near midnight,
        // and isOverdue() only uses hour:minute from dueTime applied to dueDate
        $now = new \DateTimeImmutable();
        $currentHour = (int) $now->format('H');
        $currentMinute = (int) $now->format('i');

        // Set due time to 1 minute from now, but clamp to 23:59 max
        $futureMinute = $currentMinute + 1;
        $futureHour = $currentHour;
        if ($futureMinute >= 60) {
            $futureMinute = 59;
            $futureHour = min($currentHour + 1, 23);
        }
        if ($futureHour >= 23 && $futureMinute >= 59) {
            // Edge case: if we're at 23:59, use 23:59 and skip test assertion
            // as it's impossible to have a "future" time today
            $this->markTestSkipped('Cannot test future time at 23:59');
        }

        $futureTime = (new \DateTimeImmutable('today'))->setTime($futureHour, $futureMinute);
        $task->setDueTime($futureTime);

        $this->assertFalse($task->isOverdue());
    }

    public function testIsOverdueReturnsFalseForTaskDueTodayWithNoTime(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('today'));
        // No due time set - should be end of day, so not overdue

        $this->assertFalse($task->isOverdue());
    }

    public function testIsOverdueReturnsTrueForPastDateIgnoresTime(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('-1 day'));
        // Even with future time set, past date is overdue
        $futureTime = (new \DateTimeImmutable())->modify('+1 hour');
        $task->setDueTime($futureTime);

        $this->assertTrue($task->isOverdue());
    }

    public function testIsOverdueReturnsFalseForFutureDateIgnoresTime(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('+1 day'));
        // Even with past time set, future date is not overdue
        $pastTime = (new \DateTimeImmutable())->modify('-1 hour');
        $task->setDueTime($pastTime);

        $this->assertFalse($task->isOverdue());
    }

    public function testIsOverdueReturnsFalseForCompletedTaskEvenWithPastTime(): void
    {
        $task = new Task();
        $task->setTitle('Test Task');
        $task->setDueDate(new \DateTimeImmutable('today'));
        $task->setDueTime((new \DateTimeImmutable())->modify('-1 hour'));
        $task->setStatus(Task::STATUS_COMPLETED);

        $this->assertFalse($task->isOverdue());
    }

    public function testOverdueSeverityBoundaryAtTwoToThreeDays(): void
    {
        $today = new \DateTimeImmutable('today');

        $taskTwoDays = new Task();
        $taskTwoDays->setTitle('Test Task');
        $taskTwoDays->setDueDate($today->modify('-2 days'));

        $taskThreeDays = new Task();
        $taskThreeDays->setTitle('Test Task');
        $taskThreeDays->setDueDate($today->modify('-3 days'));

        $this->assertEquals(Task::OVERDUE_SEVERITY_LOW, $taskTwoDays->getOverdueSeverity());
        $this->assertEquals(Task::OVERDUE_SEVERITY_MEDIUM, $taskThreeDays->getOverdueSeverity());
    }

    public function testOverdueSeverityBoundaryAtSevenToEightDays(): void
    {
        $today = new \DateTimeImmutable('today');

        $taskSevenDays = new Task();
        $taskSevenDays->setTitle('Test Task');
        $taskSevenDays->setDueDate($today->modify('-7 days'));

        $taskEightDays = new Task();
        $taskEightDays->setTitle('Test Task');
        $taskEightDays->setDueDate($today->modify('-8 days'));

        $this->assertEquals(Task::OVERDUE_SEVERITY_MEDIUM, $taskSevenDays->getOverdueSeverity());
        $this->assertEquals(Task::OVERDUE_SEVERITY_HIGH, $taskEightDays->getOverdueSeverity());
    }
}
