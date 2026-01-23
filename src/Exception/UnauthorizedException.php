<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when a user is not authenticated.
 */
final class UnauthorizedException extends HttpException
{
    public readonly string $errorCode;

    public function __construct(
        string $message = 'Authentication required',
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'UNAUTHORIZED';

        parent::__construct(
            statusCode: 401,
            message: $message,
            previous: $previous,
            headers: ['WWW-Authenticate' => 'Bearer'],
        );
    }

    /**
     * Creates an exception for missing authentication.
     */
    public static function missingCredentials(): self
    {
        return new self('Authentication credentials were not provided');
    }

    /**
     * Creates an exception for invalid authentication.
     */
    public static function invalidCredentials(): self
    {
        return new self('Invalid authentication credentials');
    }

    /**
     * Creates an exception for expired authentication.
     */
    public static function expiredCredentials(): self
    {
        return new self('Authentication credentials have expired');
    }
}
