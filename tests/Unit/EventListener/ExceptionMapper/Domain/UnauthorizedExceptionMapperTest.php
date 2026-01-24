<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\ExceptionMapper\Domain;

use App\EventListener\ExceptionMapper\Domain\UnauthorizedExceptionMapper;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\Exception\UnauthorizedException;
use PHPUnit\Framework\TestCase;

class UnauthorizedExceptionMapperTest extends TestCase
{
    private UnauthorizedExceptionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new UnauthorizedExceptionMapper();
    }

    public function testCanHandleReturnsTrueForUnauthorizedException(): void
    {
        $exception = new UnauthorizedException();
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsTrueForMissingCredentials(): void
    {
        $exception = UnauthorizedException::missingCredentials();
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsTrueForInvalidCredentials(): void
    {
        $exception = UnauthorizedException::invalidCredentials();
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsTrueForExpiredCredentials(): void
    {
        $exception = UnauthorizedException::expiredCredentials();
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
        $exception = new UnauthorizedException();

        $mapping = $this->mapper->map($exception);

        $this->assertInstanceOf(ExceptionMapping::class, $mapping);
        $this->assertSame('UNAUTHORIZED', $mapping->errorCode);
        $this->assertSame('Authentication required', $mapping->message);
        $this->assertSame(401, $mapping->statusCode);
    }

    public function testMapWithCustomMessage(): void
    {
        $exception = new UnauthorizedException('Custom auth error');

        $mapping = $this->mapper->map($exception);

        $this->assertSame('UNAUTHORIZED', $mapping->errorCode);
        $this->assertSame('Custom auth error', $mapping->message);
        $this->assertSame(401, $mapping->statusCode);
    }

    public function testMapWithMissingCredentials(): void
    {
        $exception = UnauthorizedException::missingCredentials();

        $mapping = $this->mapper->map($exception);

        $this->assertSame('UNAUTHORIZED', $mapping->errorCode);
        $this->assertSame('Authentication credentials were not provided', $mapping->message);
        $this->assertSame(401, $mapping->statusCode);
    }

    public function testMapWithInvalidCredentials(): void
    {
        $exception = UnauthorizedException::invalidCredentials();

        $mapping = $this->mapper->map($exception);

        $this->assertSame('UNAUTHORIZED', $mapping->errorCode);
        $this->assertSame('Invalid authentication credentials', $mapping->message);
        $this->assertSame(401, $mapping->statusCode);
    }

    public function testMapWithExpiredCredentials(): void
    {
        $exception = UnauthorizedException::expiredCredentials();

        $mapping = $this->mapper->map($exception);

        $this->assertSame('UNAUTHORIZED', $mapping->errorCode);
        $this->assertSame('Authentication credentials have expired', $mapping->message);
        $this->assertSame(401, $mapping->statusCode);
    }

    public function testMappingHasNoDetails(): void
    {
        $exception = new UnauthorizedException();

        $mapping = $this->mapper->map($exception);

        $this->assertEmpty($mapping->details);
    }

    public function testPriorityIs100(): void
    {
        $this->assertSame(100, UnauthorizedExceptionMapper::getPriority());
    }
}
