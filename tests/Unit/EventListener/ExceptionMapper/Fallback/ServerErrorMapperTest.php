<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\ExceptionMapper\Fallback;

use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\EventListener\ExceptionMapper\Fallback\ServerErrorMapper;
use PHPUnit\Framework\TestCase;

class ServerErrorMapperTest extends TestCase
{
    public function testCanHandleAlwaysReturnsTrue(): void
    {
        $mapper = new ServerErrorMapper('prod');

        $this->assertTrue($mapper->canHandle(new \Exception()));
        $this->assertTrue($mapper->canHandle(new \RuntimeException()));
        $this->assertTrue($mapper->canHandle(new \InvalidArgumentException()));
    }

    public function testMapReturnsServerErrorInProduction(): void
    {
        $mapper = new ServerErrorMapper('prod');
        $exception = new \RuntimeException('Sensitive error message');

        $mapping = $mapper->map($exception);

        $this->assertInstanceOf(ExceptionMapping::class, $mapping);
        $this->assertSame('SERVER_ERROR', $mapping->errorCode);
        $this->assertSame('An internal server error occurred', $mapping->message);
        $this->assertSame(500, $mapping->statusCode);
        $this->assertSame([], $mapping->details);
    }

    public function testMapReturnsDebugInfoInDevelopment(): void
    {
        $mapper = new ServerErrorMapper('dev');
        $exception = new \RuntimeException('Detailed error message');

        $mapping = $mapper->map($exception);

        $this->assertSame('SERVER_ERROR', $mapping->errorCode);
        $this->assertSame('Detailed error message', $mapping->message);
        $this->assertSame(500, $mapping->statusCode);
        $this->assertSame(\RuntimeException::class, $mapping->details['exception']);
        $this->assertArrayHasKey('file', $mapping->details);
        $this->assertArrayHasKey('line', $mapping->details);
    }

    public function testPriorityIsZero(): void
    {
        $this->assertSame(0, ServerErrorMapper::getPriority());
    }
}
