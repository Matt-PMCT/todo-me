<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the Auth API endpoints.
 *
 * Tests:
 * - User registration (success, duplicate email, invalid email, weak password)
 * - Login (success, wrong password, non-existent user, rate limiting)
 * - Token revocation (success, already revoked)
 * - /me endpoint (authenticated, unauthenticated)
 * - Both Bearer token and X-API-Key authentication methods
 */
class AuthApiTest extends ApiTestCase
{
    // ========================================
    // Registration Tests
    // ========================================

    public function testRegisterSuccess(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/register', [
            'email' => 'newuser@example.com',
            'password' => 'SecurePassword123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('user', $data['data']);
        $this->assertArrayHasKey('token', $data['data']);
        $this->assertEquals('newuser@example.com', $data['data']['user']['email']);
        $this->assertNotEmpty($data['data']['token']);
    }

    public function testRegisterDuplicateEmail(): void
    {
        // Create an existing user
        $this->createUser('existing@example.com', 'Password123');

        $response = $this->apiRequest('POST', '/api/v1/auth/register', [
            'email' => 'existing@example.com',
            'password' => 'AnotherPassword123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, 'USER_EXISTS');
    }

    public function testRegisterInvalidEmail(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/register', [
            'email' => 'not-a-valid-email',
            'password' => 'SecurePassword123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');

        $error = $this->getResponseError($response);
        $this->assertArrayHasKey('details', $error);
        $this->assertArrayHasKey('fields', $error['details']);
        $this->assertArrayHasKey('email', $error['details']['fields']);
    }

    public function testRegisterWeakPassword(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/register', [
            'email' => 'newuser@example.com',
            'password' => 'short',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');

        $error = $this->getResponseError($response);
        $this->assertArrayHasKey('details', $error);
        $this->assertArrayHasKey('fields', $error['details']);
        $this->assertArrayHasKey('password', $error['details']['fields']);
    }

    public function testRegisterMissingFields(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/register', []);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');

        $error = $this->getResponseError($response);
        $this->assertArrayHasKey('details', $error);
        $this->assertArrayHasKey('fields', $error['details']);
    }

