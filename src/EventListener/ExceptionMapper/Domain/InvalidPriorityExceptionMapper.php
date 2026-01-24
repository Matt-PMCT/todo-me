<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper\Domain;

use App\EventListener\ExceptionMapper\ExceptionMapperInterface;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\Exception\InvalidPriorityException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Maps InvalidPriorityException to error response.
 */
#[AutoconfigureTag('app.exception_mapper')]
final class InvalidPriorityExceptionMapper implements ExceptionMapperInterface
{
    public static function getPriority(): int
    {
        return 100;
    }

    public function canHandle(\Throwable $exception): bool
    {
        return $exception instanceof InvalidPriorityException;
    }

    public function map(\Throwable $exception): ExceptionMapping
    {
        assert($exception instanceof InvalidPriorityException);

        return new ExceptionMapping(
            $exception->errorCode,
            $exception->getMessage(),
            $exception->getStatusCode(),
            [
                'invalidPriority' => $exception->invalidPriority,
                'minPriority' => $exception->minPriority,
                'maxPriority' => $exception->maxPriority,
            ],
        );
    }
}
