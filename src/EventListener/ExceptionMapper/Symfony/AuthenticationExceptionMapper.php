<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper\Symfony;

use App\EventListener\ExceptionMapper\ExceptionMapperInterface;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Maps Symfony AuthenticationException to error response.
 */
#[AutoconfigureTag('app.exception_mapper')]
final class AuthenticationExceptionMapper implements ExceptionMapperInterface
{
    public static function getPriority(): int
    {
        return 75;
    }

    public function canHandle(\Throwable $exception): bool
    {
        return $exception instanceof AuthenticationException;
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
