<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\TwoFactorSetupResponse;
use App\DTO\TwoFactorStatusResponse;
use App\DTO\TwoFactorVerifyRequest;
use App\Entity\User;
use App\Service\ResponseFormatter;
use App\Service\TwoFactorRecoveryService;
use App\Service\TwoFactorService;
use App\Service\UserService;
use App\Service\ValidationHelper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Two-Factor Authentication', description: 'Two-factor authentication setup and management')]
#[Route('/api/v1/2fa', name: 'api_2fa_')]
final class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly TwoFactorRecoveryService $twoFactorRecoveryService,
        private readonly UserService $userService,
        private readonly ResponseFormatter $responseFormatter,
        private readonly ValidationHelper $validationHelper,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Get 2FA status for the current user.
     */
    #[Route('/status', name: 'status', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get 2FA status',
        description: 'Returns the two-factor authentication status for the authenticated user',
        responses: [
            new OA\Response(
                response: 200,
                description: '2FA status',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function status(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $status = $this->twoFactorService->getStatus($user);
        $response = TwoFactorStatusResponse::fromArray($status);

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Initialize 2FA setup.
     */
    #[Route('/setup', name: 'setup', methods: ['POST'])]
    #[OA\Post(
        summary: 'Initialize 2FA setup',
        description: 'Generates a new TOTP secret and QR code for 2FA setup',
        responses: [
            new OA\Response(
                response: 200,
                description: '2FA setup data including QR code URI',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 400, description: '2FA already enabled'),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function setup(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isTwoFactorEnabled()) {
            return $this->responseFormatter->error(
                'Two-factor authentication is already enabled',
                '2FA_ALREADY_ENABLED',
                Response::HTTP_BAD_REQUEST
            );
        }

        $setupData = $this->twoFactorService->initializeSetup($user);
        $response = new TwoFactorSetupResponse(
            setupToken: $setupData['setupToken'],
            secret: $setupData['secret'],
            qrCodeUri: $setupData['qrCodeUri'],
            expiresIn: $setupData['expiresIn'],
        );

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Complete 2FA setup by verifying a TOTP code.
     */
    #[Route('/setup/verify', name: 'setup_verify', methods: ['POST'])]
    #[OA\Post(
        summary: 'Verify 2FA setup',
        description: 'Completes 2FA setup by verifying a TOTP code and returns backup codes',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['setupToken', 'code'],
                properties: [
                    new OA\Property(property: 'setupToken', type: 'string', description: 'Setup token from /setup endpoint'),
                    new OA\Property(property: 'code', type: 'string', description: '6-digit TOTP code', example: '123456'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '2FA enabled with backup codes',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 400, description: 'Invalid setup token or code'),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function setupVerify(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isTwoFactorEnabled()) {
            return $this->responseFormatter->error(
                'Two-factor authentication is already enabled',
                '2FA_ALREADY_ENABLED',
                Response::HTTP_BAD_REQUEST
            );
        }

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = TwoFactorVerifyRequest::fromArray($data);

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

        $result = $this->twoFactorService->completeSetup($user, $dto->setupToken, $dto->code);
        if ($result === null) {
            return $this->responseFormatter->error(
                'Invalid setup token or code',
                '2FA_SETUP_FAILED',
                Response::HTTP_BAD_REQUEST
            );
        }

        return $this->responseFormatter->success([
            'enabled' => $result['enabled'],
            'backupCodes' => $result['backupCodes'],
            'message' => 'Two-factor authentication has been enabled. Please save your backup codes securely.',
        ]);
    }

    /**
     * Disable 2FA for the current user.
     */
    #[Route('/disable', name: 'disable', methods: ['POST'])]
    #[OA\Post(
        summary: 'Disable 2FA',
        description: 'Disables two-factor authentication for the authenticated user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [
                    new OA\Property(property: 'password', type: 'string', description: 'Current account password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '2FA disabled',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 400, description: '2FA not enabled or invalid password'),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 422, description: 'Password required'),
        ]
    )]
    public function disable(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isTwoFactorEnabled()) {
            return $this->responseFormatter->error(
                'Two-factor authentication is not enabled',
                '2FA_NOT_ENABLED',
                Response::HTTP_BAD_REQUEST
            );
        }

        $data = $this->validationHelper->decodeJsonBody($request);
        $password = (string) ($data['password'] ?? '');

        if ($password === '') {
            return $this->responseFormatter->error(
                'Password is required',
                'PASSWORD_REQUIRED',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (!$this->userService->validatePassword($user, $password)) {
            return $this->responseFormatter->error(
                'Invalid password',
                'INVALID_PASSWORD',
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->twoFactorService->disable($user);

        return $this->responseFormatter->success([
            'message' => 'Two-factor authentication has been disabled',
        ]);
    }

    /**
     * Regenerate backup codes.
     */
    #[Route('/backup-codes', name: 'backup_codes', methods: ['POST'])]
    #[OA\Post(
        summary: 'Regenerate backup codes',
        description: 'Generates new backup codes, invalidating any previous codes',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [
                    new OA\Property(property: 'password', type: 'string', description: 'Current account password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'New backup codes generated',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 400, description: '2FA not enabled or invalid password'),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 422, description: 'Password required'),
        ]
    )]
    public function regenerateBackupCodes(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isTwoFactorEnabled()) {
            return $this->responseFormatter->error(
                'Two-factor authentication is not enabled',
                '2FA_NOT_ENABLED',
                Response::HTTP_BAD_REQUEST
            );
        }

        $data = $this->validationHelper->decodeJsonBody($request);
        $password = (string) ($data['password'] ?? '');

        if ($password === '') {
            return $this->responseFormatter->error(
                'Password is required',
                'PASSWORD_REQUIRED',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if (!$this->userService->validatePassword($user, $password)) {
            return $this->responseFormatter->error(
                'Invalid password',
                'INVALID_PASSWORD',
                Response::HTTP_BAD_REQUEST
            );
        }

        $backupCodes = $this->twoFactorService->regenerateBackupCodes($user);

        return $this->responseFormatter->success([
            'backupCodes' => $backupCodes,
            'message' => 'Backup codes have been regenerated. Please save them securely.',
        ]);
    }

    /**
     * Request 2FA recovery email.
     *
     * Always returns success to prevent user enumeration.
     */
    #[Route('/recovery/request', name: 'recovery_request', methods: ['POST'])]
    #[OA\Post(
        summary: 'Request 2FA recovery',
        description: 'Sends a 2FA recovery email to the specified address (always returns success to prevent enumeration)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', description: 'Account email address'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Recovery email sent (if account exists)',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 422, description: 'Email required'),
        ]
    )]
    public function recoveryRequest(Request $request): JsonResponse
    {
        $data = $this->validationHelper->decodeJsonBody($request);
        $email = (string) ($data['email'] ?? '');

        if ($email === '') {
            return $this->responseFormatter->error(
                'Email is required',
                'EMAIL_REQUIRED',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Always succeeds to prevent user enumeration
        $this->twoFactorRecoveryService->requestRecovery($email);

        return $this->responseFormatter->success([
            'message' => 'If an account exists with 2FA enabled, a recovery email has been sent.',
        ]);
    }

    /**
     * Complete 2FA recovery by disabling 2FA.
     */
    #[Route('/recovery/complete', name: 'recovery_complete', methods: ['POST'])]
    #[OA\Post(
        summary: 'Complete 2FA recovery',
        description: 'Completes 2FA recovery by disabling 2FA using the recovery token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', description: 'Recovery token from email'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: '2FA disabled via recovery',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 400, description: 'Invalid or expired recovery token'),
            new OA\Response(response: 422, description: 'Token required'),
        ]
    )]
    public function recoveryComplete(Request $request): JsonResponse
    {
        $data = $this->validationHelper->decodeJsonBody($request);
        $token = (string) ($data['token'] ?? '');

        if ($token === '') {
            return $this->responseFormatter->error(
                'Token is required',
                'TOKEN_REQUIRED',
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $success = $this->twoFactorRecoveryService->completeRecovery($token);
        if (!$success) {
            return $this->responseFormatter->error(
                'Invalid or expired recovery token',
                'INVALID_RECOVERY_TOKEN',
                Response::HTTP_BAD_REQUEST
            );
        }

        return $this->responseFormatter->success([
            'message' => 'Two-factor authentication has been disabled. Please log in again.',
        ]);
    }
}
