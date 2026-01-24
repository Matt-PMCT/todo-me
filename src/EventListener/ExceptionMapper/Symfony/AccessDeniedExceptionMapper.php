<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper\Symfony;

use App\EventListener\ExceptionMapper\ExceptionMapperInterface;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Maps Symfony Security AccessDeniedException to error response.
 *
 * Note: This handles the Security component exception, which typically
 * means the user is not authenticated (401), not that they lack permissions (403).
 */
#[AutoconfigureTag('app.exception_mapper')]
final class AccessDeniedExceptionMapper implements ExceptionMapperInterface
{
    public static function getPriority(): int
    {
        return 75;
    }

    public function canHandle(\Throwable $exception): bool
    {
        return $exception instanceof AccessDeniedException;
    }

    public function map(\Throwable $exception): ExceptionMapping
    {
        return new ExceptionMapping(
            'UNAUTHORIZED',
            'Authentication required',
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
