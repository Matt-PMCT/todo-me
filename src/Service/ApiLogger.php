<?php

declare(strict_types=1);

namespace App\Service;

use App\EventListener\RequestIdListener;
use App\Interface\ApiLoggerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wrapper around Monolog logger for API-specific logging.
 *
 * Automatically includes:
 * - Request ID in all log context
 * - Timing information
 * - Structured logging format
 */
final class ApiLogger implements ApiLoggerInterface
{
    private ?float $requestStartTime = null;

    public function __construct(
        private readonly LoggerInterface $apiLogger,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Logs an incoming API request.
     *
     * @param Request              $request           The HTTP request
     * @param array<string, mixed> $additionalContext Additional context data
     */
    public function logRequest(Request $request, array $additionalContext = []): void
    {
        $this->requestStartTime = microtime(true);

        $context = array_merge([
            'request_id' => $this->getRequestId(),
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'content_type' => $request->headers->get('Content-Type'),
            'content_length' => $request->headers->get('Content-Length'),
        ], $additionalContext);

        $this->apiLogger->info('API Request received', $context);
    }

    /**
     * Logs an API response.
     *
     * @param Response             $response          The HTTP response
     * @param array<string, mixed> $additionalContext Additional context data
     */
    public function logResponse(Response $response, array $additionalContext = []): void
    {
        $duration = $this->calculateDuration();

        $context = array_merge([
            'request_id' => $this->getRequestId(),
            'status_code' => $response->getStatusCode(),
            'content_type' => $response->headers->get('Content-Type'),
            'content_length' => strlen((string) $response->getContent()),
            'duration_ms' => $duration,
        ], $additionalContext);

        $logLevel = $this->determineLogLevel($response->getStatusCode());

        $this->apiLogger->log($logLevel, 'API Response sent', $context);
    }

    /**
     * Logs an API error.
     *
     * @param \Throwable           $exception         The exception that occurred
     * @param array<string, mixed> $additionalContext Additional context data
     */
    public function logError(\Throwable $exception, array $additionalContext = []): void
    {
        $duration = $this->calculateDuration();

        $context = array_merge([
            'request_id' => $this->getRequestId(),
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'duration_ms' => $duration,
        ], $additionalContext);

        $this->apiLogger->error('API Error occurred', $context);
    }

    /**
     * Logs a warning message.
     *
     * @param string               $message The warning message
     * @param array<string, mixed> $context Additional context data
     */
    public function logWarning(string $message, array $context = []): void
    {
        $this->apiLogger->warning($message, array_merge($context, [
            'request_id' => $this->getRequestId(),
        ]));
    }

    /**
     * Logs an info message.
     *
     * @param string               $message The info message
     * @param array<string, mixed> $context Additional context data
     */
    public function logInfo(string $message, array $context = []): void
    {
        $this->apiLogger->info($message, array_merge($context, [
            'request_id' => $this->getRequestId(),
        ]));
    }

    /**
     * Logs a debug message.
     *
     * @param string               $message The debug message
     * @param array<string, mixed> $context Additional context data
     */
    public function logDebug(string $message, array $context = []): void
    {
        $this->apiLogger->debug($message, array_merge($context, [
            'request_id' => $this->getRequestId(),
        ]));
    }

    /**
     * Gets the current request ID.
     */
    private function getRequestId(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return null;
        }

        return $request->attributes->get(RequestIdListener::REQUEST_ID_ATTRIBUTE);
    }

    /**
     * Calculates the duration since the request started.
     *
     * @return float|null Duration in milliseconds, or null if not available
     */
    private function calculateDuration(): ?float
    {
        if ($this->requestStartTime === null) {
            return null;
        }

        return round((microtime(true) - $this->requestStartTime) * 1000, 2);
    }

    /**
     * Determines the appropriate log level based on HTTP status code.
     */
    private function determineLogLevel(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            default => 'info',
        };
    }

    /**
     * Hashes an email address for safe logging.
     *
     * Returns a truncated SHA-256 hash that allows correlation
     * of log entries without exposing the full email address.
     *
     * @param string $email The email address to hash
     *
     * @return string 16-character truncated hash
     */
    public static function hashEmail(string $email): string
    {
        return substr(hash('sha256', $email), 0, 16);
    }
}
