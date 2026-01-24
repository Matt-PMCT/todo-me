<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper\Domain;

use App\EventListener\ExceptionMapper\ExceptionMapperInterface;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\Exception\InvalidRecurrenceException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Maps InvalidRecurrenceException to error response.
 */
#[AutoconfigureTag('app.exception_mapper')]
final class InvalidRecurrenceExceptionMapper implements ExceptionMapperInterface
{
    public static function getPriority(): int
    {
        return 100;
    }

    public function canHandle(\Throwable $exception): bool
    {
        return $exception instanceof InvalidRecurrenceException;
    }

    public function map(\Throwable $exception): ExceptionMapping
    {
        assert($exception instanceof InvalidRecurrenceException);

        $details = [];
        if ($exception->getPattern() !== '') {
            $details['pattern'] = $exception->getPattern();
        }

        return new ExceptionMapping(
            $exception->errorCode,
            $exception->getMessage(),
            $exception->getStatusCode(),
            $details,
        );
    }
}
