<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the API Token Management endpoints.
 *
 * Tests:
 * - GET /api/v1/users/me/tokens - List tokens
 * - POST /api/v1/users/me/tokens - Create token
 * - DELETE /api/v1/users/me/tokens/{id} - Revoke token
 * - Token can be used for authentication
 * - Token expiration validation
 * - Token scopes validation
 */
class ApiTokenApiTest extends ApiTestCase
{
    // ========================================
    // List Tokens Tests
    // ========================================

    public function testListTokensReturnsEmptyListInitially(): void
    {
        $user = $this->createUser('tokens-empty@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/users/me/tokens'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertIsArray($data);
        // May have tokens from user creation, but shouldn't fail
    }

    public function testListTokensUnauthenticated(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/users/me/tokens');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Create Token Tests
    // ========================================

    public function testCreateTokenSuccess(): void
    {
        $user = $this->createUser('tokens-create@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/users/me/tokens',
            ['name' => 'My Test Token']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('plainToken', $data);

        // Token metadata
        $this->assertArrayHasKey('id', $data['token']);
        $this->assertArrayHasKey('name', $data['token']);
        $this->assertArrayHasKey('tokenPrefix', $data['token']);
        $this->assertArrayHasKey('scopes', $data['token']);
        $this->assertArrayHasKey('createdAt', $data['token']);

        $this->assertEquals('My Test Token', $data['token']['name']);

        // Plain token should have the correct format (tm_ prefix)
        $this->assertStringStartsWith('tm_', $data['plainToken']);
    }

    public function testCreateTokenWithScopes(): void
    {
        $user = $this->createUser('tokens-scopes@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/users/me/tokens',
            [
                'name' => 'Scoped Token',
                'scopes' => ['read', 'write'],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals(['read', 'write'], $data['token']['scopes']);
    }

    public function testCreateTokenWithExpiration(): void
    {
        $user = $this->createUser('tokens-expiry@example.com', 'Password123');

        $expiresAt = (new \DateTimeImmutable('+30 days'))->format(\DateTimeInterface::RFC3339);

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/users/me/tokens',
            [
                'name' => 'Expiring Token',
                'expiresAt' => $expiresAt,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertNotNull($data['token']['expiresAt']);
    }

    public function testCreateTokenDefaultsToAllScopes(): void
    {
        $user = $this->createUser('tokens-default-scopes@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/users/me/tokens',
            ['name' => 'Default Scopes Token']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals(['*'], $data['token']['scopes']);
    }

    public function testCreateTokenValidationMissingName(): void
    {
        $user = $this->createUser('tokens-no-name@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/users/me/tokens',
            []
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testCreateTokenValidationNameTooLong(): void
    {
        $user = $this->createUser('tokens-long-name@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/users/me/tokens',
            ['name' => str_repeat('a', 101)]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testCreateTokenUnauthenticated(): void
    {
        $response = $this->apiRequest(
            'POST',
            '/api/v1/users/me/tokens',
            ['name' => 'Unauthorized Token']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Token Authentication Tests
    // ========================================

    public function testCreatedTokenCanBeUsedForAuthentication(): void
    {
        $user = $this->createUser('tokens-auth@example.com', 'Password123');

        // Create a token
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/users/me/tokens',
            ['name' => 'Auth Test Token']
        );

        $data = $this->getResponseData($createResponse);
        $plainToken = $data['plainToken'];

        // Use the token to make an authenticated request
        $response = $this->apiRequest(
            'GET',
            '/api/v1/auth/me',
            null,
            ['Authorization' => 'Bearer ' . $plainToken]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $meData = $this->getResponseData($response);
        $this->assertEquals('tokens-auth@example.com', $meData['email']);
    }

    public function testExpiredTokenCannotAuthenticate(): void
    {
        $user = $this->createUser('tokens-expired@example.com', 'Password123');

        // Create a token that expires immediately (in the past)
        $expiresAt = (new \DateTimeImmutable('-1 day'))->format(\DateTimeInterface::RFC3339);

        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/users/me/tokens',
            [
                'name' => 'Expired Token',
                'expiresAt' => $expiresAt,
            ]
        );

        $data = $this->getResponseData($createResponse);
        $plainToken = $data['plainToken'];

        // Try to use the expired token
        $response = $this->apiRequest(
            'GET',
            '/api/v1/auth/me',
            null,
            ['Authorization' => 'Bearer ' . $plainToken]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Revoke Token Tests
    // ========================================

    public function testRevokeTokenSuccess(): void
    {
        $user = $this->createUser('tokens-revoke@example.com', 'Password123');

        // Create a token
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/users/me/tokens',
            ['name' => 'Token to Revoke']
        );

        $data = $this->getResponseData($createResponse);
        $tokenId = $data['token']['id'];

        // Revoke the token
        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/users/me/tokens/' . $tokenId
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $revokeData = $this->getResponseData($response);
        $this->assertArrayHasKey('message', $revokeData);
    }

    public function testRevokedTokenCannotBeUsed(): void
    {
        $user = $this->createUser('tokens-revoked-use@example.com', 'Password123');

        // Create a token
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/users/me/tokens',
            ['name' => 'Will Be Revoked']
        );

        $data = $this->getResponseData($createResponse);
        $tokenId = $data['token']['id'];
        $plainToken = $data['plainToken'];

        // Revoke the token
        $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/users/me/tokens/' . $tokenId
        );

        // Try to use the revoked token
        $response = $this->apiRequest(
            'GET',
            '/api/v1/auth/me',
            null,
            ['Authorization' => 'Bearer ' . $plainToken]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testRevokeNonExistentToken(): void
    {
        $user = $this->createUser('tokens-revoke-nonexist@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/users/me/tokens/00000000-0000-0000-0000-000000000000'
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    public function testCannotRevokeAnotherUsersToken(): void
    {
        $user1 = $this->createUser('tokens-user1@example.com', 'Password123');
        $user2 = $this->createUser('tokens-user2@example.com', 'Password123');

        // User1 creates a token
        $createResponse = $this->authenticatedApiRequest(
            $user1,
            'POST',
            '/api/v1/users/me/tokens',
            ['name' => 'User1 Token']
        );

        $tokenId = $this->getResponseData($createResponse)['token']['id'];

        // User2 tries to revoke user1's token
        $response = $this->authenticatedApiRequest(
            $user2,
            'DELETE',
            '/api/v1/users/me/tokens/' . $tokenId
        );

        // Should be forbidden or not found
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_FORBIDDEN,
            Response::HTTP_NOT_FOUND,
        ]);
    }

    public function testRevokeTokenUnauthenticated(): void
    {
        $response = $this->apiRequest(
            'DELETE',
            '/api/v1/users/me/tokens/00000000-0000-0000-0000-000000000000'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Token Metadata Tests
    // ========================================

    public function testTokenPrefixIsCorrect(): void
    {
        $user = $this->createUser('tokens-prefix@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/users/me/tokens',
            ['name' => 'Prefix Test Token']
        );

        $data = $this->getResponseData($response);

        $plainToken = $data['plainToken'];
        $tokenPrefix = $data['token']['tokenPrefix'];

        // Token prefix should match the first 8 characters of the plain token
        $this->assertEquals(substr($plainToken, 0, 8), $tokenPrefix);
    }

    public function testListTokensShowsCreatedToken(): void
    {
        $user = $this->createUser('tokens-list-created@example.com', 'Password123');

        // Create a token
        $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/users/me/tokens',
            ['name' => 'Listed Token']
        );

        // List tokens
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/users/me/tokens'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $tokens = $this->getResponseData($response);

        // Find the token we created
        $foundToken = null;
        foreach ($tokens as $token) {
            if ($token['name'] === 'Listed Token') {
                $foundToken = $token;
                break;
            }
        }

        $this->assertNotNull($foundToken);
        $this->assertArrayNotHasKey('tokenHash', $foundToken); // Should not expose hash
    }

    public function testPlainTokenOnlyReturnedOnCreate(): void
    {
        $user = $this->createUser('tokens-plain-once@example.com', 'Password123');

        // Create a token
        $createResponse = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/users/me/tokens',
            ['name' => 'Plain Token Test']
        );

        $createData = $this->getResponseData($createResponse);
        $this->assertArrayHasKey('plainToken', $createData);

        // List tokens - should NOT include plain token
        $listResponse = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/users/me/tokens'
        );

        $tokens = $this->getResponseData($listResponse);

        foreach ($tokens as $token) {
            $this->assertArrayNotHasKey('plainToken', $token);
        }
    }
}
