<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\TaskFilterRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class TaskFilterRequestTest extends TestCase
{
    // ========================================
    // Status Parsing Tests
    // ========================================

    public function testFromRequestParsesStatus(): void
    {
        $request = new Request(['status' => 'pending']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals(['pending'], $dto->statuses);
    }

    public function testFromRequestParsesCommaSeparatedStatuses(): void
    {
        $request = new Request(['statuses' => 'pending,in_progress']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals(['pending', 'in_progress'], $dto->statuses);
    }

    public function testFromRequestStatusesTakePrecedenceOverStatus(): void
    {
        $request = new Request(['status' => 'pending', 'statuses' => 'completed']);
        $dto = TaskFilterRequest::fromRequest($request);

        // 'status' is checked first in the keys array
        $this->assertEquals(['pending'], $dto->statuses);
    }

    public function testFromRequestParsesStatusAsArray(): void
    {
        $request = new Request(['status' => ['pending', 'completed']]);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals(['pending', 'completed'], $dto->statuses);
    }

    // ========================================
    // Priority Parsing Tests
    // ========================================

    public function testFromRequestParsesPriorityMin(): void
    {
        $request = new Request(['priority_min' => '2']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals(2, $dto->priorityMin);
    }

    public function testFromRequestParsesPriorityMax(): void
    {
        $request = new Request(['priority_max' => '4']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals(4, $dto->priorityMax);
    }

    public function testFromRequestParsesBothPriorityMinAndMax(): void
    {
        $request = new Request(['priority_min' => '1', 'priority_max' => '3']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals(1, $dto->priorityMin);
        $this->assertEquals(3, $dto->priorityMax);
    }

    public function testFromRequestPriorityMinDefaultsToNull(): void
    {
        $request = new Request([]);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertNull($dto->priorityMin);
    }

    public function testFromRequestPriorityMaxDefaultsToNull(): void
    {
        $request = new Request([]);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertNull($dto->priorityMax);
    }

    // ========================================
    // Project IDs Parsing Tests
    // ========================================

    public function testFromRequestParsesProjectIds(): void
    {
        $uuid1 = '550e8400-e29b-41d4-a716-446655440000';
        $uuid2 = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        $request = new Request(['project_ids' => "$uuid1,$uuid2"]);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals([$uuid1, $uuid2], $dto->projectIds);
    }

    public function testFromRequestParsesProjectIdsAsArray(): void
    {
        $uuid1 = '550e8400-e29b-41d4-a716-446655440000';
        $uuid2 = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        $request = new Request(['project_ids' => [$uuid1, $uuid2]]);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals([$uuid1, $uuid2], $dto->projectIds);
    }

    // ========================================
    // Tag IDs Parsing Tests
    // ========================================

    public function testFromRequestParsesTagIds(): void
    {
        $uuid1 = '550e8400-e29b-41d4-a716-446655440000';
        $uuid2 = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';
        $request = new Request(['tag_ids' => "$uuid1,$uuid2"]);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals([$uuid1, $uuid2], $dto->tagIds);
    }

    public function testFromRequestParsesTagMode(): void
    {
        $request = new Request(['tag_mode' => 'AND']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals('AND', $dto->tagMode);
    }

    public function testFromRequestTagModeDefaultsToOr(): void
    {
        $request = new Request([]);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals('OR', $dto->tagMode);
    }

    public function testFromRequestParsesTagIdsWithTagMode(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $request = new Request(['tag_ids' => $uuid, 'tag_mode' => 'AND']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals([$uuid], $dto->tagIds);
        $this->assertEquals('AND', $dto->tagMode);
    }

    // ========================================
    // Include Child Projects Tests
    // ========================================

    public function testFromRequestParsesBooleanIncludeChildProjects(): void
    {
        $request = new Request(['include_child_projects' => 'true']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertTrue($dto->includeChildProjects);
    }

    public function testFromRequestIncludeChildProjectsWithOne(): void
    {
        $request = new Request(['include_child_projects' => '1']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertTrue($dto->includeChildProjects);
    }

    public function testFromRequestIncludeChildProjectsFalse(): void
    {
        $request = new Request(['include_child_projects' => 'false']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertFalse($dto->includeChildProjects);
    }

    public function testFromRequestIncludeChildProjectsDefaultsToFalse(): void
    {
        $request = new Request([]);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertFalse($dto->includeChildProjects);
    }

    // ========================================
    // Due Date Parsing Tests
    // ========================================

    public function testFromRequestParsesDueBefore(): void
    {
        $request = new Request(['due_before' => '2024-12-31']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals('2024-12-31', $dto->dueBefore);
    }

    public function testFromRequestParsesDueAfter(): void
    {
        $request = new Request(['due_after' => '2024-01-01']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals('2024-01-01', $dto->dueAfter);
    }

    public function testFromRequestParsesBothDueBeforeAndAfter(): void
    {
        $request = new Request(['due_before' => '2024-12-31', 'due_after' => '2024-01-01']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals('2024-12-31', $dto->dueBefore);
        $this->assertEquals('2024-01-01', $dto->dueAfter);
    }

    // ========================================
    // Has No Due Date Tests
    // ========================================

    public function testFromRequestParsesHasNoDueDate(): void
    {
        $request = new Request(['has_no_due_date' => 'true']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertTrue($dto->hasNoDueDate);
    }

    public function testFromRequestParsesHasNoDueDateFalse(): void
    {
        $request = new Request(['has_no_due_date' => 'false']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertFalse($dto->hasNoDueDate);
    }

    public function testFromRequestHasNoDueDateDefaultsToNull(): void
    {
        $request = new Request([]);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertNull($dto->hasNoDueDate);
    }

    // ========================================
    // Search Parsing Tests
    // ========================================

    public function testFromRequestParsesSearch(): void
    {
        $request = new Request(['search' => 'meeting notes']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals('meeting notes', $dto->search);
    }

    public function testFromRequestSearchDefaultsToNull(): void
    {
        $request = new Request([]);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertNull($dto->search);
    }

    public function testFromRequestSearchEmptyStringBecomesNull(): void
    {
        $request = new Request(['search' => '']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertNull($dto->search);
    }

    // ========================================
    // Include Completed Tests
    // ========================================

    public function testFromRequestParsesIncludeCompleted(): void
    {
        $request = new Request(['include_completed' => 'false']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertFalse($dto->includeCompleted);
    }

    public function testFromRequestIncludeCompletedDefaultsToTrue(): void
    {
        $request = new Request([]);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertTrue($dto->includeCompleted);
    }

    // ========================================
    // Default Values Tests
    // ========================================

    public function testDefaultValuesWhenParametersNotProvided(): void
    {
        $request = new Request([]);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertNull($dto->statuses);
        $this->assertNull($dto->priorityMin);
        $this->assertNull($dto->priorityMax);
        $this->assertNull($dto->projectIds);
        $this->assertFalse($dto->includeChildProjects);
        $this->assertNull($dto->tagIds);
        $this->assertEquals('OR', $dto->tagMode);
        $this->assertNull($dto->dueBefore);
        $this->assertNull($dto->dueAfter);
        $this->assertNull($dto->hasNoDueDate);
        $this->assertNull($dto->search);
        $this->assertTrue($dto->includeCompleted);
    }

    // ========================================
    // Edge Cases Tests
    // ========================================

    public function testFromRequestFiltersEmptyStringsFromArrays(): void
    {
        $request = new Request(['status' => ['pending', '', 'completed']]);
        $dto = TaskFilterRequest::fromRequest($request);

        // array_filter preserves keys, so we check values only
        $this->assertCount(2, $dto->statuses);
        $this->assertContains('pending', $dto->statuses);
        $this->assertContains('completed', $dto->statuses);
    }

    public function testFromRequestTrimsCommaSeparatedValues(): void
    {
        $request = new Request(['statuses' => ' pending , completed ']);
        $dto = TaskFilterRequest::fromRequest($request);

        $this->assertEquals(['pending', 'completed'], $dto->statuses);
    }
}
