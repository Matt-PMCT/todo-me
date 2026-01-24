<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\ExceptionMapper\Domain;

use App\EventListener\ExceptionMapper\Domain\ForbiddenExceptionMapper;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\Exception\ForbiddenException;
use PHPUnit\Framework\TestCase;

class ForbiddenExceptionMapperTest extends TestCase
{
    private ForbiddenExceptionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ForbiddenExceptionMapper();
    }

    public function testCanHandleReturnsTrueForForbiddenException(): void
    {
        $exception = new ForbiddenException('Access denied');
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsTrueForNotOwner(): void
    {
        $exception = ForbiddenException::notOwner('task');
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsTrueForInsufficientPermissions(): void
    {
        $exception = ForbiddenException::insufficientPermissions('delete this resource');
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsFalseForOtherExceptions(): void
    {
        $this->assertFalse($this->mapper->canHandle(new \Exception()));
        $this->assertFalse($this->mapper->canHandle(new \RuntimeException()));
        $this->assertFalse($this->mapper->canHandle(new \InvalidArgumentException()));
    }

    public function testMapReturnsCorrectMapping(): void
    {
        $exception = new ForbiddenException('You cannot access this');

        $mapping = $this->mapper->map($exception);

        $this->assertInstanceOf(ExceptionMapping::class, $mapping);
        $this->assertSame('PERMISSION_DENIED', $mapping->errorCode);
        $this->assertSame('You cannot access this', $mapping->message);
        $this->assertSame(403, $mapping->statusCode);
        $this->assertSame(['reason' => 'You cannot access this'], $mapping->details);
    }

    public function testMapWithNotOwnerException(): void
    {
        $exception = ForbiddenException::notOwner('project');

        $mapping = $this->mapper->map($exception);

        $this->assertSame('PERMISSION_DENIED', $mapping->errorCode);
        $this->assertSame(403, $mapping->statusCode);
        $this->assertSame(['reason' => 'You do not have permission to access this project'], $mapping->details);
    }

    public function testMapWithResourceAccessDeniedException(): void
    {
        $exception = ForbiddenException::resourceAccessDenied();

        $mapping = $this->mapper->map($exception);

        $this->assertSame('PERMISSION_DENIED', $mapping->errorCode);
        $this->assertSame(403, $mapping->statusCode);
        $this->assertSame(['reason' => 'You do not have permission to access this resource'], $mapping->details);
    }

    public function testPriorityIs100(): void
    {
        $this->assertSame(100, ForbiddenExceptionMapper::getPriority());
    }
}
