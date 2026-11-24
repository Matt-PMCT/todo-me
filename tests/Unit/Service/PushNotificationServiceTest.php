<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Repository\PushSubscriptionRepository;
use App\Service\PushNotificationService;
use App\Tests\Unit\UnitTestCase;
use Minishlink\WebPush\VAPID;
use Psr\Log\NullLogger;

class PushNotificationServiceTest extends UnitTestCase
{
    /**
     * web-push v11 has no bundled HTTP client; it needs a discoverable PSR-18 client
     * and PSR-17 factories. This guards against the service failing at send time.
     */
    public function testSendBuildsWebPushWithDiscoveredHttpClient(): void
    {
        $keys = VAPID::createVapidKeys();

        $subscription = (new PushSubscription())
            ->setEndpoint('https://127.0.0.1:1/push')
            ->setPublicKey('BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM')
            ->setAuthToken('dGVzdC1hdXRoLXRva2Vu');

        $repository = $this->createStub(PushSubscriptionRepository::class);
        $repository->method('findByOwner')->willReturn([$subscription]);

        $service = new PushNotificationService(
            $repository,
            new NullLogger(),
            $keys['publicKey'],
            $keys['privateKey'],
            'mailto:test@example.com',
        );

        $this->assertSame(0, $service->send(new User(), 'Title', 'Body'));
    }
}
