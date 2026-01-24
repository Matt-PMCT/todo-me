<?php

declare(strict_types=1);

namespace App\Tests\Unit\Exception;

use App\Exception\ProjectHierarchyTooDeepException;
use PHPUnit\Framework\TestCase;

class ProjectHierarchyTooDeepExceptionTest extends TestCase
{
    public function testExceptionMessage(): void
    {
        $exception = new ProjectHierarchyTooDeepException('project-123', 50);

        $this->assertStringContainsString('project-123', $exception->getMessage());
        $this->assertStringContainsString('50', $exception->getMessage());
        $this->assertStringContainsString('maximum hierarchy depth', $exception->getMessage());
    }

    public function testStatusCode(): void
    {
        $exception = new ProjectHierarchyTooDeepException('project-123', 50);

        $this->assertSame(422, $exception->getStatusCode());
    }

    public function testErrorCode(): void
    {
        $exception = new ProjectHierarchyTooDeepException('project-123', 50);

        $this->assertSame('PROJECT_HIERARCHY_TOO_DEEP', $exception->errorCode);
    }

    public function testProjectId(): void
    {
        $exception = new ProjectHierarchyTooDeepException('project-123', 50);

        $this->assertSame('project-123', $exception->projectId);
    }

    public function testMaxDepth(): void
    {
        $exception = new ProjectHierarchyTooDeepException('project-123', 50);

        $this->assertSame(50, $exception->maxDepth);
    }

    public function testCreateFactory(): void
    {
        $exception = ProjectHierarchyTooDeepException::create('project-456', 25);

        $this->assertInstanceOf(ProjectHierarchyTooDeepException::class, $exception);
        $this->assertSame('project-456', $exception->projectId);
        $this->assertSame(25, $exception->maxDepth);
        $this->assertSame(422, $exception->getStatusCode());
        $this->assertSame('PROJECT_HIERARCHY_TOO_DEEP', $exception->errorCode);
    }

    public function testPreviousException(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new ProjectHierarchyTooDeepException('project-123', 50, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
