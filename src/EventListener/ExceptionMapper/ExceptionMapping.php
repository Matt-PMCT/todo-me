<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper;

/**
 * Immutable value object representing an exception mapping result.
 */
final readonly class ExceptionMapping
{
    /**
     * @param string $errorCode Machine-readable error code (e.g., 'VALIDATION_ERROR')
     * @param string $message Human-readable error message
     * @param int $statusCode HTTP status code
     * @param array<string, mixed> $details Additional error details
     */
    public function __construct(
        public string $errorCode,
        public string $message,
        public int $statusCode,
        public array $details = [],
    ) {
    }
}
