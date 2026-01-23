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
        $data = json_decode($request->getContent(), true) ?? [];

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
                'email' => $registerRequest->email,
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
                'email' => $registerRequest->email,
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
                'email' => $user->getEmail(),
            ]);

            $userResponse = UserResponse::fromUser($user);
            $tokenResponse = new TokenResponse($user->getApiToken() ?? '');

            return $this->responseFormatter->created([
                'user' => $userResponse->toArray(),
                'token' => $tokenResponse->token,
            ]);
        } catch (\Exception $e) {
            $this->apiLogger->logError($e, [
                'email' => $registerRequest->email,
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
        $data = json_decode($request->getContent(), true) ?? [];

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
                'email' => $loginRequest->email,
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
                'email' => $loginRequest->email,
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
                'email' => $loginRequest->email,
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
            'email' => $user->getEmail(),
        ]);

        $tokenResponse = new TokenResponse($apiToken);

        return $this->responseFormatter->success($tokenResponse->toArray());
    }

    /**
     * Revoke the current token (logout).
     *
     * @return JsonResponse
     */
    #[Route('/token', name: 'revoke_token', methods: ['DELETE'])]
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
            'email' => $user->getEmail(),
        ]);

        return $this->responseFormatter->noContent();
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
