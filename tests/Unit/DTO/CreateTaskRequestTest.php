<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\CreateTaskRequest;
use App\Entity\Task;

class CreateTaskRequestTest extends DtoTestCase
{
    public function testValidMinimalRequest(): void
    {
        $dto = new CreateTaskRequest(title: 'My Task');

        $violations = $this->validate($dto);

        $this->assertCount(0, $violations, $this->formatViolations($violations));
    }

    public function testValidFullRequest(): void
    {
        $dto = new CreateTaskRequest(
            title: 'My Task',
            description: 'Task description',
            status: Task::STATUS_IN_PROGRESS,
            priority: 3,
            dueDate: '2024-12-31',
            projectId: $this->generateUuid(),
            tagIds: [$this->generateUuid(), $this->generateUuid()],
        );

        $violations = $this->validate($dto);

        $this->assertCount(0, $violations, $this->formatViolations($violations));
    }

    public function testEmptyTitleViolation(): void
    {
        $dto = new CreateTaskRequest(title: '');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'title');
    }

    public function testBlankTitleViolation(): void
    {
        $dto = new CreateTaskRequest(title: '   ');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'title');
    }

    public function testTitleTooLongViolation(): void
    {
        $dto = new CreateTaskRequest(title: $this->generateString(501));

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'title');
    }

    public function testTitleAtMaxLength(): void
    {
        $dto = new CreateTaskRequest(title: $this->generateString(500));

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'title');
    }

    public function testDescriptionTooLongViolation(): void
    {
        $dto = new CreateTaskRequest(
            title: 'Valid Title',
            description: $this->generateString(2001),
        );

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'description');
    }

    public function testDescriptionAtMaxLength(): void
    {
        $dto = new CreateTaskRequest(
            title: 'Valid Title',
            description: $this->generateString(2000),
        );

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'description');
    }

    public function testNullDescriptionIsValid(): void
    {
        $dto = new CreateTaskRequest(title: 'Valid Title', description: null);

        $violations = $this->validate($dto);

        $this->assertCount(0, $violations);
    }

    public function testInvalidStatusViolation(): void
    {
        $dto = new CreateTaskRequest(
            title: 'Valid Title',
            status: 'invalid_status',
        );

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'status');
    }

    /**
     * @dataProvider validStatusesProvider
     */
    public function testValidStatuses(string $status): void
    {
        $dto = new CreateTaskRequest(title: 'Valid Title', status: $status);

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
        $dto = new CreateTaskRequest(
            title: 'Valid Title',
            priority: -1,
        );

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'priority');
    }

    public function testPriorityAboveMaxViolation(): void
    {
        $dto = new CreateTaskRequest(
            title: 'Valid Title',
            priority: 6,
        );

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'priority');
    }

    /**
     * @dataProvider validPrioritiesProvider
     */
    public function testValidPriorities(int $priority): void
    {
        $dto = new CreateTaskRequest(title: 'Valid Title', priority: $priority);

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
        $dto = new CreateTaskRequest(
            title: 'Valid Title',
            projectId: 'not-a-uuid',
        );

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'projectId');
    }

    public function testValidProjectId(): void
    {
        $dto = new CreateTaskRequest(
            title: 'Valid Title',
            projectId: $this->generateUuid(),
        );

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'projectId');
    }

    public function testInvalidTagIdViolation(): void
    {
        $dto = new CreateTaskRequest(
            title: 'Valid Title',
            tagIds: ['not-a-uuid', $this->generateUuid()],
        );

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'tagIds[0]');
    }

    public function testMultipleInvalidTagIdsViolation(): void
    {
        $dto = new CreateTaskRequest(
            title: 'Valid Title',
            tagIds: ['invalid1', 'invalid2'],
        );

        $violations = $this->validate($dto);

        $this->assertGreaterThanOrEqual(2, count($violations));
    }

    public function testValidTagIds(): void
    {
        $dto = new CreateTaskRequest(
            title: 'Valid Title',
            tagIds: [$this->generateUuid(), $this->generateUuid()],
        );

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'tagIds');
    }

    public function testEmptyTagIdsArrayIsValid(): void
    {
        $dto = new CreateTaskRequest(title: 'Valid Title', tagIds: []);

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'tagIds');
    }

    public function testFromArrayWithValidData(): void
    {
        $data = [
            'title' => 'Test Task',
            'description' => 'Test description',
            'status' => Task::STATUS_IN_PROGRESS,
            'priority' => 3,
            'dueDate' => '2024-12-31',
            'projectId' => 'abc123',
            'tagIds' => ['tag1', 'tag2'],
        ];

        $dto = CreateTaskRequest::fromArray($data);

        $this->assertSame('Test Task', $dto->title);
        $this->assertSame('Test description', $dto->description);
        $this->assertSame(Task::STATUS_IN_PROGRESS, $dto->status);
        $this->assertSame(3, $dto->priority);
        $this->assertSame('2024-12-31', $dto->dueDate);
        $this->assertSame('abc123', $dto->projectId);
        $this->assertSame(['tag1', 'tag2'], $dto->tagIds);
    }

    public function testFromArrayWithMinimalData(): void
    {
        $data = ['title' => 'Test Task'];

        $dto = CreateTaskRequest::fromArray($data);

        $this->assertSame('Test Task', $dto->title);
        $this->assertNull($dto->description);
        $this->assertSame(Task::STATUS_PENDING, $dto->status);
        $this->assertSame(Task::PRIORITY_DEFAULT, $dto->priority);
        $this->assertNull($dto->dueDate);
        $this->assertNull($dto->projectId);
        $this->assertNull($dto->tagIds);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $dto = CreateTaskRequest::fromArray([]);

        $this->assertSame('', $dto->title);
        $this->assertNull($dto->description);
        $this->assertSame(Task::STATUS_PENDING, $dto->status);
        $this->assertSame(Task::PRIORITY_DEFAULT, $dto->priority);
    }

    public function testFromArrayCastsIntToString(): void
    {
        $dto = CreateTaskRequest::fromArray(['title' => 123]);

        $this->assertSame('123', $dto->title);
    }

    public function testFromArrayCastsStringToInt(): void
    {
        $dto = CreateTaskRequest::fromArray([
            'title' => 'Test',
            'priority' => '3',
        ]);

        $this->assertSame(3, $dto->priority);
    }

    public function testValidDueTime(): void
    {
        $dto = new CreateTaskRequest(title: 'Valid Title', dueTime: '14:30');

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'dueTime');
    }

    public function testValidDueTimeWithLeadingZero(): void
    {
        $dto = new CreateTaskRequest(title: 'Valid Title', dueTime: '09:05');

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'dueTime');
    }

    public function testValidDueTimeMidnight(): void
    {
        $dto = new CreateTaskRequest(title: 'Valid Title', dueTime: '00:00');

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'dueTime');
    }

    public function testValidDueTimeEndOfDay(): void
    {
        $dto = new CreateTaskRequest(title: 'Valid Title', dueTime: '23:59');

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'dueTime');
    }

    public function testInvalidDueTimeFormat(): void
    {
        $dto = new CreateTaskRequest(title: 'Valid Title', dueTime: '2:30 PM');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'dueTime');
    }

    public function testInvalidDueTimeWithSeconds(): void
    {
        $dto = new CreateTaskRequest(title: 'Valid Title', dueTime: '14:30:00');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'dueTime');
    }

    public function testInvalidDueTimeOutOfRange(): void
    {
        $dto = new CreateTaskRequest(title: 'Valid Title', dueTime: '25:00');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'dueTime');
    }

    public function testInvalidDueTimeInvalidMinutes(): void
    {
        $dto = new CreateTaskRequest(title: 'Valid Title', dueTime: '14:60');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'dueTime');
    }

    public function testNullDueTimeIsValid(): void
    {
        $dto = new CreateTaskRequest(title: 'Valid Title', dueTime: null);

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'dueTime');
    }

    public function testFromArrayWithDueTime(): void
    {
        $data = [
            'title' => 'Test Task',
            'dueTime' => '14:30',
        ];

        $dto = CreateTaskRequest::fromArray($data);

        $this->assertSame('14:30', $dto->dueTime);
    }

    public function testFromArrayWithoutDueTime(): void
    {
        $data = ['title' => 'Test Task'];

        $dto = CreateTaskRequest::fromArray($data);

        $this->assertNull($dto->dueTime);
    }
}
