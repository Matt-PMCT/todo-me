<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when an entity is in an invalid state for an operation.
 *
 * This is typically a programming error (500) rather than a client error.
 */
final class InvalidStateException extends HttpException
{
    public readonly string $errorCode;

    public function __construct(
        string $message = 'Entity is in an invalid state for this operation',
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'INVALID_STATE';

        parent::__construct(
            statusCode: 500,
            message: $message,
            previous: $previous,
        );
    }

    /**
     * Creates an exception for a missing required ID.
     *
     * @param string $entityType The type of entity (e.g., 'Project', 'Task')
     */
    public static function missingRequiredId(string $entityType): self
    {
        return new self(sprintf('%s must have an ID before this operation', $entityType));
    }

    /**
     * Creates an exception for a missing owner.
     *
     * @param string $entityType The type of entity (e.g., 'Project', 'Task')
     */
    public static function missingOwner(string $entityType): self
    {
        return new self(sprintf('%s must have an owner before this operation', $entityType));
    }
}
