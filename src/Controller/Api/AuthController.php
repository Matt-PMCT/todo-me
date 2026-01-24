<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\LoginRequest;
use App\DTO\RegisterRequest;
use App\DTO\TokenResponse;
use App\DTO\UserResponse;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Service\ApiLogger;
use App\Service\ResponseFormatter;
use App\Service\UserService;
use App\Service\ValidationHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/auth', name: 'api_auth_')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly ResponseFormatter $responseFormatter,
        private readonly ValidatorInterface $validator,
        private readonly ApiLogger $apiLogger,
        private readonly RateLimiterFactory $loginLimiter,
        private readonly RateLimiterFactory $registrationLimiter,
        private readonly ValidationHelper $validationHelper,
    ) {
    }

    /**
     * Register a new user.
     *
     * @param Request $request The HTTP request
     * @return JsonResponse
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        // Rate limiting: 10 attempts per hour per IP
        $limiter = $this->registrationLimiter->create($request->getClientIp() ?? 'unknown');
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();

            $this->apiLogger->logWarning('Registration rate limit exceeded', [
                'ip' => $request->getClientIp(),
                'retry_after' => $retryAfter->getTimestamp(),
            ]);

            $response = $this->responseFormatter->error(
                'Too many registration attempts. Please try again later.',
                'RATE_LIMIT_EXCEEDED',
                Response::HTTP_TOO_MANY_REQUESTS
            );

            $response->headers->set('Retry-After', (string) $retryAfter->getTimestamp());
            $response->headers->set('X-RateLimit-Remaining', '0');
            $response->headers->set('X-RateLimit-Reset', (string) $retryAfter->getTimestamp());

            return $response;
        }

        $data = $this->validationHelper->decodeJsonBody($request);

        $registerRequest = RegisterRequest::fromArray($data);

        // Validate the request
        $errors = $this->validator->validate($registerRequest);
        if (count($errors) > 0) {
            $validationErrors = [];
            foreach ($errors as $error) {
                $field = $error->getPropertyPath();
                $validationErrors[$field][] = $error->getMessage();
            }

            $this->apiLogger->logWarning('Registration validation failed', [
                'email_hash' => ApiLogger::hashEmail($registerRequest->email),
                'errors' => $validationErrors,
            ]);

            return $this->responseFormatter->error(
                'Validation failed',
                'VALIDATION_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['fields' => $validationErrors]
            );
        }

        // Check if user already exists
        $existingUser = $this->userService->findByEmail($registerRequest->email);
        if ($existingUser !== null) {
            $this->apiLogger->logWarning('Registration attempt for existing email', [
                'email_hash' => ApiLogger::hashEmail($registerRequest->email),
            ]);

            return $this->responseFormatter->error(
                'User with this email already exists',
                'USER_EXISTS',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Create the user
        try {
            $user = $this->userService->register(
                $registerRequest->email,
                $registerRequest->password
            );

            $this->apiLogger->logInfo('User registered successfully', [
                'user_id' => $user->getId(),
                'email_hash' => ApiLogger::hashEmail($user->getEmail()),
            ]);

            $userResponse = UserResponse::fromUser($user);
            $tokenResponse = new TokenResponse(
                $user->getApiToken() ?? '',
                $this->userService->getTokenExpiresAt($user)
            );

            return $this->responseFormatter->created([
                'user' => $userResponse->toArray(),
                'token' => $tokenResponse->token,
                'expiresAt' => $tokenResponse->expiresAt?->format(\DateTimeInterface::RFC3339),
            ]);
        } catch (\Exception $e) {
            $this->apiLogger->logError($e, [
                'email_hash' => ApiLogger::hashEmail($registerRequest->email),
            ]);

            return $this->responseFormatter->error(
                'Failed to register user',
                'REGISTRATION_FAILED',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Generate a new token (login).
     *
     * @param Request $request The HTTP request
     * @return JsonResponse
     */
    #[Route('/token', name: 'token', methods: ['POST'])]
    public function token(Request $request): JsonResponse
    {
        $data = $this->validationHelper->decodeJsonBody($request);

        $loginRequest = LoginRequest::fromArray($data);

        // Validate the request
        $errors = $this->validator->validate($loginRequest);
        if (count($errors) > 0) {
            $validationErrors = [];
            foreach ($errors as $error) {
                $field = $error->getPropertyPath();
                $validationErrors[$field][] = $error->getMessage();
            }

            return $this->responseFormatter->error(
                'Validation failed',
                'VALIDATION_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['fields' => $validationErrors]
            );
        }

        // Rate limiting: 5 attempts per minute per email
        $limiter = $this->loginLimiter->create($loginRequest->email);
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter();

            $this->apiLogger->logWarning('Login rate limit exceeded', [
                'email_hash' => ApiLogger::hashEmail($loginRequest->email),
                'ip' => $request->getClientIp(),
                'retry_after' => $retryAfter->getTimestamp(),
            ]);

            $response = $this->responseFormatter->error(
                'Too many login attempts. Please try again later.',
                'RATE_LIMIT_EXCEEDED',
                Response::HTTP_TOO_MANY_REQUESTS
            );

            $response->headers->set('Retry-After', (string) $retryAfter->getTimestamp());
            $response->headers->set('X-RateLimit-Remaining', '0');
            $response->headers->set('X-RateLimit-Reset', (string) $retryAfter->getTimestamp());

            return $response;
        }

        // Find the user
        $user = $this->userService->findByEmail($loginRequest->email);
        if ($user === null) {
            $this->apiLogger->logWarning('Login attempt for non-existent user', [
                'email_hash' => ApiLogger::hashEmail($loginRequest->email),
                'ip' => $request->getClientIp(),
            ]);

            return $this->responseFormatter->error(
                'Invalid credentials',
                'INVALID_CREDENTIALS',
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Validate password
        if (!$this->userService->validatePassword($user, $loginRequest->password)) {
            $this->apiLogger->logWarning('Login attempt with invalid password', [
                'email_hash' => ApiLogger::hashEmail($loginRequest->email),
                'ip' => $request->getClientIp(),
            ]);

            return $this->responseFormatter->error(
                'Invalid credentials',
                'INVALID_CREDENTIALS',
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Generate new token
        $apiToken = $this->userService->generateNewApiToken($user);

        $this->apiLogger->logInfo('User logged in successfully', [
            'user_id' => $user->getId(),
            'email_hash' => ApiLogger::hashEmail($user->getEmail()),
        ]);

        $tokenResponse = new TokenResponse(
            $apiToken,
            $this->userService->getTokenExpiresAt($user)
        );

        return $this->responseFormatter->success($tokenResponse->toArray());
    }

    /**
     * Revoke the current token (logout).
     *
     * @return JsonResponse
     */
    #[Route('/revoke', name: 'revoke_token', methods: ['POST'])]
    public function revokeToken(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user === null) {
            return $this->responseFormatter->error(
                'Not authenticated',
                'AUTHENTICATION_REQUIRED',
                Response::HTTP_UNAUTHORIZED
            );
        }

        $this->userService->revokeApiToken($user);

        $this->apiLogger->logInfo('User logged out (token revoked)', [
            'user_id' => $user->getId(),
            'email_hash' => ApiLogger::hashEmail($user->getEmail()),
        ]);

        return $this->responseFormatter->noContent();
    }

    /**
     * Refresh the current token.
     * Requires a valid (but possibly expired) token.
     *
     * @return JsonResponse
     */
    #[Route('/refresh', name: 'refresh_token', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        // Extract token from request (works even if expired)
        $token = $this->extractTokenFromRequest($request);

        if ($token === null) {
            return $this->responseFormatter->error(
                'API token not provided',
                'TOKEN_REQUIRED',
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Find user by token without expiration check
        $user = $this->userService->findByApiTokenIgnoreExpiration($token);

        if ($user === null) {
            return $this->responseFormatter->error(
                'Invalid API token',
                'INVALID_TOKEN',
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Check if token is too old (expired more than 7 days ago)
        $expiresAt = $user->getApiTokenExpiresAt();
        if ($expiresAt !== null) {
            $maxRefreshWindow = $expiresAt->modify('+7 days');
            if (new \DateTimeImmutable() > $maxRefreshWindow) {
                $this->apiLogger->logWarning('Token refresh attempt outside window', [
                    'user_id' => $user->getId(),
                    'expired_at' => $expiresAt->format(\DateTimeInterface::RFC3339),
                ]);

                return $this->responseFormatter->error(
                    'Token expired too long ago. Please login again.',
                    'TOKEN_REFRESH_EXPIRED',
                    Response::HTTP_UNAUTHORIZED
                );
            }
        }

        // Generate new token
        $apiToken = $this->userService->generateNewApiToken($user);

        $this->apiLogger->logInfo('Token refreshed successfully', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
        ]);

        $tokenResponse = new TokenResponse(
            $apiToken,
            $this->userService->getTokenExpiresAt($user)
        );

        return $this->responseFormatter->success($tokenResponse->toArray());
    }

    /**
     * Extracts the API token from the request headers.
     */
    private function extractTokenFromRequest(Request $request): ?string
    {
        $authHeader = $request->headers->get('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            return $token !== '' ? $token : null;
        }

        $apiKey = $request->headers->get('X-API-Key');
        if ($apiKey !== null && $apiKey !== '') {
            return $apiKey;
        }

        return null;
    }

    /**
     * Get the current user's information.
     *
     * @return JsonResponse
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if ($user === null) {
            return $this->responseFormatter->error(
                'Not authenticated',
                'AUTHENTICATION_REQUIRED',
                Response::HTTP_UNAUTHORIZED
            );
        }

        $userResponse = UserResponse::fromUser($user);

        return $this->responseFormatter->success($userResponse->toArray());
    }
}
