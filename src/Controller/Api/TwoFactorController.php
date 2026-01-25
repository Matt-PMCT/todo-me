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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
