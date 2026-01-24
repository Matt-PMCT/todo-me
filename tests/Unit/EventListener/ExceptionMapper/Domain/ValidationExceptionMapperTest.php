<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\ExceptionMapper\Domain;

use App\EventListener\ExceptionMapper\Domain\ValidationExceptionMapper;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

class ValidationExceptionMapperTest extends TestCase
{
    private ValidationExceptionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ValidationExceptionMapper();
    }

    public function testCanHandleReturnsTrueForValidationException(): void
    {
        $exception = new ValidationException(['field' => 'required']);
        $this->assertTrue($this->mapper->canHandle($exception));
    }

    public function testCanHandleReturnsFalseForOtherExceptions(): void
    {
        $this->assertFalse($this->mapper->canHandle(new \Exception()));
        $this->assertFalse($this->mapper->canHandle(new \RuntimeException()));
    }

    public function testMapReturnsCorrectMapping(): void
    {
        $errors = ['email' => 'Invalid email', 'password' => 'Too short'];
        $exception = new ValidationException($errors);

        $mapping = $this->mapper->map($exception);

        $this->assertInstanceOf(ExceptionMapping::class, $mapping);
        $this->assertSame('VALIDATION_ERROR', $mapping->errorCode);
        $this->assertSame('Validation failed', $mapping->message);
        $this->assertSame(422, $mapping->statusCode);
        $this->assertSame(['errors' => $errors], $mapping->details);
    }

    public function testPriorityIs100(): void
    {
        $this->assertSame(100, ValidationExceptionMapper::getPriority());
    }
}
