<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

/**
 * Listener that manages request IDs for tracing requests through the system.
 *
 * - On request: Checks for X-Request-ID header, generates UUID v4 if not present
 * - Stores request ID in request attributes for access throughout the request lifecycle
 * - On response: Adds X-Request-ID to response headers for client correlation
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onKernelRequest', priority: 255)]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onKernelResponse', priority: -255)]
final class RequestIdListener
{
    public const REQUEST_ID_HEADER = 'X-Request-ID';
    public const REQUEST_ID_ATTRIBUTE = '_request_id';

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Check for existing X-Request-ID header
        $requestId = $request->headers->get(self::REQUEST_ID_HEADER);

        // Validate the request ID format (should be a valid UUID)
        if ($requestId !== null && !$this->isValidUuid($requestId)) {
            $requestId = null;
        }

        // Generate a new UUID v4 if not present or invalid
        if ($requestId === null) {
            $requestId = Uuid::v4()->toRfc4122();
        }

        // Store in request attributes for access throughout the application
        $request->attributes->set(self::REQUEST_ID_ATTRIBUTE, $requestId);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $requestId = $request->attributes->get(self::REQUEST_ID_ATTRIBUTE);

        if ($requestId !== null) {
            $response->headers->set(self::REQUEST_ID_HEADER, $requestId);
        }
    }

    /**
     * Validates if a string is a valid UUID format.
     */
    private function isValidUuid(string $uuid): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        ) === 1;
    }
}
