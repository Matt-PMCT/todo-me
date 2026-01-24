<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper\Domain;

use App\EventListener\ExceptionMapper\ExceptionMapperInterface;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use App\Exception\EntityNotFoundException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Maps EntityNotFoundException to error response.
 */
#[AutoconfigureTag('app.exception_mapper')]
final class EntityNotFoundExceptionMapper implements ExceptionMapperInterface
{
    public static function getPriority(): int
    {
        return 100;
    }

    public function canHandle(\Throwable $exception): bool
    {
        return $exception instanceof EntityNotFoundException;
    }

    public function map(\Throwable $exception): ExceptionMapping
    {
        assert($exception instanceof EntityNotFoundException);

        return new ExceptionMapping(
            $exception->errorCode,
            $exception->getMessage(),
            $exception->getStatusCode(),
            [
                'entityType' => $exception->entityType,
                'entityId' => $exception->entityId,
            ],
        );
    }
}
