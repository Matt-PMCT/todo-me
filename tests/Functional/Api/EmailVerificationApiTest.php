<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;

final class EmailVerificationApiTest extends ApiTestCase
{
    public function testVerifyEmailWithInvalidToken(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/verify-email/invalid-token-123');

        $this->assertResponseStatusCodeSame(422);
    }

    public function testResendVerificationRequiresAuth(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/resend-verification');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testResendVerificationForVerifiedUser(): void
    {
        // Create a user and mark as verified
        $user = $this->createUser();
        $user->setEmailVerified(true);
        $this->entityManager->flush();

        // User is already verified, should fail
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/auth/resend-verification'
        );

        $this->assertResponseStatusCodeSame(422);
    }
}
