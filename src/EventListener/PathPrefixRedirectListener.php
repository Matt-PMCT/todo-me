<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listener to fix redirect URLs when application is behind a reverse proxy with path prefix.
 *
 * When nginx strips the /todo-me/ prefix before passing to the application,
 * Symfony generates redirects to /login instead of /todo-me/login.
 *
 * This listener reads the X-Forwarded-Prefix header and adds it back to relative redirects.
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

        // Debug: Log what we're seeing
        error_log(sprintf(
            'PathPrefixRedirectListener: location=%s, prefix=%s, request_uri=%s',
            $location,
            $prefix ?? 'NULL',
            $request->getRequestUri()
        ));

        if (!$prefix) {
            error_log('PathPrefixRedirectListener: No X-Forwarded-Prefix header found');
            return;
        }

        // Only add prefix to relative URLs that don't already have it
        if ($location[0] === '/' && !str_starts_with($location, $prefix) && !preg_match('#^https?://#', $location)) {
            error_log(sprintf('PathPrefixRedirectListener: Updating location from %s to %s', $location, $prefix . $location));
            $response->headers->set('Location', $prefix . $location);
        }
    }
}
