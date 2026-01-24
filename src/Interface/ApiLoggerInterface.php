<?php

declare(strict_types=1);

namespace App\Interface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Interface for API logging operations.
 */
interface ApiLoggerInterface
{
    /**
     * Logs an incoming API request.
     *
     * @param Request $request The HTTP request
     * @param array<string, mixed> $additionalContext Additional context data
     */
    public function logRequest(Request $request, array $additionalContext = []): void;

    /**
     * Logs an API response.
     *
     * @param Response $response The HTTP response
     * @param array<string, mixed> $additionalContext Additional context data
     */
    public function logResponse(Response $response, array $additionalContext = []): void;

    /**
     * Logs an API error.
     *
     * @param \Throwable $exception The exception that occurred
     * @param array<string, mixed> $additionalContext Additional context data
     */
    public function logError(\Throwable $exception, array $additionalContext = []): void;

    /**
     * Logs a warning message.
     *
     * @param string $message The warning message
     * @param array<string, mixed> $context Additional context data
     */
    public function logWarning(string $message, array $context = []): void;

    /**
     * Logs an info message.
     *
     * @param string $message The info message
     * @param array<string, mixed> $context Additional context data
     */
    public function logInfo(string $message, array $context = []): void;

    /**
     * Logs a debug message.
     *
     * @param string $message The debug message
     * @param array<string, mixed> $context Additional context data
     */
    public function logDebug(string $message, array $context = []): void;
}
