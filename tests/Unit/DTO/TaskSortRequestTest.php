<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\TaskSortRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class TaskSortRequestTest extends TestCase
{
    // ========================================
    // Sort Field Parsing Tests
    // ========================================

    public function testFromRequestParsesSortParameter(): void
    {
        $request = new Request(['sort' => 'due_date']);
        $dto = TaskSortRequest::fromRequest($request);

        $this->assertEquals('due_date', $dto->field);
    }

    public function testFromRequestParsesSortByAsAlternative(): void
    {
        $request = new Request(['sort_by' => 'priority']);
        $dto = TaskSortRequest::fromRequest($request);

        $this->assertEquals('priority', $dto->field);
    }

    public function testFromRequestSortTakesPrecedenceOverSortBy(): void
    {
        $request = new Request(['sort' => 'due_date', 'sort_by' => 'priority']);
        $dto = TaskSortRequest::fromRequest($request);

        $this->assertEquals('due_date', $dto->field);
    }

    public function testFromRequestParsesAllValidSortFields(): void
    {
        $validFields = ['due_date', 'priority', 'created_at', 'updated_at', 'completed_at', 'title', 'position'];

        foreach ($validFields as $field) {
            $request = new Request(['sort' => $field]);
            $dto = TaskSortRequest::fromRequest($request);

            $this->assertEquals($field, $dto->field, "Failed for field: $field");
        }
    }

    // ========================================
    // Direction Parsing Tests
    // ========================================

    public function testFromRequestParsesDirectionParameter(): void
    {
        $request = new Request(['direction' => 'DESC']);
        $dto = TaskSortRequest::fromRequest($request);

        $this->assertEquals('DESC', $dto->direction);
    }

    public function testFromRequestParsesOrderAsAlternative(): void
    {
        $request = new Request(['order' => 'DESC']);
        $dto = TaskSortRequest::fromRequest($request);

        $this->assertEquals('DESC', $dto->direction);
    }

    public function testFromRequestDirectionTakesPrecedenceOverOrder(): void
    {
        $request = new Request(['direction' => 'ASC', 'order' => 'DESC']);
        $dto = TaskSortRequest::fromRequest($request);

        $this->assertEquals('ASC', $dto->direction);
    }

    public function testFromRequestParsesLowercaseDirection(): void
    {
        $request = new Request(['direction' => 'desc']);
        $dto = TaskSortRequest::fromRequest($request);

        $this->assertEquals('DESC', $dto->direction);
    }

    // ========================================
    // Invalid Value Fallback Tests
    // ========================================

    public function testInvalidSortFieldFallsBackToPosition(): void
    {
        $request = new Request(['sort' => 'invalid_field']);
        $dto = TaskSortRequest::fromRequest($request);

        $this->assertEquals('position', $dto->field);
    }

    public function testInvalidDirectionFallsBackToAsc(): void
    {
        $request = new Request(['direction' => 'INVALID']);
        $dto = TaskSortRequest::fromRequest($request);

        $this->assertEquals('ASC', $dto->direction);
    }

    public function testEmptySortFieldFallsBackToPosition(): void
    {
        $request = new Request(['sort' => '']);
        $dto = TaskSortRequest::fromRequest($request);

        $this->assertEquals('position', $dto->field);
    }

    public function testEmptyDirectionFallsBackToAsc(): void
    {
        $request = new Request(['direction' => '']);
        $dto = TaskSortRequest::fromRequest($request);

        $this->assertEquals('ASC', $dto->direction);
    }

    // ========================================
    // Default Values Tests
    // ========================================

    public function testDefaultValuesWhenNoParametersProvided(): void
    {
        $request = new Request([]);
        $dto = TaskSortRequest::fromRequest($request);

        $this->assertEquals('position', $dto->field);
        $this->assertEquals('ASC', $dto->direction);
    }

    // ========================================
    // getDqlField Tests
    // ========================================

    public function testGetDqlFieldReturnsDueDateMapping(): void
    {
        $dto = new TaskSortRequest(field: 'due_date');

        $this->assertEquals('t.dueDate', $dto->getDqlField());
    }

    public function testGetDqlFieldReturnsPriorityMapping(): void
    {
        $dto = new TaskSortRequest(field: 'priority');

        $this->assertEquals('t.priority', $dto->getDqlField());
    }

    public function testGetDqlFieldReturnsCreatedAtMapping(): void
    {
        $dto = new TaskSortRequest(field: 'created_at');

        $this->assertEquals('t.createdAt', $dto->getDqlField());
    }

    public function testGetDqlFieldReturnsUpdatedAtMapping(): void
    {
        $dto = new TaskSortRequest(field: 'updated_at');

        $this->assertEquals('t.updatedAt', $dto->getDqlField());
    }

    public function testGetDqlFieldReturnsCompletedAtMapping(): void
    {
        $dto = new TaskSortRequest(field: 'completed_at');

        $this->assertEquals('t.completedAt', $dto->getDqlField());
    }

    public function testGetDqlFieldReturnsTitleMapping(): void
    {
        $dto = new TaskSortRequest(field: 'title');

        $this->assertEquals('t.title', $dto->getDqlField());
    }

    public function testGetDqlFieldReturnsPositionMapping(): void
    {
        $dto = new TaskSortRequest(field: 'position');

        $this->assertEquals('t.position', $dto->getDqlField());
    }

    public function testGetDqlFieldReturnsCorrectMappingFromRequest(): void
    {
        $request = new Request(['sort' => 'priority']);
        $dto = TaskSortRequest::fromRequest($request);

        $this->assertEquals('t.priority', $dto->getDqlField());
    }

    // ========================================
    // isNullsLastField Tests
    // ========================================

    public function testIsNullsLastFieldReturnsTrueForDueDate(): void
    {
        $dto = new TaskSortRequest(field: 'due_date');

        $this->assertTrue($dto->isNullsLastField());
    }

    public function testIsNullsLastFieldReturnsFalseForPriority(): void
    {
        $dto = new TaskSortRequest(field: 'priority');

        $this->assertFalse($dto->isNullsLastField());
    }

    public function testIsNullsLastFieldReturnsFalseForCreatedAt(): void
    {
        $dto = new TaskSortRequest(field: 'created_at');

        $this->assertFalse($dto->isNullsLastField());
    }

    public function testIsNullsLastFieldReturnsFalseForUpdatedAt(): void
    {
        $dto = new TaskSortRequest(field: 'updated_at');

        $this->assertFalse($dto->isNullsLastField());
    }

    public function testIsNullsLastFieldReturnsTrueForCompletedAt(): void
    {
        $dto = new TaskSortRequest(field: 'completed_at');

        $this->assertTrue($dto->isNullsLastField());
    }

    public function testIsNullsLastFieldReturnsFalseForTitle(): void
    {
        $dto = new TaskSortRequest(field: 'title');

        $this->assertFalse($dto->isNullsLastField());
    }

    public function testIsNullsLastFieldReturnsFalseForPosition(): void
    {
        $dto = new TaskSortRequest(field: 'position');

        $this->assertFalse($dto->isNullsLastField());
    }

    // ========================================
    // Constructor Tests
    // ========================================

    public function testConstructorWithAllParameters(): void
    {
        $dto = new TaskSortRequest(field: 'priority', direction: 'DESC');

        $this->assertEquals('priority', $dto->field);
        $this->assertEquals('DESC', $dto->direction);
    }

    public function testConstructorWithDefaultValues(): void
    {
        $dto = new TaskSortRequest();

        $this->assertEquals('position', $dto->field);
        $this->assertEquals('ASC', $dto->direction);
    }
}
