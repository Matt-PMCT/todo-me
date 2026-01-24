<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper\Domain;

use App\EventListener\ExceptionMapper\ExceptionMapperInterface;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\Exception\ValidationException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Maps ValidationException to error response.
 */
#[AutoconfigureTag('app.exception_mapper')]
final class ValidationExceptionMapper implements ExceptionMapperInterface
{
    public static function getPriority(): int
    {
        return 100;
    }

    public function canHandle(\Throwable $exception): bool
    {
        return $exception instanceof ValidationException;
    }

    public function map(\Throwable $exception): ExceptionMapping
    {
        assert($exception instanceof ValidationException);

        return new ExceptionMapping(
            $exception->errorCode,
            $exception->getMessage(),
            $exception->getStatusCode(),
            ['errors' => $exception->getErrors()],
        );
    }
}
