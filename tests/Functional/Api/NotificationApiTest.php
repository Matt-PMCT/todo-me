<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Notification;
use App\Entity\User;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class NotificationApiTest extends ApiTestCase
{
    // ========================================
    // Preferences Tests
    // ========================================

    public function testGetPreferencesReturnsDefaultSettings(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/notifications/preferences');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('preferences', $data['data']);
        $preferences = $data['data']['preferences'];

        $this->assertArrayHasKey('emailEnabled', $preferences);
        $this->assertArrayHasKey('pushEnabled', $preferences);
        $this->assertArrayHasKey('taskDueSoon', $preferences);
        $this->assertArrayHasKey('taskOverdue', $preferences);
        $this->assertArrayHasKey('taskDueToday', $preferences);
    }

    public function testUpdatePreferences(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'PATCH', '/api/v1/notifications/preferences', [
            'emailEnabled' => false,
            'pushEnabled' => true,
            'quietHoursEnabled' => true,
            'quietHoursStart' => '22:00',
            'quietHoursEnd' => '07:00',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $preferences = $data['data']['preferences'];
        $this->assertFalse($preferences['emailEnabled']);
        $this->assertTrue($preferences['pushEnabled']);
        $this->assertTrue($preferences['quietHoursEnabled']);
        $this->assertEquals('22:00', $preferences['quietHoursStart']);
        $this->assertEquals('07:00', $preferences['quietHoursEnd']);
    }

    public function testUpdatePreferencesValidatesQuietHoursFormat(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'PATCH', '/api/v1/notifications/preferences', [
            'quietHoursStart' => 'invalid',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testUpdatePreferencesValidatesDueSoonHours(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'PATCH', '/api/v1/notifications/preferences', [
            'dueSoonHours' => 72, // Invalid - not in allowed list
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    // ========================================
    // Notification List Tests
    // ========================================

    public function testListNotificationsReturnsEmptyArray(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/notifications');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('notifications', $data['data']);
        $this->assertIsArray($data['data']['notifications']);
        $this->assertEmpty($data['data']['notifications']);
    }

    public function testListNotificationsReturnsCreatedNotifications(): void
    {
        $user = $this->createUser();
        $this->createNotification($user, 'task_due_soon', 'Test notification 1');
        $this->createNotification($user, 'task_overdue', 'Test notification 2');

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/notifications');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertCount(2, $data['data']['notifications']);
    }

    public function testListNotificationsRespectsLimit(): void
    {
        $user = $this->createUser();
        for ($i = 0; $i < 10; $i++) {
            $this->createNotification($user, 'system', "Notification {$i}");
        }

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/notifications?limit=5');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertCount(5, $data['data']['notifications']);
    }

    public function testListNotificationsFiltersUnreadOnly(): void
    {
        $user = $this->createUser();
        $unread = $this->createNotification($user, 'task_due_soon', 'Unread');
        $read = $this->createNotification($user, 'task_overdue', 'Read');

        // Mark one as read
        $read->markAsRead();
        $this->entityManager->flush();

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/notifications?unreadOnly=true');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertCount(1, $data['data']['notifications']);
        $this->assertEquals($unread->getId(), $data['data']['notifications'][0]['id']);
    }

    // ========================================
    // Unread Count Tests
    // ========================================

    public function testUnreadCountReturnsZeroWhenEmpty(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/notifications/unread-count');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertEquals(0, $data['data']['count']);
    }

    public function testUnreadCountReturnsCorrectCount(): void
    {
        $user = $this->createUser();
        $this->createNotification($user, 'task_due_soon', 'Unread 1');
        $this->createNotification($user, 'task_overdue', 'Unread 2');
        $read = $this->createNotification($user, 'system', 'Read');
        $read->markAsRead();
        $this->entityManager->flush();

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/notifications/unread-count');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertEquals(2, $data['data']['count']);
    }

    // ========================================
    // Mark As Read Tests
    // ========================================

    public function testMarkAsRead(): void
    {
        $user = $this->createUser();
        $notification = $this->createNotification($user, 'task_due_soon', 'Test');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            "/api/v1/notifications/{$notification->getId()}/read"
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertTrue($data['data']['notification']['isRead']);
        $this->assertNotNull($data['data']['notification']['readAt']);
    }

    public function testMarkAsReadNotFound(): void
    {
        $user = $this->createUser();

        // Use a valid UUID format that doesn't exist
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/notifications/00000000-0000-0000-0000-000000000000/read'
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
        $this->assertErrorCode($response, 'NOTIFICATION_NOT_FOUND');
    }

    public function testMarkAsReadCannotAccessOtherUserNotification(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');
        $notification = $this->createNotification($user1, 'task_due_soon', 'Test');

        $response = $this->authenticatedApiRequest(
            $user2,
            'POST',
            "/api/v1/notifications/{$notification->getId()}/read"
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    // ========================================
    // Mark All As Read Tests
    // ========================================

    public function testMarkAllAsRead(): void
    {
        $user = $this->createUser();
        $this->createNotification($user, 'task_due_soon', 'Test 1');
        $this->createNotification($user, 'task_overdue', 'Test 2');
        $this->createNotification($user, 'system', 'Test 3');

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/notifications/read-all');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertEquals(3, $data['data']['markedAsRead']);
    }

    // ========================================
    // Authentication Tests
    // ========================================

    public function testPreferencesRequiresAuthentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/notifications/preferences');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testListRequiresAuthentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/notifications');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createNotification(User $user, string $type, string $title): Notification
    {
        $notification = new Notification();
        $notification->setOwner($user)
            ->setType($type)
            ->setTitle($title)
            ->setMessage('Test message');

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }
}
