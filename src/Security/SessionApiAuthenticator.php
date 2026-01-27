<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Session-based authenticator for API requests from the web UI.
 *
 * This authenticator allows web browser AJAX calls to use the existing
 * session cookie for authentication instead of exposing API tokens
 * in frontend JavaScript. CSRF protection is enforced for state-changing
 * operations.
 *
 * Authentication priority:
 * 1. If Authorization/X-API-Key headers present → skip (let ApiTokenAuthenticator handle)
 * 2. If session exists with authenticated user → use session auth with CSRF validation
 */
final class SessionApiAuthenticator extends AbstractAuthenticator
{
    private const CSRF_TOKEN_ID = 'api';
    private const CSRF_HEADER = 'X-CSRF-Token';

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Only handle API routes
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return false;
        }

        // Skip if token auth headers are present - let ApiTokenAuthenticator handle those
        if ($request->headers->has('Authorization') || $request->headers->has('X-API-Key')) {
            return false;
        }

        // Support if session exists and user is authenticated via main firewall
        return $request->hasSession() && $request->getSession()->has('_security_main');
    }

    public function authenticate(Request $request): Passport
    {
        // Validate CSRF token for state-changing requests
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $csrfToken = $request->headers->get(self::CSRF_HEADER);

            if ($csrfToken === null || $csrfToken === '') {
                throw new AuthenticationException('CSRF token missing');
            }

            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $csrfToken))) {
                throw new AuthenticationException('Invalid CSRF token');
            }
        }

        // Get user from session
        $session = $request->getSession();
        $serializedToken = $session->get('_security_main');

        if ($serializedToken === null) {
            throw new AuthenticationException('No session token found');
        }

        $token = unserialize($serializedToken);

        if (!$token instanceof TokenInterface) {
            throw new AuthenticationException('Invalid session token');
        }

        $userIdentifier = $token->getUserIdentifier();

        if ($userIdentifier === '' || $userIdentifier === null) {
            throw new AuthenticationException('No user identifier in session');
        }

        return new SelfValidatingPassport(
            new UserBadge($userIdentifier)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Continue to controller
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Return null to let the next authenticator try (ApiTokenAuthenticator)
        // This allows graceful fallback for external API clients
        return null;
    }
}
