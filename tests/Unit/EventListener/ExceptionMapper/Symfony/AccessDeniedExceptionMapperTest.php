<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\ExceptionMapper\Symfony;

use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\EventListener\ExceptionMapper\Symfony\AccessDeniedExceptionMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

class AccessDeniedExceptionMapperTest extends TestCase
{
    private Security&MockObject $security;
    private AccessDeniedExceptionMapper $mapper;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->mapper = new AccessDeniedExceptionMapper($this->security);
    }

    public function testCanHandleReturnsTrueForAccessDeniedException(): void
    {
        $exception = new AccessDeniedException();
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsTrueForAccessDeniedExceptionWithMessage(): void
    {
        $exception = new AccessDeniedException('Access denied for this resource');
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsFalseForOtherExceptions(): void
    {
        $this->assertFalse($this->mapper->canHandle(new \Exception()));
        $this->assertFalse($this->mapper->canHandle(new \RuntimeException()));
        $this->assertFalse($this->mapper->canHandle(new \InvalidArgumentException()));
    }

    public function testCanHandleReturnsFalseForDomainForbiddenException(): void
    {
        // This mapper handles Symfony's AccessDeniedException, not our domain ForbiddenException
        $exception = new \App\Exception\ForbiddenException('Test');
        $this->assertFalse($this->mapper->canHandle($exception));
    }

    public function testMapReturns401ForUnauthenticatedUser(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $exception = new AccessDeniedException();

        $mapping = $this->mapper->map($exception);

        $this->assertInstanceOf(ExceptionMapping::class, $mapping);
        $this->assertSame('UNAUTHORIZED', $mapping->errorCode);
        $this->assertSame('Authentication required', $mapping->message);
        $this->assertSame(401, $mapping->statusCode);
    }

    public function testMapReturns403ForAuthenticatedUser(): void
    {
        $user = $this->createMock(UserInterface::class);
        $this->security->method('getUser')->willReturn($user);
        $exception = new AccessDeniedException();

        $mapping = $this->mapper->map($exception);

        $this->assertInstanceOf(ExceptionMapping::class, $mapping);
        $this->assertSame('FORBIDDEN', $mapping->errorCode);
        $this->assertSame('Access denied', $mapping->message);
        $this->assertSame(403, $mapping->statusCode);
    }

    public function testMapReturnsGenericMessageForUnauthenticatedUser(): void
    {
        $this->security->method('getUser')->willReturn(null);
        // The mapper returns a generic message, not the exception's message
        $exception = new AccessDeniedException('You cannot do this');

        $mapping = $this->mapper->map($exception);

        $this->assertSame('Authentication required', $mapping->message);
        $this->assertSame(401, $mapping->statusCode);
    }

    public function testMappingHasNoDetails(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $exception = new AccessDeniedException();

        $mapping = $this->mapper->map($exception);

        $this->assertEmpty($mapping->details);
    }

    public function testPriorityIs75(): void
    {
        // Symfony exception mappers have lower priority (75) than domain mappers (100)
        $this->assertSame(75, AccessDeniedExceptionMapper::getPriority());
    }
}