    public function testRegisterEmptyPassword(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/register', [
            'email' => 'newuser@example.com',
            'password' => '',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    // ========================================
    // Login Tests
    // ========================================

    public function testLoginSuccess(): void
    {
        $user = $this->createUser('logintest@example.com', 'TestPassword123');

        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'logintest@example.com',
            'password' => 'TestPassword123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('token', $data['data']);
        $this->assertNotEmpty($data['data']['token']);
    }

    public function testLoginWrongPassword(): void
    {
        $this->createUser('wrongpass@example.com', 'CorrectPassword123');

        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'wrongpass@example.com',
            'password' => 'WrongPassword123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
        $this->assertErrorCode($response, 'INVALID_CREDENTIALS');
    }

    public function testLoginNonExistentUser(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'nonexistent@example.com',
            'password' => 'SomePassword123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
        $this->assertErrorCode($response, 'INVALID_CREDENTIALS');
    }

    public function testLoginMissingFields(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/token', []);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testLoginInvalidEmailFormat(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'not-an-email',
            'password' => 'SomePassword123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    // ========================================
    // Token Revocation Tests
    // ========================================

    public function testRevokeTokenSuccess(): void
    {
        $user = $this->createUser('revoke@example.com', 'Password123');
        $originalToken = $user->getApiToken();

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/auth/revoke'
        );

        $this->assertResponseStatusCode(Response::HTTP_NO_CONTENT, $response);

        // Verify the token was revoked
        $this->entityManager->refresh($user);
        $this->assertNull($user->getApiToken());
    }

    public function testRevokeTokenUnauthenticated(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/revoke');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testRevokeTokenInvalidToken(): void
    {
        $response = $this->apiRequest(
            'POST',
            '/api/v1/auth/revoke',
            null,
            ['Authorization' => 'Bearer invalid-token-12345']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // /me Endpoint Tests
    // ========================================

    public function testMeAuthenticated(): void
    {
        $user = $this->createUser('metest@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/auth/me'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayHasKey('email', $data['data']);
        $this->assertEquals('metest@example.com', $data['data']['email']);
        $this->assertEquals($user->getId(), $data['data']['id']);
    }

    public function testMeUnauthenticated(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/auth/me');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testMeInvalidToken(): void
    {
        $response = $this->apiRequest(
            'GET',
            '/api/v1/auth/me',
            null,
            ['Authorization' => 'Bearer invalid-token-12345']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Authentication Methods Tests
    // ========================================

    public function testBearerTokenAuthentication(): void
    {
        $user = $this->createUser('bearer@example.com', 'Password123');

        $response = $this->apiRequest(
            'GET',
            '/api/v1/auth/me',
            null,
            ['Authorization' => 'Bearer ' . $user->getApiToken()]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);
        $this->assertEquals('bearer@example.com', $data['email']);
    }

    public function testXApiKeyAuthentication(): void
    {
        $user = $this->createUser('apikey@example.com', 'Password123');

        $response = $this->apiRequest(
            'GET',
            '/api/v1/auth/me',
            null,
            ['X-API-Key' => $user->getApiToken()]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);
        $this->assertEquals('apikey@example.com', $data['email']);
    }

    public function testBearerTokenTakesPrecedenceOverXApiKey(): void
    {
        $user1 = $this->createUser('user1@example.com', 'Password123');
        $user2 = $this->createUser('user2@example.com', 'Password123');

        // Send both tokens, Bearer should take precedence
        $response = $this->apiRequest(
            'GET',
            '/api/v1/auth/me',
            null,
            [
                'Authorization' => 'Bearer ' . $user1->getApiToken(),
                'X-API-Key' => $user2->getApiToken(),
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);
        // Bearer token (user1) should take precedence
        $this->assertEquals('user1@example.com', $data['email']);
    }

    public function testEmptyBearerToken(): void
    {
        $response = $this->apiRequest(
            'GET',
            '/api/v1/auth/me',
            null,
            ['Authorization' => 'Bearer ']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testMalformedAuthorizationHeader(): void
    {
        $user = $this->createUser('malformed@example.com', 'Password123');

        // Missing "Bearer " prefix
        $response = $this->apiRequest(
            'GET',
            '/api/v1/auth/me',
            null,
            ['Authorization' => $user->getApiToken()]
        );

        // Without Bearer prefix, it should fall back to checking X-API-Key
        // Since X-API-Key is not set, it should fail
        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Response Structure Tests
    // ========================================

    public function testResponseStructureOnSuccess(): void
    {
        $user = $this->createUser('structure@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/auth/me'
        );

        $json = $this->assertJsonResponse($response);

        // Verify standard response structure
        $this->assertArrayHasKey('success', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('error', $json);
        $this->assertArrayHasKey('meta', $json);

        $this->assertTrue($json['success']);
        $this->assertNotNull($json['data']);
        $this->assertNull($json['error']);

        // Verify meta structure
        $this->assertArrayHasKey('timestamp', $json['meta']);
    }

    public function testResponseStructureOnError(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'nonexistent@example.com',
            'password' => 'WrongPassword',
        ]);

        $json = $this->assertJsonResponse($response);

        // Verify standard response structure
        $this->assertArrayHasKey('success', $json);
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('error', $json);
        $this->assertArrayHasKey('meta', $json);

        $this->assertFalse($json['success']);
        $this->assertNull($json['data']);
        $this->assertNotNull($json['error']);

        // Verify error structure
        $this->assertArrayHasKey('code', $json['error']);
        $this->assertArrayHasKey('message', $json['error']);
    }

    // ========================================
    // Edge Cases
    // ========================================

    public function testRegisterWithVeryLongEmail(): void
    {
        $longEmail = str_repeat('a', 200) . '@example.com';

        $response = $this->apiRequest('POST', '/api/v1/auth/register', [
            'email' => $longEmail,
            'password' => 'SecurePassword123',
        ]);

        // Should fail validation (email too long or invalid)
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_UNPROCESSABLE_ENTITY,
            Response::HTTP_BAD_REQUEST,
        ]);
    }

    public function testLoginWithCaseSensitiveEmail(): void
    {
        $this->createUser('CaseSensitive@Example.com', 'Password123');

        // Try logging in with different case
        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'casesensitive@example.com',
            'password' => 'Password123',
        ]);

        // Behavior depends on implementation - either works or returns unauthorized
        // We just verify it doesn't cause a server error
        $this->assertNotEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    public function testRegisterWithUnicodeEmail(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/register', [
            'email' => 'test@example.com',
            'password' => 'Password123',
        ]);

        // Should succeed with valid unicode email
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_CREATED,
            Response::HTTP_UNPROCESSABLE_ENTITY, // If unicode not supported
        ]);
    }

    public function testTokenIsRegeneratedOnLogin(): void
    {
        $user = $this->createUser('regenerate@example.com', 'Password123');
        $originalToken = $user->getApiToken();

        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'regenerate@example.com',
            'password' => 'Password123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);
        $newToken = $data['token'];

        // Token should be regenerated
        $this->assertNotEquals($originalToken, $newToken);
    }

    // ========================================
    // Token Expiration Tests
    // ========================================

    public function testLoginReturnsExpiresAt(): void
    {
        $this->createUser('expiry@example.com', 'Password123');

        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'expiry@example.com',
            'password' => 'Password123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('token', $data['data']);
        $this->assertArrayHasKey('expiresAt', $data['data']);
        $this->assertNotNull($data['data']['expiresAt']);
    }

    public function testRegisterReturnsExpiresAt(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/register', [
            'email' => 'newexpiry@example.com',
            'password' => 'SecurePassword123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('expiresAt', $data['data']);
        $this->assertNotNull($data['data']['expiresAt']);
    }

    public function testExpiredTokenReturnsUnauthorized(): void
    {
        $user = $this->createUser('expired@example.com', 'Password123');

        // Manually expire the token
        $user->setApiTokenExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->entityManager->flush();

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/auth/me'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testExpiredTokenErrorMessage(): void
    {
        $user = $this->createUser('expiredmsg@example.com', 'Password123');

        // Manually expire the token
        $user->setApiTokenExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->entityManager->flush();

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/auth/me'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);

        // The API returns a generic message for security (prevents token enumeration)
        $json = $this->assertJsonResponse($response);
        $this->assertNotNull($json['error']['message']);
    }

    // ========================================
    // Token Refresh Tests
    // ========================================

    public function testRefreshTokenSuccess(): void
    {
        $user = $this->createUser('refresh@example.com', 'Password123');
        $originalToken = $user->getApiToken();

        $response = $this->apiRequest(
            'POST',
            '/api/v1/auth/refresh',
            null,
            ['Authorization' => 'Bearer ' . $originalToken]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('token', $data['data']);
        $this->assertArrayHasKey('expiresAt', $data['data']);
        $this->assertNotEquals($originalToken, $data['data']['token']);
    }

    public function testRefreshTokenWithXApiKey(): void
    {
        $user = $this->createUser('refreshkey@example.com', 'Password123');
        $originalToken = $user->getApiToken();

        $response = $this->apiRequest(
            'POST',
            '/api/v1/auth/refresh',
            null,
            ['X-API-Key' => $originalToken]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->assertSuccessResponse($response);
        $this->assertNotEquals($originalToken, $data['data']['token']);
    }

    public function testRefreshTokenWithExpiredToken(): void
    {
        $user = $this->createUser('refreshexpired@example.com', 'Password123');
        $originalToken = $user->getApiToken();

        // Manually expire the token (but within the 7-day refresh window)
        $user->setApiTokenExpiresAt(new \DateTimeImmutable('-1 hour'));
        $this->entityManager->flush();

        $response = $this->apiRequest(
            'POST',
            '/api/v1/auth/refresh',
            null,
            ['Authorization' => 'Bearer ' . $originalToken]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->assertSuccessResponse($response);
        $this->assertNotEquals($originalToken, $data['data']['token']);
    }

    public function testRefreshTokenWithoutToken(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/refresh');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
        $this->assertErrorCode($response, 'TOKEN_REQUIRED');
    }

    public function testRefreshTokenWithInvalidToken(): void
    {
        $response = $this->apiRequest(
            'POST',
            '/api/v1/auth/refresh',
            null,
            ['Authorization' => 'Bearer invalid-token-12345']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
        $this->assertErrorCode($response, 'INVALID_TOKEN');
    }

    public function testRefreshTokenOutsideWindow(): void
    {
        $user = $this->createUser('refreshold@example.com', 'Password123');
        $originalToken = $user->getApiToken();

        // Manually set token to be expired for more than 7 days
        $user->setApiTokenExpiresAt(new \DateTimeImmutable('-8 days'));
        $this->entityManager->flush();

        $response = $this->apiRequest(
            'POST',
            '/api/v1/auth/refresh',
            null,
            ['Authorization' => 'Bearer ' . $originalToken]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
        $this->assertErrorCode($response, 'TOKEN_REFRESH_EXPIRED');
    }
}
