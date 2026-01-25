<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendPushNotification;
use App\Repository\UserRepository;
use App\Service\PushNotificationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendPushNotificationHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PushNotificationService $pushNotificationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendPushNotification $message): void
    {
        $user = $this->userRepository->find($message->getUserId());

        if ($user === null) {
            $this->logger->warning('User not found for push notification', [
                'userId' => $message->getUserId(),
                'notificationId' => $message->getNotificationId(),
            ]);

            return;
        }

        // Check if user is in quiet hours
        if ($user->isInQuietHours()) {
            $this->logger->debug('Skipping push notification due to quiet hours', [
                'userId' => $user->getId(),
                'notificationId' => $message->getNotificationId(),
            ]);

            return;
        }

        // Check if push is configured
        if (!$this->pushNotificationService->isConfigured()) {
            $this->logger->debug('Push notifications not configured, skipping', [
                'notificationId' => $message->getNotificationId(),
            ]);

            return;
        }

        try {
            $successCount = $this->pushNotificationService->send(
                user: $user,
                title: $message->getTitle(),
                message: $message->getMessage(),
                data: array_merge($message->getData(), [
                    'notificationId' => $message->getNotificationId(),
                    'type' => $message->getType(),
                ])
            );

            $this->logger->info('Push notification sent', [
                'userId' => $user->getId(),
                'notificationId' => $message->getNotificationId(),
                'type' => $message->getType(),
                'successCount' => $successCount,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send push notification', [
                'userId' => $user->getId(),
                'notificationId' => $message->getNotificationId(),
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }
}
