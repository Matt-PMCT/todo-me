<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper\Symfony;

use App\EventListener\ExceptionMapper\ExceptionMapperInterface;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Maps Symfony HttpExceptionInterface exceptions to error responses.
 *
 * Handles all HTTP exceptions with appropriate error codes based on
 * the specific exception type or HTTP status code.
 */
#[AutoconfigureTag('app.exception_mapper')]
final class HttpExceptionMapper implements ExceptionMapperInterface
{
    public static function getPriority(): int
    {
        return 10;
    }

    public function canHandle(\Throwable $exception): bool
    {
        return $exception instanceof HttpExceptionInterface;
    }

    public function map(\Throwable $exception): ExceptionMapping
    {
        assert($exception instanceof HttpExceptionInterface);

        $statusCode = $exception->getStatusCode();
        $message = $exception->getMessage();

        if (empty($message)) {
            $message = Response::$statusTexts[$statusCode] ?? 'An error occurred';
        }

        $errorCode = $this->resolveErrorCode($exception, $statusCode);

        return new ExceptionMapping($errorCode, $message, $statusCode);
    }

    private function resolveErrorCode(HttpExceptionInterface $exception, int $statusCode): string
    {
        return match (true) {
            $exception instanceof NotFoundHttpException => 'NOT_FOUND',
            $exception instanceof UnauthorizedHttpException => 'UNAUTHORIZED',
            $exception instanceof AccessDeniedHttpException => 'FORBIDDEN',
            $exception instanceof TooManyRequestsHttpException => 'RATE_LIMIT_EXCEEDED',
            $exception instanceof BadRequestHttpException => 'BAD_REQUEST',
            $exception instanceof UnprocessableEntityHttpException => 'VALIDATION_ERROR',
            $exception instanceof ConflictHttpException => 'CONFLICT',
            default => $this->getErrorCodeFromStatusCode($statusCode),
        };
    }

    private function getErrorCodeFromStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMIT_EXCEEDED',
            500 => 'SERVER_ERROR',
            501 => 'NOT_IMPLEMENTED',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'ERROR',
        };
    }
}
