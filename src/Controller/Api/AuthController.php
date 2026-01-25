<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\ChangePasswordRequest;
use App\DTO\ForgotPasswordRequest;
use App\DTO\LoginRequest;
use App\DTO\RegisterRequest;
use App\DTO\ResetPasswordRequest;
use App\DTO\TokenResponse;
use App\DTO\UserResponse;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Service\AccountLockoutService;
use App\Service\ApiLogger;
use App\Service\EmailService;
use App\Service\EmailVerificationService;
use App\Service\PasswordPolicyValidator;
use App\Service\PasswordResetService;
use App\Service\ResponseFormatter;
use App\Service\TwoFactorLoginService;
use App\Service\UserService;
use App\Service\ValidationHelper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Authentication', description: 'User authentication and token management')]
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
        private readonly PasswordResetService $passwordResetService,
        private readonly PasswordPolicyValidator $passwordPolicyValidator,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly EmailService $emailService,
        private readonly AccountLockoutService $accountLockoutService,
        private readonly TwoFactorLoginService $twoFactorLoginService,
    ) {
    }

    /**
     * Register a new user.
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    #[OA\Post(
        summary: 'Register a new user',
        description: 'Creates a new user account and returns an API token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8, example: 'SecurePass123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User created successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 400, description: 'User already exists'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 429, description: 'Rate limit exceeded'),
        ]
    )]
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
     */
    #[Route('/token', name: 'token', methods: ['POST'])]
    #[OA\Post(
        summary: 'Login and get API token',
        description: 'Authenticates user credentials and returns an API token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'SecurePass123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 401, description: 'Invalid credentials'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 429, description: 'Rate limit exceeded'),
        ]
    )]
    public function token(Request $request): JsonResponse
    {
        $data = $this->validationHelper->decodeJsonBody($request);

        // Check if this is a 2FA challenge verification
        $challengeToken = $data['challengeToken'] ?? null;
        if ($challengeToken !== null) {
            return $this->verify2faChallenge($data, $request);
        }

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

        // Check account lockout
        $this->accountLockoutService->checkLockout($user);

        // Validate password
        if (!$this->userService->validatePassword($user, $loginRequest->password)) {
            $this->accountLockoutService->recordFailedAttempt($user);

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

        // Check if 2FA is required
        if ($this->twoFactorLoginService->requires2fa($user)) {
            $challengeData = $this->twoFactorLoginService->createChallenge($user);

            $this->apiLogger->logInfo('2FA challenge created for login', [
                'user_id' => $user->getId(),
                'email_hash' => ApiLogger::hashEmail($user->getEmail()),
            ]);

            return $this->responseFormatter->success([
                'twoFactorRequired' => true,
                'challengeToken' => $challengeData['challengeToken'],
                'expiresIn' => $challengeData['expiresIn'],
            ]);
        }

        return $this->completeLogin($user);
    }

    /**
     * Verify 2FA challenge and complete login.
     *
     * @param array<string, mixed> $data
     */
    private function verify2faChallenge(array $data, Request $request): JsonResponse
    {
        $challengeToken = (string) ($data['challengeToken'] ?? '');
        $code = (string) ($data['code'] ?? '');

        if ($challengeToken === '' || $code === '') {
            return $this->responseFormatter->error(
                'Challenge token and code are required',
                'VALIDATION_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $user = $this->twoFactorLoginService->verifyChallenge($challengeToken, $code);
        if ($user === null) {
            $this->apiLogger->logWarning('2FA challenge verification failed', [
                'ip' => $request->getClientIp(),
            ]);

            return $this->responseFormatter->error(
                'Invalid or expired challenge token, or incorrect code',
                '2FA_VERIFICATION_FAILED',
                Response::HTTP_UNAUTHORIZED
            );
        }

        return $this->completeLogin($user);
    }

    /**
     * Complete login by generating token and recording success.
     */
    private function completeLogin(User $user): JsonResponse
    {
        $apiToken = $this->userService->generateNewApiToken($user);

        $this->accountLockoutService->recordSuccessfulLogin($user);

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
     */
    #[Route('/revoke', name: 'revoke_token', methods: ['POST'])]
    #[OA\Post(
        summary: 'Revoke API token (logout)',
        description: 'Invalidates the current API token',
        responses: [
            new OA\Response(response: 204, description: 'Token revoked successfully'),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
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
     */
    #[Route('/refresh', name: 'refresh_token', methods: ['POST'])]
    #[OA\Post(
        summary: 'Refresh API token',
        description: 'Generates a new API token. Requires a valid (but possibly expired within 7 days) token.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token refreshed successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 401, description: 'Invalid or expired token'),
        ]
    )]
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
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get current user',
        description: 'Returns information about the authenticated user',
        responses: [
            new OA\Response(
                response: 200,
                description: 'User information',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
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

    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = ForgotPasswordRequest::fromArray($data);

        $errors = $this->validator->validate($dto);
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

        $this->passwordResetService->requestReset($dto->email);

        return $this->responseFormatter->success([
            'message' => 'If an account exists with this email, a reset link has been sent.',
        ]);
    }

    #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = ResetPasswordRequest::fromArray($data);

        $errors = $this->validator->validate($dto);
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

        $this->passwordResetService->resetPassword($dto->token, $dto->password);

        return $this->responseFormatter->success([
            'message' => 'Password has been reset successfully.',
        ]);
    }

    #[Route('/reset-password/validate', name: 'validate_reset_token', methods: ['POST'])]
    public function validateResetToken(Request $request): JsonResponse
    {
        $data = $this->validationHelper->decodeJsonBody($request);
        $token = (string) ($data['token'] ?? '');

        $isValid = $this->passwordResetService->validateToken($token);

        return $this->responseFormatter->success(['valid' => $isValid]);
    }

    #[Route('/password-requirements', name: 'password_requirements', methods: ['GET'])]
    public function passwordRequirements(): JsonResponse
    {
        return $this->responseFormatter->success(
            $this->passwordPolicyValidator->getRequirements()
        );
    }

    #[Route('/me/password', name: 'change_password', methods: ['PATCH'])]
    public function changePassword(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = ChangePasswordRequest::fromArray($data);

        $errors = $this->validator->validate($dto);
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

        // Verify current password
        if (!$this->userService->validatePassword($user, $dto->currentPassword)) {
            return $this->responseFormatter->error(
                'Current password is incorrect',
                'INVALID_PASSWORD',
                Response::HTTP_BAD_REQUEST
            );
        }

        // Validate new password policy
        $policyErrors = $this->passwordPolicyValidator->validate(
            $dto->newPassword,
            $user->getEmail(),
            $user->getUsername()
        );

        if (!empty($policyErrors)) {
            return $this->responseFormatter->error(
                'Password does not meet requirements',
                'WEAK_PASSWORD',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['errors' => $policyErrors]
            );
        }

        $this->userService->changePassword($user, $dto->newPassword);
        $this->emailService->sendPasswordChangedNotification($user);

        return $this->responseFormatter->success([
            'message' => 'Password changed successfully',
        ]);
    }

    #[Route('/verify-email/{token}', name: 'verify_email', methods: ['POST'])]
    public function verifyEmail(string $token): JsonResponse
    {
        $this->emailVerificationService->verifyEmail($token);

        return $this->responseFormatter->success([
            'message' => 'Email verified successfully',
        ]);
    }

    #[Route('/resend-verification', name: 'resend_verification', methods: ['POST'])]
    public function resendVerification(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->emailVerificationService->sendVerificationEmail($user);

        return $this->responseFormatter->success([
            'message' => 'Verification email sent',
        ]);
    }
}
