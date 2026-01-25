<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Log\LoggerInterface;

final class PushNotificationService
{
    private ?WebPush $webPush = null;

    public function __construct(
        private readonly PushSubscriptionRepository $subscriptionRepository,
        private readonly LoggerInterface $logger,
        private readonly string $vapidPublicKey,
        private readonly string $vapidPrivateKey,
        private readonly string $vapidSubject,
    ) {
    }

    /**
     * Send a push notification to all user's subscriptions.
     *
     * @param array<string, mixed> $data
     * @return int Number of successful deliveries
     */
    public function send(User $user, string $title, ?string $message = null, array $data = []): int
    {
        $subscriptions = $this->subscriptionRepository->findByOwner($user);

        if (empty($subscriptions)) {
            $this->logger->debug('No push subscriptions for user', ['userId' => $user->getId()]);

            return 0;
        }

        $webPush = $this->getWebPush();
        if ($webPush === null) {
            $this->logger->warning('WebPush not configured - VAPID keys missing');

            return 0;
        }

        $payload = json_encode([
            'title' => $title,
            'body' => $message,
            'data' => $data,
            'timestamp' => time(),
        ]);

        $successCount = 0;
        $failedSubscriptions = [];

        foreach ($subscriptions as $subscription) {
            try {
                $pushSubscription = Subscription::create([
                    'endpoint' => $subscription->getEndpoint(),
                    'keys' => [
                        'p256dh' => $subscription->getPublicKey(),
                        'auth' => $subscription->getAuthToken(),
                    ],
                ]);

                $webPush->queueNotification($pushSubscription, $payload);
            } catch (\Exception $e) {
                $this->logger->error('Failed to queue push notification', [
                    'subscriptionId' => $subscription->getId(),
                    'error' => $e->getMessage(),
                ]);
                $failedSubscriptions[] = $subscription;
            }
        }

        // Send all queued notifications
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();

            if ($report->isSuccess()) {
                $successCount++;
                // Update last used timestamp
                $subscription = $this->subscriptionRepository->findByEndpoint($endpoint);
                if ($subscription !== null) {
                    $subscription->markAsUsed();
                }
            } else {
                $this->logger->warning('Push notification failed', [
                    'endpoint' => $endpoint,
                    'reason' => $report->getReason(),
                ]);

                // If subscription is expired or invalid, remove it
                if ($report->isSubscriptionExpired()) {
                    $subscription = $this->subscriptionRepository->findByEndpoint($endpoint);
                    if ($subscription !== null) {
                        $this->subscriptionRepository->remove($subscription, true);
                        $this->logger->info('Removed expired push subscription', [
                            'subscriptionId' => $subscription->getId(),
                        ]);
                    }
                }
            }
        }

        return $successCount;
    }

    /**
     * Subscribe a user to push notifications.
     */
    public function subscribe(
        User $user,
        string $endpoint,
        string $publicKey,
        string $authToken,
        ?string $userAgent = null
    ): PushSubscription {
        // Check if subscription already exists
        $existing = $this->subscriptionRepository->findByEndpointAndOwner($endpoint, $user);

        if ($existing !== null) {
            // Update existing subscription
            $existing->setPublicKey($publicKey)
                ->setAuthToken($authToken)
                ->setUserAgent($userAgent)
                ->markAsUsed();

            $this->subscriptionRepository->save($existing, true);

            return $existing;
        }

        // Create new subscription
        $subscription = new PushSubscription();
        $subscription->setOwner($user)
            ->setEndpoint($endpoint)
            ->setPublicKey($publicKey)
            ->setAuthToken($authToken)
            ->setUserAgent($userAgent);

        $this->subscriptionRepository->save($subscription, true);

        $this->logger->info('New push subscription created', [
            'subscriptionId' => $subscription->getId(),
            'userId' => $user->getId(),
        ]);

        return $subscription;
    }

    /**
     * Unsubscribe from push notifications.
     */
    public function unsubscribe(User $user, string $endpoint): bool
    {
        $subscription = $this->subscriptionRepository->findByEndpointAndOwner($endpoint, $user);

        if ($subscription === null) {
            return false;
        }

        $this->subscriptionRepository->remove($subscription, true);

        $this->logger->info('Push subscription removed', [
            'subscriptionId' => $subscription->getId(),
            'userId' => $user->getId(),
        ]);

        return true;
    }

    /**
     * Get the VAPID public key for client-side subscription.
     */
    public function getVapidPublicKey(): string
    {
        return $this->vapidPublicKey;
    }

    /**
     * Check if push notifications are configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->vapidPublicKey) && !empty($this->vapidPrivateKey);
    }

    /**
     * Get or create the WebPush instance.
     */
    private function getWebPush(): ?WebPush
    {
        if ($this->webPush !== null) {
            return $this->webPush;
        }

        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject' => $this->vapidSubject,
                    'publicKey' => $this->vapidPublicKey,
                    'privateKey' => $this->vapidPrivateKey,
                ],
            ]);

            return $this->webPush;
        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize WebPush', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
