<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper\Domain;

use App\EventListener\ExceptionMapper\ExceptionMapperInterface;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\Exception\UnauthorizedException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Maps UnauthorizedException to error response.
 */
#[AutoconfigureTag('app.exception_mapper')]
final class UnauthorizedExceptionMapper implements ExceptionMapperInterface
{
    public static function getPriority(): int
    {
        return 100;
    }

    public function canHandle(\Throwable $exception): bool
    {
        return $exception instanceof UnauthorizedException;
    }

    public function map(\Throwable $exception): ExceptionMapping
    {
        assert($exception instanceof UnauthorizedException);

        return new ExceptionMapping(
            $exception->errorCode,
            $exception->getMessage(),
            $exception->getStatusCode(),
        );
    }
}
