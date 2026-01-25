<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\NotificationPreferencesRequest;
use App\Entity\User;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use App\Service\ResponseFormatter;
use App\Service\ValidationHelper;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Notifications', description: 'User notification management')]
#[Route('/api/v1/notifications')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly ResponseFormatter $responseFormatter,
        private readonly NotificationService $notificationService,
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidationHelper $validationHelper,
    ) {
    }

    /**
     * Get notification preferences for the current user.
     */
    #[Route('/preferences', name: 'api_notification_preferences_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get notification preferences',
        description: 'Returns the notification preferences for the authenticated user',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification preferences',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function getPreferences(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->responseFormatter->success([
            'preferences' => $user->getNotificationSettings(),
        ]);
    }

    /**
     * Update notification preferences for the current user.
     */
    #[Route('/preferences', name: 'api_notification_preferences_update', methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Update notification preferences',
        description: 'Updates notification preferences for the authenticated user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'emailEnabled', type: 'boolean'),
                    new OA\Property(property: 'pushEnabled', type: 'boolean'),
                    new OA\Property(property: 'dailyDigestEnabled', type: 'boolean'),
                    new OA\Property(property: 'taskReminders', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Updated preferences',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updatePreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = NotificationPreferencesRequest::fromArray($data);

        $this->validationHelper->validate($dto);

        $updates = $dto->toArray();
        if (!empty($updates)) {
            $user->updateNotificationSettings($updates);
            $this->entityManager->flush();
        }

        return $this->responseFormatter->success([
            'preferences' => $user->getNotificationSettings(),
        ]);
    }

    /**
     * Get notifications for the current user.
     */
    #[Route('', name: 'api_notifications_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List notifications',
        description: 'Returns paginated notifications for the authenticated user',
        parameters: [
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 50, maximum: 100)),
            new OA\Parameter(name: 'offset', in: 'query', schema: new OA\Schema(type: 'integer', default: 0)),
            new OA\Parameter(name: 'unreadOnly', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of notifications',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $limit = min(100, max(1, $request->query->getInt('limit', 50)));
        $offset = max(0, $request->query->getInt('offset', 0));
        $unreadOnly = $request->query->getBoolean('unreadOnly', false);

        if ($unreadOnly) {
            $notifications = $this->notificationService->getUnreadNotifications($user, $limit);
        } else {
            $notifications = $this->notificationService->getNotifications($user, $limit, $offset);
        }

        return $this->responseFormatter->success([
            'notifications' => array_map(fn ($n) => [
                'id' => $n->getId(),
                'type' => $n->getType(),
                'title' => $n->getTitle(),
                'message' => $n->getMessage(),
                'data' => $n->getData(),
                'isRead' => $n->isRead(),
                'createdAt' => $n->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'readAt' => $n->getReadAt()?->format(\DateTimeInterface::ATOM),
            ], $notifications),
        ]);
    }

    /**
     * Get unread notification count.
     */
    #[Route('/unread-count', name: 'api_notifications_unread_count', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get unread count',
        description: 'Returns the number of unread notifications for the authenticated user',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Unread notification count',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function unreadCount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->responseFormatter->success([
            'count' => $this->notificationService->getUnreadCount($user),
        ]);
    }

    /**
     * Mark a notification as read.
     */
    #[Route('/{id}/read', name: 'api_notification_mark_read', methods: ['POST'])]
    #[OA\Post(
        summary: 'Mark notification as read',
        description: 'Marks a single notification as read',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notification marked as read',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 404, description: 'Notification not found'),
        ]
    )]
    public function markRead(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $notification = $this->notificationRepository->findOneByOwnerAndId($user, $id);
        if ($notification === null) {
            return $this->responseFormatter->error(
                'Notification not found',
                'NOTIFICATION_NOT_FOUND',
                Response::HTTP_NOT_FOUND
            );
        }

        $this->notificationService->markAsRead($notification);

        return $this->responseFormatter->success([
            'notification' => [
                'id' => $notification->getId(),
                'isRead' => $notification->isRead(),
                'readAt' => $notification->getReadAt()?->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    #[Route('/read-all', name: 'api_notifications_mark_all_read', methods: ['POST'])]
    #[OA\Post(
        summary: 'Mark all notifications as read',
        description: 'Marks all notifications as read for the authenticated user',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Notifications marked as read',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function markAllRead(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $count = $this->notificationService->markAllAsRead($user);

        return $this->responseFormatter->success([
            'markedAsRead' => $count,
        ]);
    }
}
