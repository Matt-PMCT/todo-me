<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\ResponseFormatter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * Subscriber that applies rate limiting to API routes.
 *
 * - Only applies to /api/* routes
 * - Uses Symfony's RateLimiter component
 * - Adds rate limit headers to responses
 * - Returns 429 Too Many Requests when limit exceeded
 */
final class ApiRateLimitSubscriber implements EventSubscriberInterface
{
    public const RATE_LIMIT_ATTRIBUTE = '_rate_limit';

    private const HEADER_LIMIT = 'X-RateLimit-Limit';
    private const HEADER_REMAINING = 'X-RateLimit-Remaining';
    private const HEADER_RESET = 'X-RateLimit-Reset';
    private const HEADER_RETRY_AFTER = 'Retry-After';

    public function __construct(
        private readonly RateLimiterFactory $anonymousApiLimiter,
        private readonly RateLimiterFactory $authenticatedApiLimiter,
        private readonly ResponseFormatter $responseFormatter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
            KernelEvents::RESPONSE => ['onKernelResponse', -100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Only apply to /api/* routes
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Determine the identifier for rate limiting
        $identifier = $this->getIdentifier($request);

        // Determine if request is authenticated (has valid API token)
        $isAuthenticated = $this->isAuthenticated($request);

        // Get the appropriate limiter
        $limiter = $isAuthenticated
            ? $this->authenticatedApiLimiter->create($identifier)
            : $this->anonymousApiLimiter->create($identifier);

        // Consume a token
        $rateLimit = $limiter->consume(1);

        // Store rate limit info for response headers
        $request->attributes->set(self::RATE_LIMIT_ATTRIBUTE, $rateLimit);

        // If rate limit exceeded, return 429 response
        if (!$rateLimit->isAccepted()) {
            $retryAfter = $rateLimit->getRetryAfter()->getTimestamp() - time();

            $response = $this->responseFormatter->error(
                'Rate limit exceeded. Please try again later.',
                'RATE_LIMITED',
                429,
                [
                    'retry_after' => max(0, $retryAfter),
                ]
            );

            // Add rate limit headers
            $response->headers->set(self::HEADER_LIMIT, (string) $rateLimit->getLimit());
            $response->headers->set(self::HEADER_REMAINING, '0');
            $response->headers->set(self::HEADER_RESET, (string) $rateLimit->getRetryAfter()->getTimestamp());
            $response->headers->set(self::HEADER_RETRY_AFTER, (string) max(0, $retryAfter));

            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        /** @var RateLimit|null $rateLimit */
        $rateLimit = $request->attributes->get(self::RATE_LIMIT_ATTRIBUTE);

        if ($rateLimit === null) {
            return;
        }

        // Add rate limit headers to all API responses
        $response->headers->set(self::HEADER_LIMIT, (string) $rateLimit->getLimit());
        $response->headers->set(self::HEADER_REMAINING, (string) $rateLimit->getRemainingTokens());
        $response->headers->set(self::HEADER_RESET, (string) $rateLimit->getRetryAfter()->getTimestamp());
    }

    /**
     * Gets the identifier for rate limiting.
     * Uses API token if present, otherwise falls back to IP address.
     */
    private function getIdentifier(\Symfony\Component\HttpFoundation\Request $request): string
    {
        // Check for API token in Authorization header
        $authHeader = $request->headers->get('Authorization');

        if ($authHeader !== null && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            if (!empty($token)) {
                return 'token_' . hash('sha256', $token);
            }
        }

        // Check for API key in X-API-Key header
        $apiKey = $request->headers->get('X-API-Key');
        if ($apiKey !== null && !empty($apiKey)) {
            return 'token_' . hash('sha256', $apiKey);
        }

        // Fall back to IP address
        $ip = $request->getClientIp() ?? 'unknown';

        return 'ip_' . $ip;
    }

    /**
     * Determines if the request is authenticated.
     */
    private function isAuthenticated(\Symfony\Component\HttpFoundation\Request $request): bool
    {
        // Check for Bearer token
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader !== null && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            if (!empty($token)) {
                return true;
            }
        }

        // Check for API key
        $apiKey = $request->headers->get('X-API-Key');
        if ($apiKey !== null && !empty($apiKey)) {
            return true;
        }

        return false;
    }
}
