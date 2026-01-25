<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper\Symfony;

use App\EventListener\ExceptionMapper\ExceptionMapperInterface;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Maps Symfony Security AccessDeniedException to error response.
 *
 * Symfony throws AccessDeniedException for both unauthenticated users and
 * authenticated users lacking permissions. This mapper differentiates:
 * - Unauthenticated users → 401 UNAUTHORIZED
 * - Authenticated users lacking permission → 403 FORBIDDEN
 */
#[AutoconfigureTag('app.exception_mapper')]
final class AccessDeniedExceptionMapper implements ExceptionMapperInterface
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

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
        if ($this->security->getUser() === null) {
            return new ExceptionMapping(
                'UNAUTHORIZED',
                'Authentication required',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        return new ExceptionMapping(
            'FORBIDDEN',
            'Access denied',
            Response::HTTP_FORBIDDEN,
        );
    }
}
