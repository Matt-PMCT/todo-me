<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when a user is not authorized to perform an action.
 */
final class ForbiddenException extends HttpException
{
    public readonly string $errorCode;

    /**
     * @param string $reason The reason access was denied
     */
    public function __construct(
        public readonly string $reason = 'Access denied',
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'PERMISSION_DENIED';

        parent::__construct(
            statusCode: 403,
            message: $reason,
            previous: $previous,
        );
    }

    /**
     * Creates an exception for ownership violation.
     */
    public static function notOwner(string $entityType): self
    {
        return new self(sprintf('You do not have permission to access this %s', $entityType));
    }

    /**
     * Creates an exception for insufficient permissions.
     */
    public static function insufficientPermissions(string $action): self
    {
        return new self(sprintf('You do not have permission to %s', $action));
    }

    /**
     * Creates an exception for resource access denial.
     */
    public static function resourceAccessDenied(): self
    {
        return new self('You do not have permission to access this resource');
    }
}
