<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\ExceptionMapper;

use App\EventListener\ExceptionMapper\ExceptionMapping;
use PHPUnit\Framework\TestCase;

class ExceptionMappingTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
    {
        $mapping = new ExceptionMapping(
            'VALIDATION_ERROR',
            'Validation failed',
            422,
            ['errors' => ['field' => 'required']],
        );

        $this->assertSame('VALIDATION_ERROR', $mapping->errorCode);
        $this->assertSame('Validation failed', $mapping->message);
        $this->assertSame(422, $mapping->statusCode);
        $this->assertSame(['errors' => ['field' => 'required']], $mapping->details);
    }

    public function testDefaultDetailsIsEmptyArray(): void
    {
        $mapping = new ExceptionMapping('ERROR', 'An error', 500);

        $this->assertSame([], $mapping->details);
    }

    public function testIsImmutable(): void
    {
        $mapping = new ExceptionMapping('ERROR', 'An error', 500);

        // Verify readonly properties - these should all be readonly
        $reflection = new \ReflectionClass($mapping);
        $this->assertTrue($reflection->isReadOnly());
    }
}
