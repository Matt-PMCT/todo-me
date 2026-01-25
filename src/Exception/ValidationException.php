<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when validation fails.
 */
final class ValidationException extends HttpException
{
    public readonly string $errorCode;

    /**
     * @param array<string, string|array<string>> $errors Validation errors keyed by field name
     */
    public function __construct(
        private readonly array $errors = [],
        string $message = 'Validation failed',
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'VALIDATION_ERROR';

        parent::__construct(
            statusCode: 422,
            message: $message,
            previous: $previous,
        );
    }

    /**
     * Gets the validation errors.
     *
     * @return array<string, string|array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Creates a ValidationException from a single field error.
     *
     * @param string $field   The field name
     * @param string $message The error message
     */
    public static function forField(string $field, string $message): self
    {
        return new self([$field => $message]);
    }

    /**
     * Creates a ValidationException from multiple field errors.
     *
     * @param array<string, string|array<string>> $errors
     */
    public static function forFields(array $errors): self
    {
        return new self($errors);
    }

    /**
     * Creates a ValidationException for an invalid email.
     */
    public static function invalidEmail(string $email): self
    {
        return self::forField('email', sprintf('The email "%s" is not a valid email address', $email));
    }

    /**
     * Creates a ValidationException for an invalid password.
     */
    public static function invalidPassword(): self
    {
        return self::forField('password', 'Password must be at least 8 characters and contain at least one letter and one number');
    }

    /**
     * Creates a ValidationException for invalid JSON in request body.
     */
    public static function invalidJson(string $error = 'Invalid JSON in request body'): self
    {
        return self::forField('body', $error);
    }
}
