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
 * Note: This handles the Security component AccessDeniedException, which is thrown
 * when an authenticated user lacks permissions to access a resource (403 Forbidden).
 * For unauthenticated users, Symfony throws AuthenticationException instead (401).
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
            'FORBIDDEN',
            'Access denied',
            Response::HTTP_FORBIDDEN,
        );
    }
}
