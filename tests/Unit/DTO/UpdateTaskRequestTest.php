<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\UpdateTaskRequest;
use App\Entity\Task;

class UpdateTaskRequestTest extends DtoTestCase
{
    public function testEmptyRequestIsValid(): void
    {
        $dto = new UpdateTaskRequest();

        $violations = $this->validate($dto);

        $this->assertCount(0, $violations, $this->formatViolations($violations));
    }

    public function testTitleOnlyUpdate(): void
    {
        $dto = new UpdateTaskRequest(title: 'Updated Title');

        $violations = $this->validate($dto);

        $this->assertCount(0, $violations);
        $this->assertSame('Updated Title', $dto->title);
        $this->assertNull($dto->description);
        $this->assertNull($dto->status);
    }

    public function testTitleTooLongViolation(): void
    {
        $dto = new UpdateTaskRequest(title: $this->generateString(501));

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'title');
    }

    public function testTitleAtMaxLengthIsValid(): void
    {
        $dto = new UpdateTaskRequest(title: $this->generateString(500));

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'title');
    }

    public function testDescriptionTooLongViolation(): void
    {
        $dto = new UpdateTaskRequest(description: $this->generateString(2001));

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'description');
    }

    public function testDescriptionAtMaxLengthIsValid(): void
    {
        $dto = new UpdateTaskRequest(description: $this->generateString(2000));

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'description');
    }

    public function testInvalidStatusViolation(): void
    {
        $dto = new UpdateTaskRequest(status: 'invalid_status');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'status');
    }

    /**
     * @dataProvider validStatusesProvider
     */
    public function testValidStatuses(string $status): void
    {
        $dto = new UpdateTaskRequest(status: $status);

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'status');
    }

    public static function validStatusesProvider(): array
    {
        return [
            'pending' => [Task::STATUS_PENDING],
            'in_progress' => [Task::STATUS_IN_PROGRESS],
            'completed' => [Task::STATUS_COMPLETED],
        ];
    }

    public function testPriorityBelowMinViolation(): void
    {
        $dto = new UpdateTaskRequest(priority: -1);

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'priority');
    }

    public function testPriorityAboveMaxViolation(): void
    {
        $dto = new UpdateTaskRequest(priority: 6);

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'priority');
    }

    /**
     * @dataProvider validPrioritiesProvider
     */
    public function testValidPriorities(int $priority): void
    {
        $dto = new UpdateTaskRequest(priority: $priority);

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'priority');
    }

    public static function validPrioritiesProvider(): array
    {
        return [
            'min priority' => [Task::PRIORITY_MIN],
            'default priority' => [Task::PRIORITY_DEFAULT],
            'max priority' => [Task::PRIORITY_MAX],
        ];
    }

    public function testInvalidProjectIdViolation(): void
    {
        $dto = new UpdateTaskRequest(projectId: 'not-a-uuid');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'projectId');
    }

    public function testValidProjectId(): void
    {
        $dto = new UpdateTaskRequest(projectId: $this->generateUuid());

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'projectId');
    }

    public function testInvalidTagIdViolation(): void
    {
        $dto = new UpdateTaskRequest(tagIds: ['not-a-uuid']);

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'tagIds[0]');
    }

    public function testValidTagIds(): void
    {
        $dto = new UpdateTaskRequest(tagIds: [$this->generateUuid(), $this->generateUuid()]);

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'tagIds');
    }

    public function testHasChangesReturnsFalseForEmptyRequest(): void
    {
        $dto = new UpdateTaskRequest();

        $this->assertFalse($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueForTitle(): void
    {
        $dto = new UpdateTaskRequest(title: 'New Title');

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueForDescription(): void
    {
        $dto = new UpdateTaskRequest(description: 'New Description');

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueForStatus(): void
    {
        $dto = new UpdateTaskRequest(status: Task::STATUS_COMPLETED);

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueForPriority(): void
    {
        $dto = new UpdateTaskRequest(priority: 3);

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueForDueDate(): void
    {
        $dto = new UpdateTaskRequest(dueDate: '2024-12-31');

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueForProjectId(): void
    {
        $dto = new UpdateTaskRequest(projectId: $this->generateUuid());

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueForTagIds(): void
    {
        $dto = new UpdateTaskRequest(tagIds: [$this->generateUuid()]);

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueForClearDescription(): void
    {
        $dto = new UpdateTaskRequest(clearDescription: true);

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueForClearProject(): void
    {
        $dto = new UpdateTaskRequest(clearProject: true);

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueForClearDueDate(): void
    {
        $dto = new UpdateTaskRequest(clearDueDate: true);

        $this->assertTrue($dto->hasChanges());
    }

    public function testClearFlagsDefaultToFalse(): void
    {
        $dto = new UpdateTaskRequest();

        $this->assertFalse($dto->clearDescription);
        $this->assertFalse($dto->clearProject);
        $this->assertFalse($dto->clearDueDate);
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'title' => 'Updated Title',
            'description' => 'Updated description',
            'status' => Task::STATUS_COMPLETED,
            'priority' => 4,
            'dueDate' => '2024-12-31',
            'projectId' => 'abc123',
            'tagIds' => ['tag1', 'tag2'],
            'clearDescription' => true,
            'clearProject' => false,
            'clearDueDate' => true,
        ];

        $dto = UpdateTaskRequest::fromArray($data);

        $this->assertSame('Updated Title', $dto->title);
        $this->assertSame('Updated description', $dto->description);
        $this->assertSame(Task::STATUS_COMPLETED, $dto->status);
        $this->assertSame(4, $dto->priority);
        $this->assertSame('2024-12-31', $dto->dueDate);
        $this->assertSame('abc123', $dto->projectId);
        $this->assertSame(['tag1', 'tag2'], $dto->tagIds);
        $this->assertTrue($dto->clearDescription);
        $this->assertFalse($dto->clearProject);
        $this->assertTrue($dto->clearDueDate);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $dto = UpdateTaskRequest::fromArray([]);

        $this->assertNull($dto->title);
        $this->assertNull($dto->description);
        $this->assertNull($dto->status);
        $this->assertNull($dto->priority);
        $this->assertNull($dto->dueDate);
        $this->assertNull($dto->projectId);
        $this->assertNull($dto->tagIds);
        $this->assertFalse($dto->clearDescription);
        $this->assertFalse($dto->clearProject);
        $this->assertFalse($dto->clearDueDate);
    }

    public function testFromArrayTypeCasting(): void
    {
        $dto = UpdateTaskRequest::fromArray([
            'title' => 123,
            'priority' => '3',
            'clearDescription' => 1,
        ]);

        $this->assertSame('123', $dto->title);
        $this->assertSame(3, $dto->priority);
        $this->assertTrue($dto->clearDescription);
    }

    public function testValidDueTime(): void
    {
        $dto = new UpdateTaskRequest(dueTime: '14:30');

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'dueTime');
    }

    public function testValidDueTimeWithLeadingZero(): void
    {
        $dto = new UpdateTaskRequest(dueTime: '09:05');

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'dueTime');
    }

    public function testInvalidDueTimeFormat(): void
    {
        $dto = new UpdateTaskRequest(dueTime: '2:30 PM');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'dueTime');
    }

    public function testInvalidDueTimeOutOfRange(): void
    {
        $dto = new UpdateTaskRequest(dueTime: '25:00');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'dueTime');
    }

    public function testHasChangesReturnsTrueForDueTime(): void
    {
        $dto = new UpdateTaskRequest(dueTime: '14:30');

        $this->assertTrue($dto->hasChanges());
    }

    public function testHasChangesReturnsTrueForClearDueTime(): void
    {
        $dto = new UpdateTaskRequest(clearDueTime: true);

        $this->assertTrue($dto->hasChanges());
    }

    public function testClearDueTimeDefaultsToFalse(): void
    {
        $dto = new UpdateTaskRequest();

        $this->assertFalse($dto->clearDueTime);
    }

    public function testFromArrayWithDueTime(): void
    {
        $data = [
            'dueTime' => '14:30',
            'clearDueTime' => true,
        ];

        $dto = UpdateTaskRequest::fromArray($data);

        $this->assertSame('14:30', $dto->dueTime);
        $this->assertTrue($dto->clearDueTime);
    }

    public function testFromArrayWithoutDueTime(): void
    {
        $dto = UpdateTaskRequest::fromArray([]);

        $this->assertNull($dto->dueTime);
        $this->assertFalse($dto->clearDueTime);
    }
}
