<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;

final class PasswordResetApiTest extends ApiTestCase
{
    public function testForgotPasswordReturnsSuccessForValidEmail(): void
    {
        $user = $this->createUser();

        $response = $this->apiRequest('POST', '/api/v1/auth/forgot-password', [
            'email' => $user->getEmail(),
        ]);

        $this->assertSuccessResponse($response);
        $data = $this->getResponseData($response);
        $this->assertStringContainsString('reset link', $data['message']);
    }

    public function testForgotPasswordReturnsSuccessForUnknownEmail(): void
    {
        // Should return success to prevent email enumeration
        $response = $this->apiRequest('POST', '/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $this->assertSuccessResponse($response);
    }

    public function testForgotPasswordValidationError(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testValidateTokenEndpointWithInvalidToken(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/reset-password/validate', [
            'token' => 'invalid-token-12345',
        ]);

        $this->assertSuccessResponse($response);
        $data = $this->getResponseData($response);
        $this->assertFalse($data['valid']);
    }

    public function testResetPasswordWithInvalidToken(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'password' => 'NewSecurePass123',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testResetPasswordWithWeakPassword(): void
    {
        // Even with invalid token, weak password should fail validation first
        $response = $this->apiRequest('POST', '/api/v1/auth/reset-password', [
            'token' => 'sometoken',
            'password' => 'weak',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testPasswordRequirementsEndpoint(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/auth/password-requirements');

        $this->assertSuccessResponse($response);
        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('minLength', $data);
        $this->assertArrayHasKey('requireUppercase', $data);
        $this->assertEquals(12, $data['minLength']);
    }
}
