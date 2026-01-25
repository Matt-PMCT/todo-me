<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\PushNotificationService;
use App\Service\ResponseFormatter;
use App\Service\ValidationHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/push')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class PushController extends AbstractController
{
    public function __construct(
        private readonly ResponseFormatter $responseFormatter,
        private readonly PushNotificationService $pushNotificationService,
        private readonly ValidationHelper $validationHelper,
    ) {
    }

    /**
     * Get VAPID public key for client-side subscription.
     */
    #[Route('/vapid-key', name: 'api_push_vapid_key', methods: ['GET'])]
    public function getVapidKey(): JsonResponse
    {
        if (!$this->pushNotificationService->isConfigured()) {
            return $this->responseFormatter->error(
                'Push notifications are not configured',
                'PUSH_NOT_CONFIGURED',
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        return $this->responseFormatter->success([
            'publicKey' => $this->pushNotificationService->getVapidPublicKey(),
        ]);
    }

    /**
     * Subscribe to push notifications.
     */
    #[Route('/subscribe', name: 'api_push_subscribe', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);

        // Validate required fields first
        if (empty($data['endpoint'])) {
            return $this->responseFormatter->error(
                'Endpoint is required',
                'VALIDATION_ERROR',
                Response::HTTP_BAD_REQUEST,
                ['endpoint' => 'This field is required']
            );
        }

        if (empty($data['keys']['p256dh']) || empty($data['keys']['auth'])) {
            return $this->responseFormatter->error(
                'Subscription keys are required',
                'VALIDATION_ERROR',
                Response::HTTP_BAD_REQUEST,
                ['keys' => 'Both p256dh and auth keys are required']
            );
        }

        // Then check if push is configured
        if (!$this->pushNotificationService->isConfigured()) {
            return $this->responseFormatter->error(
                'Push notifications are not configured',
                'PUSH_NOT_CONFIGURED',
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        $subscription = $this->pushNotificationService->subscribe(
            user: $user,
            endpoint: $data['endpoint'],
            publicKey: $data['keys']['p256dh'],
            authToken: $data['keys']['auth'],
            userAgent: $request->headers->get('User-Agent')
        );

        return $this->responseFormatter->success([
            'subscriptionId' => $subscription->getId(),
            'message' => 'Successfully subscribed to push notifications',
        ]);
    }

    /**
     * Unsubscribe from push notifications.
     */
    #[Route('/unsubscribe', name: 'api_push_unsubscribe', methods: ['POST'])]
    public function unsubscribe(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);

        if (empty($data['endpoint'])) {
            return $this->responseFormatter->error(
                'Endpoint is required',
                'VALIDATION_ERROR',
                Response::HTTP_BAD_REQUEST,
                ['endpoint' => 'This field is required']
            );
        }

        $removed = $this->pushNotificationService->unsubscribe($user, $data['endpoint']);

        if (!$removed) {
            return $this->responseFormatter->error(
                'Subscription not found',
                'SUBSCRIPTION_NOT_FOUND',
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->responseFormatter->success([
            'message' => 'Successfully unsubscribed from push notifications',
        ]);
    }
}
