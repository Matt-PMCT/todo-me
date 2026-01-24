<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\ExceptionMapper\Symfony;

use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\EventListener\ExceptionMapper\Symfony\AuthenticationExceptionMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

class AuthenticationExceptionMapperTest extends TestCase
{
    private AuthenticationExceptionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new AuthenticationExceptionMapper();
    }

    public function testCanHandleReturnsTrueForAuthenticationException(): void
    {
        $exception = new AuthenticationException();
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsTrueForBadCredentialsException(): void
    {
        // BadCredentialsException extends AuthenticationException
        $exception = new BadCredentialsException();
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsTrueForCustomUserMessageAuthenticationException(): void
    {
        // CustomUserMessageAuthenticationException extends AuthenticationException
        $exception = new CustomUserMessageAuthenticationException('Custom message');
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsFalseForOtherExceptions(): void
    {
        $this->assertFalse($this->mapper->canHandle(new \Exception()));
        $this->assertFalse($this->mapper->canHandle(new \RuntimeException()));
        $this->assertFalse($this->mapper->canHandle(new \InvalidArgumentException()));
    }

    public function testCanHandleReturnsFalseForDomainUnauthorizedException(): void
    {
        // This mapper handles Symfony's AuthenticationException, not our domain UnauthorizedException
        $exception = new \App\Exception\UnauthorizedException();
        $this->assertFalse($this->mapper->canHandle($exception));
    }

    public function testMapReturnsCorrectMapping(): void
    {
        $exception = new AuthenticationException();

        $mapping = $this->mapper->map($exception);

        $this->assertInstanceOf(ExceptionMapping::class, $mapping);
        $this->assertSame('UNAUTHORIZED', $mapping->errorCode);
        $this->assertSame('Authentication required', $mapping->message);
        $this->assertSame(401, $mapping->statusCode);
    }

    public function testMapReturnsGenericMessageRegardlessOfExceptionMessage(): void
    {
        // The mapper returns a generic message, not the exception's message
        $exception = new AuthenticationException('Token has expired');

        $mapping = $this->mapper->map($exception);

        $this->assertSame('Authentication required', $mapping->message);
        $this->assertSame(401, $mapping->statusCode);
    }

    public function testMapForBadCredentials(): void
    {
        $exception = new BadCredentialsException('Invalid password');

        $mapping = $this->mapper->map($exception);

        // Same mapping regardless of specific AuthenticationException subtype
        $this->assertSame('UNAUTHORIZED', $mapping->errorCode);
        $this->assertSame('Authentication required', $mapping->message);
        $this->assertSame(401, $mapping->statusCode);
    }

    public function testMappingHasNoDetails(): void
    {
        $exception = new AuthenticationException();

        $mapping = $this->mapper->map($exception);

        $this->assertNull($mapping->details);
    }

    public function testPriorityIs75(): void
    {
        // Symfony exception mappers have lower priority (75) than domain mappers (100)
        $this->assertSame(75, AuthenticationExceptionMapper::getPriority());
    }
}
