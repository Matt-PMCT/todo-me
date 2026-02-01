<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;
use App\Entity\User;
use App\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class TaskTest extends UnitTestCase
{
    // ========================================
    // Status Constants Tests
    // ========================================

    public function testStatusConstantsExist(): void
    {
        $this->assertEquals('pending', Task::STATUS_PENDING);
        $this->assertEquals('in_progress', Task::STATUS_IN_PROGRESS);
        $this->assertEquals('completed', Task::STATUS_COMPLETED);
    }

    public function testStatusesArrayContainsAllStatuses(): void
    {
        $this->assertContains(Task::STATUS_PENDING, Task::STATUSES);
        $this->assertContains(Task::STATUS_IN_PROGRESS, Task::STATUSES);
        $this->assertContains(Task::STATUS_COMPLETED, Task::STATUSES);
        $this->assertCount(3, Task::STATUSES);
    }

    // ========================================
    // Priority Constants Tests
    // ========================================

    public function testPriorityConstantsExist(): void
    {
        $this->assertEquals(1, Task::PRIORITY_MIN);
        $this->assertEquals(5, Task::PRIORITY_MAX);
        $this->assertEquals(3, Task::PRIORITY_DEFAULT);
    }

    // ========================================
    // Priority Validation Tests
    // ========================================

    #[DataProvider('validPriorityProvider')]
    public function testSetPriorityAcceptsValidValues(int $priority): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $task->setPriority($priority);

        $this->assertEquals($priority, $task->getPriority());
    }

    public static function validPriorityProvider(): array
    {
        return [
            'min' => [1],
            'low' => [2],
            'default' => [3],
            'high' => [4],
            'max' => [5],
        ];
    }

    #[DataProvider('invalidPriorityProvider')]
    public function testSetPriorityRejectsInvalidValues(int $priority): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $this->expectException(\InvalidArgumentException::class);
        $task->setPriority($priority);
    }

    public static function invalidPriorityProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'too_high' => [6],
            'much_too_high' => [100],
        ];
    }

    public function testPriorityDefaultsToDefaultValue(): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $this->assertEquals(Task::PRIORITY_DEFAULT, $task->getPriority());
    }

    // ========================================
    // Completed At Tests
    // ========================================

    public function testCompletedAtIsSetWhenStatusChangesToCompleted(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $task->setStatus(Task::STATUS_PENDING);

        $this->assertNull($task->getCompletedAt());

        $task->setStatus(Task::STATUS_COMPLETED);

        $this->assertNotNull($task->getCompletedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $task->getCompletedAt());
    }

    public function testCompletedAtIsClearedWhenStatusChangesFromCompleted(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $task->setStatus(Task::STATUS_COMPLETED);

        $this->assertNotNull($task->getCompletedAt());

        $task->setStatus(Task::STATUS_IN_PROGRESS);

        $this->assertNull($task->getCompletedAt());
    }

    public function testCompletedAtIsNotChangedWhenAlreadyCompleted(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $task->setStatus(Task::STATUS_COMPLETED);
        $originalCompletedAt = $task->getCompletedAt();

        // Small delay to ensure time difference if it were to change
        usleep(1000);

        $task->setStatus(Task::STATUS_COMPLETED);

        $this->assertEquals($originalCompletedAt, $task->getCompletedAt());
    }

    public function testSetCompletedAtManually(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $customDate = new \DateTimeImmutable('2024-01-15 10:00:00');

        $task->setCompletedAt($customDate);

        $this->assertEquals($customDate, $task->getCompletedAt());
    }

    // ========================================
    // Status Helper Methods Tests
    // ========================================

    public function testIsPendingReturnsTrueWhenPending(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $task->setStatus(Task::STATUS_PENDING);

        $this->assertTrue($task->isPending());
        $this->assertFalse($task->isInProgress());
        $this->assertFalse($task->isCompleted());
    }

    public function testIsInProgressReturnsTrueWhenInProgress(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $task->setStatus(Task::STATUS_IN_PROGRESS);

        $this->assertFalse($task->isPending());
        $this->assertTrue($task->isInProgress());
        $this->assertFalse($task->isCompleted());
    }

    public function testIsCompletedReturnsTrueWhenCompleted(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $task->setStatus(Task::STATUS_COMPLETED);

        $this->assertFalse($task->isPending());
        $this->assertFalse($task->isInProgress());
        $this->assertTrue($task->isCompleted());
    }

    // ========================================
    // Is Overdue Tests
    // ========================================

    public function testIsOverdueReturnsFalseWhenNoDueDate(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $task->setDueDate(null);

        $this->assertFalse($task->isOverdue());
    }

    public function testIsOverdueReturnsFalseWhenCompleted(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $task->setDueDate(new \DateTimeImmutable('yesterday'));
        $task->setStatus(Task::STATUS_COMPLETED);

        $this->assertFalse($task->isOverdue());
    }

    public function testIsOverdueReturnsTrueWhenPastDue(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $task->setDueDate(new \DateTimeImmutable('yesterday'));
        $task->setStatus(Task::STATUS_PENDING);

        $this->assertTrue($task->isOverdue());
    }

    public function testIsOverdueReturnsFalseWhenDueDateIsFuture(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $task->setDueDate(new \DateTimeImmutable('tomorrow'));
        $task->setStatus(Task::STATUS_PENDING);

        $this->assertFalse($task->isOverdue());
    }

    public function testIsOverdueReturnsFalseWhenDueDateIsToday(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $task->setDueDate(new \DateTimeImmutable('today'));
        $task->setStatus(Task::STATUS_PENDING);

        // Due date is today, not past due
        $this->assertFalse($task->isOverdue());
    }

    // ========================================
    // Position Tests
    // ========================================

    public function testGetPositionReturnsSetValue(): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $task->setPosition(5);

        $this->assertEquals(5, $task->getPosition());
    }

    public function testPositionDefaultsToZero(): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $this->assertEquals(0, $task->getPosition());
    }

    public function testSetPositionReturnsSelf(): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $result = $task->setPosition(10);

        $this->assertSame($task, $result);
    }

    // ========================================
    // Project Association Tests
    // ========================================

    public function testGetProjectReturnsNull(): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $this->assertNull($task->getProject());
    }

    public function testSetProjectAssignsProject(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $project = new Project();
        $project->setName('Test Project');

        $task->setProject($project);

        $this->assertSame($project, $task->getProject());
    }

    public function testSetProjectCanSetNull(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $project = new Project();
        $project->setName('Test Project');
        $task->setProject($project);

        $task->setProject(null);

        $this->assertNull($task->getProject());
    }

    public function testSetProjectReturnsSelf(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $project = new Project();
        $project->setName('Test Project');

        $result = $task->setProject($project);

        $this->assertSame($task, $result);
    }

    // ========================================
    // Owner Association Tests
    // ========================================

    public function testGetOwnerReturnsNullByDefault(): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $this->assertNull($task->getOwner());
    }

    public function testSetOwnerAssignsOwner(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $user = new User();
        $user->setEmail('test@example.com');

        $task->setOwner($user);

        $this->assertSame($user, $task->getOwner());
    }

    public function testSetOwnerReturnsSelf(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $user = new User();
        $user->setEmail('test@example.com');

        $result = $task->setOwner($user);

        $this->assertSame($task, $result);
    }

    // ========================================
    // Tag Collection Tests
    // ========================================

    public function testGetTagsReturnsEmptyCollectionByDefault(): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $tags = $task->getTags();

        $this->assertCount(0, $tags);
    }

    public function testAddTagAddsToCollection(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $tag = new Tag();
        $tag->setName('urgent');

        $task->addTag($tag);

        $this->assertCount(1, $task->getTags());
        $this->assertTrue($task->getTags()->contains($tag));
    }

    public function testAddTagReturnsSelf(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $tag = new Tag();
        $tag->setName('urgent');

        $result = $task->addTag($tag);

        $this->assertSame($task, $result);
    }

    public function testAddTagDoesNotAddDuplicates(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $tag = new Tag();
        $tag->setName('urgent');

        $task->addTag($tag);
        $task->addTag($tag);

        $this->assertCount(1, $task->getTags());
    }

    public function testRemoveTagRemovesFromCollection(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $tag = new Tag();
        $tag->setName('urgent');
        $task->addTag($tag);

        $task->removeTag($tag);

        $this->assertCount(0, $task->getTags());
        $this->assertFalse($task->getTags()->contains($tag));
    }

    public function testRemoveTagReturnsSelf(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $tag = new Tag();
        $tag->setName('urgent');
        $task->addTag($tag);

        $result = $task->removeTag($tag);

        $this->assertSame($task, $result);
    }

    // ========================================
    // Status Validation Tests
    // ========================================

    #[DataProvider('validStatusProvider')]
    public function testSetStatusAcceptsValidValues(string $status): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $task->setStatus($status);

        $this->assertEquals($status, $task->getStatus());
    }

    public static function validStatusProvider(): array
    {
        return [
            'pending' => [Task::STATUS_PENDING],
            'in_progress' => [Task::STATUS_IN_PROGRESS],
            'completed' => [Task::STATUS_COMPLETED],
        ];
    }

    #[DataProvider('invalidStatusProvider')]
    public function testSetStatusRejectsInvalidValues(string $status): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $this->expectException(\InvalidArgumentException::class);
        $task->setStatus($status);
    }

    public static function invalidStatusProvider(): array
    {
        return [
            'invalid' => ['invalid'],
            'uppercase' => ['PENDING'],
            'with_spaces' => ['in progress'],
            'empty' => [''],
        ];
    }

    public function testStatusDefaultsToPending(): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $this->assertEquals(Task::STATUS_PENDING, $task->getStatus());
    }

    // ========================================
    // Title and Description Tests
    // ========================================

    public function testSetTitleReturnsSelf(): void
    {
        $task = new Task();

        $result = $task->setTitle('Test Task');

        $this->assertSame($task, $result);
    }

    public function testGetTitleReturnsSetValue(): void
    {
        $task = new Task();

        $task->setTitle('My Task Title');

        $this->assertEquals('My Task Title', $task->getTitle());
    }

    public function testDescriptionDefaultsToNull(): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $this->assertNull($task->getDescription());
    }

    public function testSetDescriptionReturnsSelf(): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $result = $task->setDescription('Description');

        $this->assertSame($task, $result);
    }

    public function testSetDescriptionCanSetNull(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $task->setDescription('Some description');

        $task->setDescription(null);

        $this->assertNull($task->getDescription());
    }

    // ========================================
    // Timestamp Tests
    // ========================================

    public function testCreatedAtIsSetOnConstruction(): void
    {
        $before = new \DateTimeImmutable();
        $task = new Task();
        $task->setTitle('Test');
        $after = new \DateTimeImmutable();

        $this->assertInstanceOf(\DateTimeImmutable::class, $task->getCreatedAt());
        $this->assertGreaterThanOrEqual($before, $task->getCreatedAt());
        $this->assertLessThanOrEqual($after, $task->getCreatedAt());
    }

    public function testUpdatedAtIsSetOnConstruction(): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $this->assertInstanceOf(\DateTimeImmutable::class, $task->getUpdatedAt());
    }

    public function testSetCreatedAtReturnsSelf(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $date = new \DateTimeImmutable('2024-01-01');

        $result = $task->setCreatedAt($date);

        $this->assertSame($task, $result);
        $this->assertEquals($date, $task->getCreatedAt());
    }

    public function testSetUpdatedAtReturnsSelf(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $date = new \DateTimeImmutable('2024-01-01');

        $result = $task->setUpdatedAt($date);

        $this->assertSame($task, $result);
        $this->assertEquals($date, $task->getUpdatedAt());
    }

    // ========================================
    // Due Date Tests
    // ========================================

    public function testDueDateDefaultsToNull(): void
    {
        $task = new Task();
        $task->setTitle('Test');

        $this->assertNull($task->getDueDate());
    }

    public function testSetDueDateReturnsSelf(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $date = new \DateTimeImmutable('2024-12-31');

        $result = $task->setDueDate($date);

        $this->assertSame($task, $result);
    }

    public function testSetDueDateCanSetNull(): void
    {
        $task = new Task();
        $task->setTitle('Test');
        $task->setDueDate(new \DateTimeImmutable('2024-12-31'));

        $task->setDueDate(null);

        $this->assertNull($task->getDueDate());
    }
}
