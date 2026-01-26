<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listener to rewrite URLs in HTML responses when behind a reverse proxy with path prefix.
 *
 * When nginx strips the /todo-me/ prefix before passing to the application,
 * Symfony generates URLs without the prefix (e.g., /login, /assets/...).
 * This listener reads the X-Forwarded-Prefix header and rewrites these URLs in the HTML.
 *
 * Rewrites:
 * - href="/" → href="/todo-me/"
 * - href="/login" → href="/todo-me/login"
 * - src="/assets/" → src="/todo-me/assets/"
 * - href="/assets/" → href="/todo-me/assets/"
 */
class PathPrefixHtmlRewriteListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', 1]];  // Priority 1 (after redirects)
    }

    public function onResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();

        // Only process HTML responses
        if (!$this->isHtmlResponse($response)) {
            return;
        }

        // Don't process redirects (already handled by PathPrefixRedirectListener)
        if ($response->isRedirect()) {
            return;
        }

        // Get the path prefix from the proxy
        $prefix = $request->headers->get('X-Forwarded-Prefix');
        if (!$prefix) {
            error_log('PathPrefixHtmlRewriteListener: No X-Forwarded-Prefix header');
            return;
        }

        error_log('PathPrefixHtmlRewriteListener: Processing HTML with prefix=' . $prefix);

        // Get the current response content
        $content = $response->getContent();
        if (!$content) {
            return;
        }

        // Rewrite URLs in the HTML
        $newContent = $this->rewriteUrls($content, $prefix);

        if ($newContent !== $content) {
            error_log('PathPrefixHtmlRewriteListener: Rewritten URLs in response');
            $response->setContent($newContent);
        } else {
            error_log('PathPrefixHtmlRewriteListener: No URLs matched to rewrite');
        }
    }

    private function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');
        return str_contains($contentType, 'text/html');
    }

    private function rewriteUrls(string $html, string $prefix): string
    {
        // Rewrite relative URLs in href and src attributes
        // Match: href="/" href="/login" src="/assets/..." etc.
        // But NOT: href="http://..." href="https://..."

        // First, replace double-quoted paths
        $html = preg_replace_callback(
            '/(?:href|src)\s*=\s*"(\/(?!\/)[^"]*)"/i',
            function ($matches) use ($prefix) {
                $url = $matches[1];
                if (!str_starts_with($url, $prefix)) {
                    return str_replace($url, $prefix . $url, $matches[0]);
                }
                return $matches[0];
            },
            $html
        );

        // Then, replace single-quoted paths
        $html = preg_replace_callback(
            "/(?:href|src)\s*=\s*'(\/(?!\/)[^']*)'/i",
            function ($matches) use ($prefix) {
                $url = $matches[1];
                if (!str_starts_with($url, $prefix)) {
                    return str_replace($url, $prefix . $url, $matches[0]);
                }
                return $matches[0];
            },
            $html
        );

        return $html;
    }
}
