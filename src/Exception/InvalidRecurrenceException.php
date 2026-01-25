<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Exception thrown when a recurrence rule is invalid or cannot be parsed.
 */
final class InvalidRecurrenceException extends BadRequestHttpException
{
    public readonly string $errorCode;
    public readonly string $pattern;

    public function __construct(
        string $message,
        string $pattern = '',
        string $errorCode = 'INVALID_RECURRENCE',
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = $errorCode;
        $this->pattern = $pattern;

        parent::__construct(
            message: $message,
            previous: $previous,
        );
    }

    /**
     * Creates an exception for an invalid pattern.
     */
    public static function invalidPattern(string $pattern): self
    {
        return new self(
            message: sprintf('Cannot parse recurrence pattern: "%s"', $pattern),
            pattern: $pattern,
            errorCode: 'INVALID_RECURRENCE_PATTERN',
        );
    }

    /**
     * Creates an exception for an invalid date in the pattern.
     */
    public static function invalidDate(string $pattern, string $dateStr): self
    {
        return new self(
            message: sprintf('Invalid date "%s" in recurrence pattern: "%s"', $dateStr, $pattern),
            pattern: $pattern,
            errorCode: 'INVALID_RECURRENCE_DATE',
        );
    }

    /**
     * Creates an exception for an unsupported pattern.
     */
    public static function unsupportedPattern(string $pattern, string $reason = ''): self
    {
        $message = sprintf('Unsupported recurrence pattern: "%s"', $pattern);
        if ($reason !== '') {
            $message .= '. '.$reason;
        }

        return new self(
            message: $message,
            pattern: $pattern,
            errorCode: 'UNSUPPORTED_RECURRENCE_PATTERN',
        );
    }

    /**
     * Creates an exception for a recurrence rule that has ended.
     */
    public static function recurrenceEnded(string $pattern): self
    {
        return new self(
            message: sprintf('Recurrence has ended for pattern: "%s"', $pattern),
            pattern: $pattern,
            errorCode: 'RECURRENCE_ENDED',
        );
    }

    /**
     * Creates an exception when complete-forever is called on a non-recurring task.
     */
    public static function taskNotRecurring(): self
    {
        return new self(
            message: 'Cannot use complete-forever on a non-recurring task',
            pattern: '',
            errorCode: 'TASK_NOT_RECURRING',
        );
    }

    /**
     * Get the invalid pattern that caused the exception.
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }
}
