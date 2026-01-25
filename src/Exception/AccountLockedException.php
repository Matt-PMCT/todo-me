<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when an account is temporarily locked due to too many failed login attempts.
 */
final class AccountLockedException extends HttpException
{
    public readonly string $errorCode;

    public function __construct(
        public readonly int $remainingSeconds,
        string $message = 'Account is temporarily locked',
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'ACCOUNT_LOCKED';

        parent::__construct(
            statusCode: 423,
            message: $message,
            previous: $previous,
        );
    }

    /**
     * Creates an exception for a locked account with remaining lockout time.
     */
    public static function locked(int $remainingSeconds): self
    {
        return new self(
            remainingSeconds: $remainingSeconds,
            message: sprintf('Account is locked. Try again in %d seconds.', $remainingSeconds),
        );
    }
}
