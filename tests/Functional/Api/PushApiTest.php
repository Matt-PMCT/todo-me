<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\PushSubscription;
use App\Entity\User;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class PushApiTest extends ApiTestCase
{
    // ========================================
    // VAPID Key Tests
    // ========================================

    public function testGetVapidKeyWhenNotConfigured(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/push/vapid-key');

        // VAPID keys are not configured in test environment
        $this->assertResponseStatusCode(Response::HTTP_SERVICE_UNAVAILABLE, $response);
        $this->assertErrorCode($response, 'PUSH_NOT_CONFIGURED');
    }

    // ========================================
    // Subscribe Tests
    // ========================================

    public function testSubscribeValidatesEndpoint(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/push/subscribe', [
            'keys' => [
                'p256dh' => 'test-key',
                'auth' => 'test-auth',
            ],
        ]);

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testSubscribeValidatesKeys(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/push/subscribe', [
            'endpoint' => 'https://push.example.com/123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testSubscribeRequiresAuthentication(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/push/subscribe', [
            'endpoint' => 'https://push.example.com/123',
            'keys' => [
                'p256dh' => 'test-key',
                'auth' => 'test-auth',
            ],
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Unsubscribe Tests
    // ========================================

    public function testUnsubscribeValidatesEndpoint(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/push/unsubscribe', []);

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testUnsubscribeNotFoundWhenNoSubscription(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/push/unsubscribe', [
            'endpoint' => 'https://push.example.com/non-existent',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
        $this->assertErrorCode($response, 'SUBSCRIPTION_NOT_FOUND');
    }

    public function testUnsubscribeRemovesSubscription(): void
    {
        $user = $this->createUser();
        $subscription = $this->createPushSubscription($user, 'https://push.example.com/to-remove');
        $subscriptionId = $subscription->getId();

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/push/unsubscribe', [
            'endpoint' => 'https://push.example.com/to-remove',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertStringContainsString('unsubscribed', $data['data']['message']);

        // Verify subscription is removed
        $this->entityManager->clear();
        $removed = $this->entityManager->find(PushSubscription::class, $subscriptionId);
        $this->assertNull($removed);
    }

    public function testUnsubscribeCannotRemoveOtherUserSubscription(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');
        $this->createPushSubscription($user1, 'https://push.example.com/user1');

        $response = $this->authenticatedApiRequest($user2, 'POST', '/api/v1/push/unsubscribe', [
            'endpoint' => 'https://push.example.com/user1',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    public function testUnsubscribeRequiresAuthentication(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/push/unsubscribe', [
            'endpoint' => 'https://push.example.com/123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createPushSubscription(User $user, string $endpoint): PushSubscription
    {
        $subscription = new PushSubscription();
        $subscription->setOwner($user)
            ->setEndpoint($endpoint)
            ->setPublicKey('test-public-key')
            ->setAuthToken('test-auth-token');

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return $subscription;
    }
}
