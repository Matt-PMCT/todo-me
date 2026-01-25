<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the Session Management API endpoints.
 *
 * Tests:
 * - GET /api/v1/sessions - List sessions
 * - DELETE /api/v1/sessions/{id} - Revoke a session
 * - POST /api/v1/sessions/revoke-others - Revoke other sessions
 * - Ownership verification
 */
class SessionApiTest extends ApiTestCase
{
    // ========================================
    // List Sessions Tests
    // ========================================

    public function testListSessionsReturnsCurrentSession(): void
    {
        $user = $this->createUser('session-list@example.com', 'Password123');

        // Login to create a session
        $loginResponse = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'session-list@example.com',
            'password' => 'Password123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $loginResponse);
        $loginData = $this->getResponseData($loginResponse);
        $token = $loginData['token'];

        // List sessions with the new token
        $response = $this->apiRequest(
            'GET',
            '/api/v1/sessions',
            null,
            ['Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('sessions', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertGreaterThanOrEqual(1, $data['total']);

        // Verify session structure
        $sessions = $data['sessions'];
        $this->assertNotEmpty($sessions);

        $session = $sessions[0];
        $this->assertArrayHasKey('id', $session);
        $this->assertArrayHasKey('createdAt', $session);
        $this->assertArrayHasKey('lastActiveAt', $session);
    }

    public function testListSessionsIdentifiesCurrentSession(): void
    {
        $user = $this->createUser('session-current@example.com', 'Password123');

        // Login to create a session
        $loginResponse = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'session-current@example.com',
            'password' => 'Password123',
        ]);

        $loginData = $this->getResponseData($loginResponse);
        $token = $loginData['token'];

        // List sessions
        $response = $this->apiRequest(
            'GET',
            '/api/v1/sessions',
            null,
            ['Authorization' => 'Bearer ' . $token]
        );

        $data = $this->getResponseData($response);
        $sessions = $data['sessions'];

        // At least one session should be marked as current
        $currentSessions = array_filter($sessions, fn($s) => $s['isCurrent'] ?? false);
        $this->assertCount(1, $currentSessions);
    }

    public function testListSessionsUnauthenticated(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/sessions');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Revoke Session Tests
    // ========================================

    public function testRevokeOwnSession(): void
    {
        $user = $this->createUser('session-revoke@example.com', 'Password123');

        // Login to create a session
        $loginResponse = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'session-revoke@example.com',
            'password' => 'Password123',
        ]);

        $loginData = $this->getResponseData($loginResponse);
        $token = $loginData['token'];

        // Get the session ID
        $listResponse = $this->apiRequest(
            'GET',
            '/api/v1/sessions',
            null,
            ['Authorization' => 'Bearer ' . $token]
        );

        $sessions = $this->getResponseData($listResponse)['sessions'];
        $sessionId = $sessions[0]['id'];

        // Revoke the session
        $response = $this->apiRequest(
            'DELETE',
            '/api/v1/sessions/' . $sessionId,
            null,
            ['Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('message', $data);
    }

    public function testCannotRevokeAnotherUsersSession(): void
    {
        // Create user1 and login
        $user1 = $this->createUser('session-user1@example.com', 'Password123');
        $login1Response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'session-user1@example.com',
            'password' => 'Password123',
        ]);
        $token1 = $this->getResponseData($login1Response)['token'];

        // Create user2 and login
        $user2 = $this->createUser('session-user2@example.com', 'Password123');
        $login2Response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'session-user2@example.com',
            'password' => 'Password123',
        ]);
        $token2 = $this->getResponseData($login2Response)['token'];

        // Get user1's session ID
        $list1Response = $this->apiRequest(
            'GET',
            '/api/v1/sessions',
            null,
            ['Authorization' => 'Bearer ' . $token1]
        );
        $sessions1 = $this->getResponseData($list1Response)['sessions'];
        $session1Id = $sessions1[0]['id'];

        // Try to revoke user1's session using user2's token
        $response = $this->apiRequest(
            'DELETE',
            '/api/v1/sessions/' . $session1Id,
            null,
            ['Authorization' => 'Bearer ' . $token2]
        );

        // Should be forbidden or not found
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_FORBIDDEN,
            Response::HTTP_NOT_FOUND,
        ]);
    }

    public function testRevokeNonExistentSession(): void
    {
        $user = $this->createUser('session-nonexist@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/sessions/00000000-0000-0000-0000-000000000000'
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    public function testRevokeSessionUnauthenticated(): void
    {
        $response = $this->apiRequest(
            'DELETE',
            '/api/v1/sessions/00000000-0000-0000-0000-000000000000'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Revoke Other Sessions Tests
    // ========================================

    public function testRevokeOtherSessions(): void
    {
        $user = $this->createUser('session-revoke-others@example.com', 'Password123');

        // Login multiple times to create multiple sessions
        $loginResponse1 = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'session-revoke-others@example.com',
            'password' => 'Password123',
        ]);
        $token1 = $this->getResponseData($loginResponse1)['token'];

        $loginResponse2 = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'session-revoke-others@example.com',
            'password' => 'Password123',
        ]);
        $token2 = $this->getResponseData($loginResponse2)['token'];

        // Revoke other sessions using token2
        $response = $this->apiRequest(
            'POST',
            '/api/v1/sessions/revoke-others',
            null,
            ['Authorization' => 'Bearer ' . $token2]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('revokedCount', $data);
    }

    public function testRevokeOtherSessionsKeepsCurrentSession(): void
    {
        $user = $this->createUser('session-keeps-current@example.com', 'Password123');

        // Login to create sessions
        $login1 = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'session-keeps-current@example.com',
            'password' => 'Password123',
        ]);
        $token1 = $this->getResponseData($login1)['token'];

        $login2 = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'session-keeps-current@example.com',
            'password' => 'Password123',
        ]);
        $token2 = $this->getResponseData($login2)['token'];

        // Revoke others with token2
        $this->apiRequest(
            'POST',
            '/api/v1/sessions/revoke-others',
            null,
            ['Authorization' => 'Bearer ' . $token2]
        );

        // List sessions with token2 should still work
        $listResponse = $this->apiRequest(
            'GET',
            '/api/v1/sessions',
            null,
            ['Authorization' => 'Bearer ' . $token2]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $listResponse);

        $sessions = $this->getResponseData($listResponse)['sessions'];
        // Should have exactly 1 session (current one)
        $this->assertCount(1, $sessions);
    }

    public function testRevokeOtherSessionsUnauthenticated(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/sessions/revoke-others');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Session Created on Login Tests
    // ========================================

    public function testSessionCreatedOnLogin(): void
    {
        $user = $this->createUser('session-login@example.com', 'Password123');

        // Login
        $loginResponse = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'session-login@example.com',
            'password' => 'Password123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $loginResponse);
        $token = $this->getResponseData($loginResponse)['token'];

        // List sessions
        $listResponse = $this->apiRequest(
            'GET',
            '/api/v1/sessions',
            null,
            ['Authorization' => 'Bearer ' . $token]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $listResponse);

        $data = $this->getResponseData($listResponse);
        $this->assertGreaterThanOrEqual(1, $data['total']);
    }
}
