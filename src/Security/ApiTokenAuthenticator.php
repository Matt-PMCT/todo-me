<?php

declare(strict_types=1);

namespace App\Security;

use App\Interface\ApiLoggerInterface;
use App\Interface\ApiTokenServiceInterface;
use App\Interface\UserServiceInterface;
use App\Service\ApiLogger;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Custom authenticator for API token authentication.
 *
 * Supports two token methods:
 * - Authorization: Bearer {token}
 * - X-API-Key: {token}
 *
 * Supports two token systems:
 * 1. Named API tokens (prefixed with "tm_") - stored in api_tokens table
 * 2. User login tokens - stored in users.api_token column
 */
final class ApiTokenAuthenticator extends AbstractAuthenticator
{
    private const BEARER_PREFIX = 'Bearer ';
    private const API_KEY_HEADER = 'X-API-Key';
    private const NAMED_TOKEN_PREFIX = 'tm_';

    /**
     * Generic authentication error message to prevent token enumeration.
     * Specific failure reasons are logged server-side but not exposed to clients.
     */
    private const AUTH_FAILED_MESSAGE = 'Authentication failed';

    /**
     * Public routes that don't require authentication.
     */
    private const PUBLIC_ROUTES = [
        '/api/v1/auth/register',
        '/api/v1/auth/token',
        '/api/v1/auth/refresh',
    ];

    public function __construct(
        private readonly UserServiceInterface $userService,
        private readonly ApiLoggerInterface $apiLogger,
        private readonly ApiTokenServiceInterface $apiTokenService,
    ) {
    }

    /**
     * Determines if this authenticator should be used for the current request.
     */
    public function supports(Request $request): ?bool
    {
        // Skip authentication for non-API routes
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return false;
        }

        // Skip authentication for public endpoints
        foreach (self::PUBLIC_ROUTES as $publicRoute) {
            if ($request->getPathInfo() === $publicRoute) {
                return false;
            }
        }

        // Check if token is present in either header
        return $this->hasApiToken($request);
    }

    /**
     * Authenticates the request.
     */
    public function authenticate(Request $request): Passport
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            $this->apiLogger->logWarning('Authentication attempt without token', [
                'uri' => $request->getRequestUri(),
                'ip' => $request->getClientIp(),
            ]);

            throw new CustomUserMessageAuthenticationException('API token not provided');
        }

        return new SelfValidatingPassport(
            new UserBadge($token, function (string $token) use ($request) {
                // First, try the named API tokens (new system) if token has the tm_ prefix
                if (str_starts_with($token, self::NAMED_TOKEN_PREFIX)) {
                    $apiToken = $this->apiTokenService->findValidToken($token);

                    if ($apiToken !== null) {
                        $this->apiTokenService->updateLastUsed($apiToken);

                        $this->apiLogger->logInfo('User authenticated via named API token', [
                            'user_id' => $apiToken->getOwner()->getId(),
                            'token_id' => $apiToken->getId(),
                            'token_name' => $apiToken->getName(),
                        ]);

                        return $apiToken->getOwner();
                    }

                    // Named token not found or expired
                    $this->apiLogger->logWarning('Authentication attempt with invalid named API token', [
                        'uri' => $request->getRequestUri(),
                        'ip' => $request->getClientIp(),
                        'token_prefix' => substr($token, 0, 8),
                    ]);

                    throw new CustomUserMessageAuthenticationException(self::AUTH_FAILED_MESSAGE);
                }

                // Fall back to User.apiToken (login token - existing system)
                $user = $this->userService->findByApiTokenIgnoreExpiration($token);

                if ($user === null) {
                    $this->apiLogger->logWarning('Authentication attempt with invalid token', [
                        'uri' => $request->getRequestUri(),
                        'ip' => $request->getClientIp(),
                    ]);

                    // Use generic message to prevent token enumeration
                    throw new CustomUserMessageAuthenticationException(self::AUTH_FAILED_MESSAGE);
                }

                // Check if token is expired
                if ($user->isApiTokenExpired()) {
                    $this->apiLogger->logWarning('Authentication attempt with expired token', [
                        'uri' => $request->getRequestUri(),
                        'ip' => $request->getClientIp(),
                        'user_id' => $user->getId(),
                    ]);

                    // Use generic message to prevent token enumeration
                    throw new CustomUserMessageAuthenticationException(self::AUTH_FAILED_MESSAGE);
                }

                $this->apiLogger->logInfo('User authenticated successfully', [
                    'user_id' => $user->getId(),
                    'email_hash' => ApiLogger::hashEmail($user->getEmail()),
                ]);

                return $user;
            })
        );
    }

    /**
     * Called when authentication is successful.
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Return null to let the request continue
        return null;
    }

    /**
     * Called when authentication fails.
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->apiLogger->logWarning('Authentication failed', [
            'uri' => $request->getRequestUri(),
            'ip' => $request->getClientIp(),
            'reason' => $exception->getMessage(),
        ]);

        $data = [
            'success' => false,
            'data' => null,
            'error' => [
                'code' => 'AUTHENTICATION_FAILED',
                'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
            ],
            'meta' => [
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
            ],
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Checks if the request has an API token in any supported header.
     */
    private function hasApiToken(Request $request): bool
    {
        return $this->hasBearerToken($request) || $request->headers->has(self::API_KEY_HEADER);
    }

    /**
     * Checks if the request has a Bearer token.
     */
    private function hasBearerToken(Request $request): bool
    {
        $authHeader = $request->headers->get('Authorization', '');

        return str_starts_with($authHeader, self::BEARER_PREFIX);
    }

    /**
     * Extracts the API token from the request headers.
     *
     * Priority:
     * 1. Authorization: Bearer {token}
     * 2. X-API-Key: {token}
     */
    private function extractToken(Request $request): ?string
    {
        // Try Bearer token first
        $authHeader = $request->headers->get('Authorization', '');
        if (str_starts_with($authHeader, self::BEARER_PREFIX)) {
            $token = substr($authHeader, strlen(self::BEARER_PREFIX));

            return $token !== '' ? $token : null;
        }

        // Try X-API-Key header
        $apiKey = $request->headers->get(self::API_KEY_HEADER);
        if ($apiKey !== null && $apiKey !== '') {
            return $apiKey;
        }

        return null;
    }
}
