<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper\Domain;

use App\EventListener\ExceptionMapper\ExceptionMapperInterface;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\Exception\InvalidStatusException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Maps InvalidStatusException to error response.
 */
#[AutoconfigureTag('app.exception_mapper')]
final class InvalidStatusExceptionMapper implements ExceptionMapperInterface
{
    public static function getPriority(): int
    {
        return 100;
    }

    public function canHandle(\Throwable $exception): bool
    {
        return $exception instanceof InvalidStatusException;
    }

    public function map(\Throwable $exception): ExceptionMapping
    {
        assert($exception instanceof InvalidStatusException);

        return new ExceptionMapping(
            $exception->errorCode,
            $exception->getMessage(),
            $exception->getStatusCode(),
            [
                'invalidStatus' => $exception->invalidStatus,
                'validStatuses' => $exception->validStatuses,
            ],
        );
    }
}
