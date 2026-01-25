<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendEmailNotification;
use App\Repository\UserRepository;
use App\Service\EmailService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendEmailNotificationHandler
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendEmailNotification $message): void
    {
        $user = $this->userRepository->find($message->getUserId());

        if ($user === null) {
            $this->logger->warning('User not found for email notification', [
                'userId' => $message->getUserId(),
                'notificationId' => $message->getNotificationId(),
            ]);

            return;
        }

        // Check if user is in quiet hours
        if ($user->isInQuietHours()) {
            $this->logger->debug('Skipping email notification due to quiet hours', [
                'userId' => $user->getId(),
                'notificationId' => $message->getNotificationId(),
            ]);

            return;
        }

        try {
            $this->emailService->sendNotificationEmail(
                user: $user,
                type: $message->getType(),
                title: $message->getTitle(),
                message: $message->getMessage(),
                data: $message->getData()
            );

            $this->logger->info('Email notification sent', [
                'userId' => $user->getId(),
                'notificationId' => $message->getNotificationId(),
                'type' => $message->getType(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send email notification', [
                'userId' => $user->getId(),
                'notificationId' => $message->getNotificationId(),
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }
}
