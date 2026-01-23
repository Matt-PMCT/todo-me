<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when a recurrence configuration is invalid.
 */
final class InvalidRecurrenceException extends HttpException
{
    public readonly string $errorCode;

    /**
     * @param string $reason The reason the recurrence is invalid
     * @param string|null $invalidValue The invalid value that was provided
     * @param string[]|null $validValues The valid values that are allowed
     */
    public function __construct(
        public readonly string $reason,
        public readonly ?string $invalidValue = null,
        public readonly ?array $validValues = null,
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'INVALID_RECURRENCE';

        parent::__construct(
            statusCode: 400,
            message: $reason,
            previous: $previous,
        );
    }

    /**
     * Creates an exception for an invalid recurrence type.
     */
    public static function forRecurrenceType(string $type): self
    {
        return new self(
            reason: sprintf('Invalid recurrence type "%s". Allowed values: absolute, relative', $type),
            invalidValue: $type,
            validValues: ['absolute', 'relative'],
        );
    }

    /**
     * Creates an exception for an invalid recurrence rule.
     */
    public static function forRecurrenceRule(string $rule): self
    {
        return new self(
            reason: sprintf('Invalid recurrence rule: %s', $rule),
            invalidValue: $rule,
        );
    }

    /**
     * Creates an exception for missing recurrence configuration.
     */
    public static function missingConfiguration(string $field): self
    {
        return new self(
            reason: sprintf('Recurrence configuration requires %s to be set', $field),
        );
    }
}
