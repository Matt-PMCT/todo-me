<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Task;
use App\Service\OverdueService;
use App\Tests\Unit\UnitTestCase;

class OverdueServiceTest extends UnitTestCase
{
    private OverdueService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OverdueService();
    }

    // ========================================
    // getSeverityColorClass Tests
    // ========================================

    public function testGetSeverityColorClassReturnsLowColors(): void
    {
        $result = $this->service->getSeverityColorClass(OverdueService::SEVERITY_LOW);

        $this->assertEquals('text-yellow-600 bg-yellow-50 border-yellow-200', $result);
    }

    public function testGetSeverityColorClassReturnsMediumColors(): void
    {
        $result = $this->service->getSeverityColorClass(OverdueService::SEVERITY_MEDIUM);

        $this->assertEquals('text-orange-600 bg-orange-50 border-orange-200', $result);
    }

    public function testGetSeverityColorClassReturnsHighColors(): void
    {
        $result = $this->service->getSeverityColorClass(OverdueService::SEVERITY_HIGH);

        $this->assertEquals('text-red-600 bg-red-50 border-red-200', $result);
    }

    public function testGetSeverityColorClassReturnsEmptyStringForUnknownSeverity(): void
    {
        $result = $this->service->getSeverityColorClass('unknown');

        $this->assertEquals('', $result);
    }

    // ========================================
    // getSeverityBorderClass Tests
    // ========================================

    public function testGetSeverityBorderClassReturnsLowBorder(): void
    {
        $result = $this->service->getSeverityBorderClass(OverdueService::SEVERITY_LOW);

        $this->assertEquals('border-l-yellow-500', $result);
    }

    public function testGetSeverityBorderClassReturnsMediumBorder(): void
    {
        $result = $this->service->getSeverityBorderClass(OverdueService::SEVERITY_MEDIUM);

        $this->assertEquals('border-l-orange-500', $result);
    }

    public function testGetSeverityBorderClassReturnsHighBorder(): void
    {
        $result = $this->service->getSeverityBorderClass(OverdueService::SEVERITY_HIGH);

        $this->assertEquals('border-l-red-500', $result);
    }

    public function testGetSeverityBorderClassReturnsEmptyStringForUnknownSeverity(): void
    {
        $result = $this->service->getSeverityBorderClass('unknown');

        $this->assertEquals('', $result);
    }

    // ========================================
    // getSeverityLabel Tests
    // ========================================

    public function testGetSeverityLabelReturnsLowLabel(): void
    {
        $result = $this->service->getSeverityLabel(OverdueService::SEVERITY_LOW);

        $this->assertEquals('1-2 days overdue', $result);
    }

    public function testGetSeverityLabelReturnsMediumLabel(): void
    {
        $result = $this->service->getSeverityLabel(OverdueService::SEVERITY_MEDIUM);

        $this->assertEquals('3-7 days overdue', $result);
    }

    public function testGetSeverityLabelReturnsHighLabel(): void
    {
        $result = $this->service->getSeverityLabel(OverdueService::SEVERITY_HIGH);

        $this->assertEquals('More than a week overdue', $result);
    }

    public function testGetSeverityLabelReturnsEmptyStringForUnknownSeverity(): void
    {
        $result = $this->service->getSeverityLabel('unknown');

        $this->assertEquals('', $result);
    }

    // ========================================
    // getOverdueDisplayInfo Tests
    // ========================================

    public function testGetOverdueDisplayInfoReturnsCompleteInfoForOverdueTask(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-1', $user);
        $dueDate = (new \DateTimeImmutable('today'))->modify('-5 days');
        $task->setDueDate($dueDate);

        $result = $this->service->getOverdueDisplayInfo($task);

        $this->assertTrue($result['isOverdue']);
        $this->assertEquals(5, $result['days']);
        $this->assertEquals(Task::OVERDUE_SEVERITY_MEDIUM, $result['severity']);
        $this->assertEquals('text-orange-600 bg-orange-50 border-orange-200', $result['colorClass']);
        $this->assertEquals('border-l-orange-500', $result['borderClass']);
        $this->assertEquals('3-7 days overdue', $result['label']);
    }

    public function testGetOverdueDisplayInfoReturnsCorrectInfoForNonOverdueTask(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-1', $user);
        $task->setDueDate(new \DateTimeImmutable('+5 days'));

        $result = $this->service->getOverdueDisplayInfo($task);

        $this->assertFalse($result['isOverdue']);
        $this->assertNull($result['days']);
        $this->assertNull($result['severity']);
        $this->assertEquals('', $result['colorClass']);
        $this->assertEquals('', $result['borderClass']);
        $this->assertEquals('', $result['label']);
    }

    public function testGetOverdueDisplayInfoReturnsCorrectInfoForTaskWithNoDueDate(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-1', $user);
        // No due date

        $result = $this->service->getOverdueDisplayInfo($task);

        $this->assertFalse($result['isOverdue']);
        $this->assertNull($result['days']);
        $this->assertNull($result['severity']);
        $this->assertEquals('', $result['colorClass']);
        $this->assertEquals('', $result['borderClass']);
        $this->assertEquals('', $result['label']);
    }

    public function testGetOverdueDisplayInfoReturnsCorrectInfoForCompletedTask(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-1', $user);
        $dueDate = (new \DateTimeImmutable('today'))->modify('-5 days');
        $task->setDueDate($dueDate);
        $task->setStatus(Task::STATUS_COMPLETED);

        $result = $this->service->getOverdueDisplayInfo($task);

        $this->assertFalse($result['isOverdue']);
        $this->assertNull($result['days']);
        $this->assertNull($result['severity']);
    }

    public function testGetOverdueDisplayInfoForLowSeverity(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-1', $user);
        $dueDate = (new \DateTimeImmutable('today'))->modify('-1 day');
        $task->setDueDate($dueDate);

        $result = $this->service->getOverdueDisplayInfo($task);

        $this->assertTrue($result['isOverdue']);
        $this->assertEquals(1, $result['days']);
        $this->assertEquals(Task::OVERDUE_SEVERITY_LOW, $result['severity']);
        $this->assertEquals('text-yellow-600 bg-yellow-50 border-yellow-200', $result['colorClass']);
        $this->assertEquals('border-l-yellow-500', $result['borderClass']);
    }

    public function testGetOverdueDisplayInfoForHighSeverity(): void
    {
        $user = $this->createUserWithId('user-123');
        $task = $this->createTaskWithId('task-1', $user);
        $dueDate = (new \DateTimeImmutable('today'))->modify('-10 days');
        $task->setDueDate($dueDate);

        $result = $this->service->getOverdueDisplayInfo($task);

        $this->assertTrue($result['isOverdue']);
        $this->assertEquals(10, $result['days']);
        $this->assertEquals(Task::OVERDUE_SEVERITY_HIGH, $result['severity']);
        $this->assertEquals('text-red-600 bg-red-50 border-red-200', $result['colorClass']);
        $this->assertEquals('border-l-red-500', $result['borderClass']);
    }

    // ========================================
    // getSeverityOptions Tests
    // ========================================

    public function testGetSeverityOptionsReturnsAllOptions(): void
    {
        $result = $this->service->getSeverityOptions();

        $this->assertCount(3, $result);
        $this->assertArrayHasKey(OverdueService::SEVERITY_LOW, $result);
        $this->assertArrayHasKey(OverdueService::SEVERITY_MEDIUM, $result);
        $this->assertArrayHasKey(OverdueService::SEVERITY_HIGH, $result);
    }

    public function testGetSeverityOptionsContainsCorrectLowOption(): void
    {
        $result = $this->service->getSeverityOptions();

        $this->assertEquals([
            'value' => 'low',
            'label' => '1-2 days overdue',
            'colorClass' => 'text-yellow-600 bg-yellow-50 border-yellow-200',
        ], $result[OverdueService::SEVERITY_LOW]);
    }

    public function testGetSeverityOptionsContainsCorrectMediumOption(): void
    {
        $result = $this->service->getSeverityOptions();

        $this->assertEquals([
            'value' => 'medium',
            'label' => '3-7 days overdue',
            'colorClass' => 'text-orange-600 bg-orange-50 border-orange-200',
        ], $result[OverdueService::SEVERITY_MEDIUM]);
    }

    public function testGetSeverityOptionsContainsCorrectHighOption(): void
    {
        $result = $this->service->getSeverityOptions();

        $this->assertEquals([
            'value' => 'high',
            'label' => 'More than a week overdue',
            'colorClass' => 'text-red-600 bg-red-50 border-red-200',
        ], $result[OverdueService::SEVERITY_HIGH]);
    }

    // ========================================
    // Constant Tests
    // ========================================

    public function testSeverityConstantsMatchTaskEntity(): void
    {
        $this->assertEquals(Task::OVERDUE_SEVERITY_LOW, OverdueService::SEVERITY_LOW);
        $this->assertEquals(Task::OVERDUE_SEVERITY_MEDIUM, OverdueService::SEVERITY_MEDIUM);
        $this->assertEquals(Task::OVERDUE_SEVERITY_HIGH, OverdueService::SEVERITY_HIGH);
    }
}
