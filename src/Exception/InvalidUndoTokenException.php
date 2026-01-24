<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when an undo token is invalid, expired, or of the wrong type.
 */
final class InvalidUndoTokenException extends HttpException
{
    public readonly string $errorCode;

    private function __construct(
        string $message = 'Invalid or expired undo token',
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'INVALID_UNDO_TOKEN';

        parent::__construct(
            statusCode: 400,
            message: $message,
            previous: $previous,
        );
    }

    /**
     * Token not found or already consumed.
     */
    public static function expired(): self
    {
        return new self('Invalid or expired undo token');
    }

    /**
     * Token is not for the expected entity type.
     */
    public static function wrongEntityType(string $expectedType, string $actualType): self
    {
        return new self(sprintf(
            'Undo token is for a %s, not a %s',
            $actualType,
            $expectedType
        ));
    }

    /**
     * Token is not for the expected action type.
     */
    public static function wrongActionType(string $expectedAction): self
    {
        return new self(sprintf(
            'Undo token is not for a %s operation',
            $expectedAction
        ));
    }

    /**
     * Unknown action type in token.
     */
    public static function unknownAction(string $action): self
    {
        return new self(sprintf('Unknown undo action type: %s', $action));
    }
}
