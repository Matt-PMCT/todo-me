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

    // ========================================
    // Preset Tests
    // ========================================

    public function testFromPresetReturnsDueDatePriorityFields(): void
    {
        $dto = TaskSortRequest::fromPreset('due_date_priority');

        $this->assertEquals('due_date', $dto->field);
        $this->assertEquals('ASC', $dto->direction);
        $this->assertNotNull($dto->secondarySorts);
        $this->assertEquals([['priority', 'DESC']], $dto->secondarySorts);
        $this->assertEquals('due_date_priority', $dto->getPresetKey());
    }

    public function testFromPresetReturnsPriorityDueDateFields(): void
    {
        $dto = TaskSortRequest::fromPreset('priority_due_date');

        $this->assertEquals('priority', $dto->field);
        $this->assertEquals('DESC', $dto->direction);
        $this->assertNotNull($dto->secondarySorts);
        $this->assertEquals([['due_date', 'ASC']], $dto->secondarySorts);
        $this->assertEquals('priority_due_date', $dto->getPresetKey());
    }

    public function testFromPresetReturnsCreatedNewestFields(): void
    {
        $dto = TaskSortRequest::fromPreset('created_newest');

        $this->assertEquals('created_at', $dto->field);
        $this->assertEquals('DESC', $dto->direction);
        $this->assertNull($dto->secondarySorts);
    }

    public function testFromPresetReturnsPositionFields(): void
    {
        $dto = TaskSortRequest::fromPreset('position');

        $this->assertEquals('position', $dto->field);
        $this->assertEquals('ASC', $dto->direction);
        $this->assertNotNull($dto->secondarySorts);
        $this->assertEquals([['priority', 'DESC']], $dto->secondarySorts);
    }

    public function testFromPresetFallsBackToDefaultForInvalidKey(): void
    {
        $dto = TaskSortRequest::fromPreset('invalid_preset');

        $this->assertEquals('due_date', $dto->field);
        $this->assertEquals('ASC', $dto->direction);
        $this->assertEquals('due_date_priority', $dto->getPresetKey());
    }

    public function testGetPresetLabelReturnsCorrectLabel(): void
    {
        $dto = TaskSortRequest::fromPreset('title_az');

        $this->assertEquals('Title A-Z', $dto->getPresetLabel());
    }

    public function testGetPresetKeysReturnsAllPresets(): void
    {
        $keys = TaskSortRequest::getPresetKeys();

        $this->assertContains('due_date_priority', $keys);
        $this->assertContains('priority_due_date', $keys);
        $this->assertContains('created_newest', $keys);
        $this->assertContains('created_oldest', $keys);
        $this->assertContains('updated_recent', $keys);
        $this->assertContains('title_az', $keys);
        $this->assertContains('position', $keys);
        $this->assertCount(7, $keys);
    }

    // ========================================
    // fromRequestWithDefaults Tests
    // ========================================

    public function testFromRequestWithDefaultsUsesUrlParam(): void
    {
        $request = new Request(['sort' => 'priority_due_date']);
        $dto = TaskSortRequest::fromRequestWithDefaults($request, 'due_date_priority');

        $this->assertEquals('priority', $dto->field);
        $this->assertEquals('DESC', $dto->direction);
        $this->assertEquals('priority_due_date', $dto->getPresetKey());
    }

    public function testFromRequestWithDefaultsFallsBackToUserDefault(): void
    {
        $request = new Request([]);
        $dto = TaskSortRequest::fromRequestWithDefaults($request, 'title_az');

        $this->assertEquals('title', $dto->field);
        $this->assertEquals('ASC', $dto->direction);
        $this->assertEquals('title_az', $dto->getPresetKey());
    }

    public function testFromRequestWithDefaultsFallsBackToSystemDefault(): void
    {
        $request = new Request([]);
        $dto = TaskSortRequest::fromRequestWithDefaults($request);

        $this->assertEquals('due_date', $dto->field);
        $this->assertEquals('ASC', $dto->direction);
        $this->assertEquals('due_date_priority', $dto->getPresetKey());
    }

    public function testFromRequestWithDefaultsIgnoresInvalidUrlParam(): void
    {
        $request = new Request(['sort' => 'invalid_preset']);
        $dto = TaskSortRequest::fromRequestWithDefaults($request, 'created_newest');

        // Falls back to user default when URL param is invalid
        $this->assertEquals('created_at', $dto->field);
        $this->assertEquals('DESC', $dto->direction);
        $this->assertEquals('created_newest', $dto->getPresetKey());
    }

    public function testFromRequestWithDefaultsFallsBackForInvalidUserDefault(): void
    {
        $request = new Request([]);
        $dto = TaskSortRequest::fromRequestWithDefaults($request, 'nonexistent');

        // Falls back to system default
        $this->assertEquals('due_date', $dto->field);
        $this->assertEquals('ASC', $dto->direction);
        $this->assertEquals('due_date_priority', $dto->getPresetKey());
    }

    // ========================================
    // Static Helper Tests
    // ========================================

    public function testFieldToDqlReturnsCorrectMapping(): void
    {
        $this->assertEquals('t.dueDate', TaskSortRequest::fieldToDql('due_date'));
        $this->assertEquals('t.priority', TaskSortRequest::fieldToDql('priority'));
        $this->assertEquals('t.position', TaskSortRequest::fieldToDql('position'));
    }

    public function testIsFieldNullsLastReturnsTrueForDueDate(): void
    {
        $this->assertTrue(TaskSortRequest::isFieldNullsLast('due_date'));
    }

    public function testIsFieldNullsLastReturnsTrueForCompletedAt(): void
    {
        $this->assertTrue(TaskSortRequest::isFieldNullsLast('completed_at'));
    }

    public function testIsFieldNullsLastReturnsFalseForPriority(): void
    {
        $this->assertFalse(TaskSortRequest::isFieldNullsLast('priority'));
    }
}
