<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener\ExceptionMapper\Symfony;

use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\EventListener\ExceptionMapper\Symfony\HttpExceptionMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class HttpExceptionMapperTest extends TestCase
{
    private HttpExceptionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new HttpExceptionMapper();
    }

    public function testCanHandleReturnsTrueForHttpExceptions(): void
    {
        $this->assertTrue($this->mapper->canHandle(new NotFoundHttpException()));
        $this->assertTrue($this->mapper->canHandle(new BadRequestHttpException()));
        $this->assertTrue($this->mapper->canHandle(new HttpException(500)));
    }

    public function testCanHandleReturnsFalseForOtherExceptions(): void
    {
        $this->assertFalse($this->mapper->canHandle(new \Exception()));
        $this->assertFalse($this->mapper->canHandle(new \RuntimeException()));
    }

    /**
     * @dataProvider httpExceptionProvider
     */
    public function testMapReturnsCorrectErrorCodes(
        \Throwable $exception,
        string $expectedErrorCode,
        int $expectedStatusCode,
    ): void {
        $mapping = $this->mapper->map($exception);

        $this->assertInstanceOf(ExceptionMapping::class, $mapping);
        $this->assertSame($expectedErrorCode, $mapping->errorCode);
        $this->assertSame($expectedStatusCode, $mapping->statusCode);
    }

    /**
     * @return array<string, array{0: \Throwable, 1: string, 2: int}>
     */
    public static function httpExceptionProvider(): array
    {
        return [
            'NotFoundHttpException' => [new NotFoundHttpException(), 'NOT_FOUND', 404],
            'UnauthorizedHttpException' => [new UnauthorizedHttpException('Bearer'), 'UNAUTHORIZED', 401],
            'AccessDeniedHttpException' => [new AccessDeniedHttpException(), 'FORBIDDEN', 403],
            'TooManyRequestsHttpException' => [new TooManyRequestsHttpException(), 'RATE_LIMIT_EXCEEDED', 429],
            'BadRequestHttpException' => [new BadRequestHttpException(), 'BAD_REQUEST', 400],
            'UnprocessableEntityHttpException' => [new UnprocessableEntityHttpException(), 'VALIDATION_ERROR', 422],
            'ConflictHttpException' => [new ConflictHttpException(), 'CONFLICT', 409],
            'GenericHttpException400' => [new HttpException(400), 'BAD_REQUEST', 400],
            'GenericHttpException503' => [new HttpException(503), 'SERVICE_UNAVAILABLE', 503],
        ];
    }

    public function testMapUsesExceptionMessageWhenProvided(): void
    {
        $exception = new NotFoundHttpException('Resource not found');
        $mapping = $this->mapper->map($exception);

        $this->assertSame('Resource not found', $mapping->message);
    }

    public function testMapUsesDefaultMessageWhenEmpty(): void
    {
        $exception = new NotFoundHttpException();
        $mapping = $this->mapper->map($exception);

        $this->assertSame('Not Found', $mapping->message);
    }

    public function testPriorityIs10(): void
    {
        $this->assertSame(10, HttpExceptionMapper::getPriority());
    }
}
