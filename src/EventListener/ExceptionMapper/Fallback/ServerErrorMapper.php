<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper\Fallback;

use App\EventListener\ExceptionMapper\ExceptionMapperInterface;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fallback mapper for any unhandled exceptions.
 *
 * Always returns a 500 Internal Server Error. In development mode,
 * includes additional debug information.
 */
#[AutoconfigureTag('app.exception_mapper')]
final class ServerErrorMapper implements ExceptionMapperInterface
{
    public function __construct(
        private readonly string $environment,
    ) {
    }

    public static function getPriority(): int
    {
        return 0;
    }

    public function canHandle(\Throwable $exception): bool
    {
        return true;
    }

    public function map(\Throwable $exception): ExceptionMapping
    {
        $message = $this->environment === 'dev'
            ? $exception->getMessage()
            : 'An internal server error occurred';

        $details = [];

        if ($this->environment === 'dev') {
            $details = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        return new ExceptionMapping(
            'SERVER_ERROR',
            $message,
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $details,
        );
    }
}
