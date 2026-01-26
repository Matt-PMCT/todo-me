<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listener to fix redirect URLs when Symfony generates redirects without path prefix.
 *
 * This is a fallback for any redirects generated through non-standard mechanisms
 * that might not use the configured router context.
 *
 * When APP_PATH_PREFIX is configured, redirects should automatically include it
 * through Symfony's URL generation. This listener ensures any remaining redirects
 * without the prefix are corrected using the X-Forwarded-Prefix header from proxy.
 */
class PathPrefixRedirectListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();

        // Only process redirects
        if (!$response->isRedirect()) {
            return;
        }

        $location = $response->headers->get('Location');
        if (!$location) {
            return;
        }

        // Get the path prefix from the proxy
        $prefix = $request->headers->get('X-Forwarded-Prefix');
        if (!$prefix) {
            return;
        }

        // Only add prefix to relative URLs that don't already have it
        if ($location[0] === '/' && !str_starts_with($location, $prefix) && !preg_match('#^https?://#', $location)) {
            $response->headers->set('Location', $prefix . $location);
        }
    }
}
