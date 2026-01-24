<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\ExceptionMapper;

use App\EventListener\ExceptionMapper\ExceptionMapperInterface;
use App\EventListener\ExceptionMapper\ExceptionMapperRegistry;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use PHPUnit\Framework\TestCase;

class ExceptionMapperRegistryTest extends TestCase
{
    public function testMapReturnsFirstMatchingMapperResult(): void
    {
        $exception = new \RuntimeException('Test error');

        $mapper1 = $this->createMockMapper(false, null);
        $mapper2 = $this->createMockMapper(true, new ExceptionMapping('ERROR_2', 'Error 2', 400));
        $mapper3 = $this->createMockMapper(true, new ExceptionMapping('ERROR_3', 'Error 3', 500));

        $registry = new ExceptionMapperRegistry([$mapper1, $mapper2, $mapper3]);
        $result = $registry->map($exception);

        $this->assertSame('ERROR_2', $result->errorCode);
        $this->assertSame('Error 2', $result->message);
        $this->assertSame(400, $result->statusCode);
    }

    public function testMapReturnsFallbackWhenNoMapperHandles(): void
    {
        $exception = new \RuntimeException('Test error');

        $mapper = $this->createMockMapper(false, null);

        $registry = new ExceptionMapperRegistry([$mapper]);
        $result = $registry->map($exception);

        $this->assertSame('SERVER_ERROR', $result->errorCode);
        $this->assertSame(500, $result->statusCode);
    }

    public function testMappersAreCalledInOrder(): void
    {
        $exception = new \RuntimeException('Test error');
        $callOrder = [];

        $mapper1 = $this->createMock(ExceptionMapperInterface::class);
        $mapper1->method('canHandle')->willReturnCallback(function () use (&$callOrder) {
            $callOrder[] = 1;
            return false;
        });

        $mapper2 = $this->createMock(ExceptionMapperInterface::class);
        $mapper2->method('canHandle')->willReturnCallback(function () use (&$callOrder) {
            $callOrder[] = 2;
            return true;
        });
        $mapper2->method('map')->willReturn(new ExceptionMapping('ERROR', 'Error', 500));

        $registry = new ExceptionMapperRegistry([$mapper1, $mapper2]);
        $registry->map($exception);

        $this->assertSame([1, 2], $callOrder);
    }

    private function createMockMapper(bool $canHandle, ?ExceptionMapping $mapping): ExceptionMapperInterface
    {
        $mapper = $this->createMock(ExceptionMapperInterface::class);
        $mapper->method('canHandle')->willReturn($canHandle);

        if ($mapping !== null) {
            $mapper->method('map')->willReturn($mapping);
        }

        return $mapper;
    }
}
