<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Interface\EncryptionServiceInterface;
use App\Service\TotpService;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

/**
 * Functional tests for 2FA login flow.
 */
class TwoFactorLoginApiTest extends ApiTestCase
{
    // ========================================
    // Login with 2FA Challenge Tests
    // ========================================

    public function testLoginReturns2FAChallengeWhenEnabled(): void
    {
        $user = $this->createUser('2fa@example.com', 'Password123');
        $this->enable2faForUser($user);

        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => '2fa@example.com',
            'password' => 'Password123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertTrue($data['data']['twoFactorRequired']);
        $this->assertArrayHasKey('challengeToken', $data['data']);
        $this->assertArrayHasKey('expiresIn', $data['data']);
        $this->assertNotEmpty($data['data']['challengeToken']);
    }

    public function testLoginWithoutPrior2FADoesNotRequireChallenge(): void
    {
        $user = $this->createUser('no2fa@example.com', 'Password123');

        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'no2fa@example.com',
            'password' => 'Password123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('token', $data['data']);
        $this->assertArrayNotHasKey('twoFactorRequired', $data['data']);
    }

    // ========================================
    // 2FA Challenge Verification Tests
    // ========================================

    public function testVerify2FAChallengeWithValidTOTPCode(): void
    {
        $user = $this->createUser('2fa@example.com', 'Password123');
        $secret = $this->enable2faForUser($user);

        // Get challenge token
        $loginResponse = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => '2fa@example.com',
            'password' => 'Password123',
        ]);
        $challengeData = $this->getResponseData($loginResponse);

        // Get valid TOTP code
        $totpService = new TotpService();
        $code = $totpService->getCurrentCode($secret);

        // Verify challenge
        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'challengeToken' => $challengeData['challengeToken'],
            'code' => $code,
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('token', $data['data']);
        $this->assertNotEmpty($data['data']['token']);
    }

    public function testVerify2FAChallengeWithValidBackupCode(): void
    {
        $user = $this->createUser('2fa@example.com', 'Password123');
        $this->enable2faForUserWithBackupCodes($user, ['AAAA-BBBB', 'CCCC-DDDD']);

        // Get challenge token
        $loginResponse = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => '2fa@example.com',
            'password' => 'Password123',
        ]);
        $challengeData = $this->getResponseData($loginResponse);

        // Verify challenge with backup code
        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'challengeToken' => $challengeData['challengeToken'],
            'code' => 'AAAA-BBBB',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('token', $data['data']);
    }

    public function testVerify2FAChallengeWithInvalidCode(): void
    {
        $user = $this->createUser('2fa@example.com', 'Password123');
        $this->enable2faForUser($user);

        // Get challenge token
        $loginResponse = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => '2fa@example.com',
            'password' => 'Password123',
        ]);
        $challengeData = $this->getResponseData($loginResponse);

        // Verify with invalid code
        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'challengeToken' => $challengeData['challengeToken'],
            'code' => '000000',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
        $this->assertErrorCode($response, '2FA_VERIFICATION_FAILED');
    }

    public function testVerify2FAChallengeWithInvalidToken(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'challengeToken' => 'invalid-token',
            'code' => '123456',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
        $this->assertErrorCode($response, '2FA_VERIFICATION_FAILED');
    }

    public function testVerify2FAChallengeTokenIsOneTimeUse(): void
    {
        $user = $this->createUser('2fa@example.com', 'Password123');
        $secret = $this->enable2faForUser($user);

        // Get challenge token
        $loginResponse = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => '2fa@example.com',
            'password' => 'Password123',
        ]);
        $challengeData = $this->getResponseData($loginResponse);

        // Get valid TOTP code
        $totpService = new TotpService();
        $code = $totpService->getCurrentCode($secret);

        // First verification should succeed
        $response1 = $this->apiRequest('POST', '/api/v1/auth/token', [
            'challengeToken' => $challengeData['challengeToken'],
            'code' => $code,
        ]);
        $this->assertResponseStatusCode(Response::HTTP_OK, $response1);

        // Second verification with same token should fail
        $response2 = $this->apiRequest('POST', '/api/v1/auth/token', [
            'challengeToken' => $challengeData['challengeToken'],
            'code' => $code,
        ]);
        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response2);
        $this->assertErrorCode($response2, '2FA_VERIFICATION_FAILED');
    }

    public function testBackupCodeIsMarkedAsUsedAfterVerification(): void
    {
        $user = $this->createUser('2fa@example.com', 'Password123');
        $this->enable2faForUserWithBackupCodes($user, ['TEST-CODE', 'OTHR-CODE']);

        // Get first challenge token
        $loginResponse1 = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => '2fa@example.com',
            'password' => 'Password123',
        ]);
        $challengeData1 = $this->getResponseData($loginResponse1);

        // Use backup code
        $this->apiRequest('POST', '/api/v1/auth/token', [
            'challengeToken' => $challengeData1['challengeToken'],
            'code' => 'TEST-CODE',
        ]);

        // Get second challenge token
        $loginResponse2 = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => '2fa@example.com',
            'password' => 'Password123',
        ]);
        $challengeData2 = $this->getResponseData($loginResponse2);

        // Same backup code should fail
        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'challengeToken' => $challengeData2['challengeToken'],
            'code' => 'TEST-CODE',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
        $this->assertErrorCode($response, '2FA_VERIFICATION_FAILED');
    }

    // ========================================
    // Recovery Endpoint Tests
    // ========================================

    public function testRecoveryRequestAlwaysSucceeds(): void
    {
        // Request for existing user with 2FA
        $user = $this->createUser('recovery@example.com', 'Password123');
        $this->enable2faForUser($user);

        $response1 = $this->apiRequest('POST', '/api/v1/2fa/recovery/request', [
            'email' => 'recovery@example.com',
        ]);
        $this->assertResponseStatusCode(Response::HTTP_OK, $response1);

        // Request for non-existent user (should still succeed to prevent enumeration)
        $response2 = $this->apiRequest('POST', '/api/v1/2fa/recovery/request', [
            'email' => 'nonexistent@example.com',
        ]);
        $this->assertResponseStatusCode(Response::HTTP_OK, $response2);
    }

    public function testRecoveryRequestRequiresEmail(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/2fa/recovery/request', []);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'EMAIL_REQUIRED');
    }

    public function testRecoveryCompleteFailsWithInvalidToken(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/2fa/recovery/complete', [
            'token' => 'invalid-recovery-token',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, 'INVALID_RECOVERY_TOKEN');
    }

    public function testRecoveryCompleteRequiresToken(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/2fa/recovery/complete', []);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'TOKEN_REQUIRED');
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function enable2faForUser($user): string
    {
        $totpService = new TotpService();
        $secret = $totpService->generateSecret();

        // Encrypt secret before storing (matches TwoFactorService::completeSetup behavior)
        $encryptionService = static::getContainer()->get(EncryptionServiceInterface::class);

        $user->setTwoFactorEnabled(true);
        $user->setTotpSecret($encryptionService->encrypt($secret));
        $user->setBackupCodes([]);
        $user->setTwoFactorEnabledAt(new \DateTimeImmutable());
        $user->setBackupCodesGeneratedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $secret;
    }

    private function enable2faForUserWithBackupCodes($user, array $codes): string
    {
        $totpService = new TotpService();
        $secret = $totpService->generateSecret();

        $passwordHasherFactory = new PasswordHasherFactory([
            'backup_code' => ['algorithm' => 'auto', 'cost' => 4],
        ]);
        $hasher = $passwordHasherFactory->getPasswordHasher('backup_code');

        $hashedCodes = [];
        foreach ($codes as $code) {
            $normalizedCode = str_replace('-', '', $code);
            $hashedCodes[] = [
                'hash' => $hasher->hash($normalizedCode),
                'used' => false,
            ];
        }

        // Encrypt secret before storing
        $encryptionService = static::getContainer()->get(EncryptionServiceInterface::class);

        $user->setTwoFactorEnabled(true);
        $user->setTotpSecret($encryptionService->encrypt($secret));
        $user->setBackupCodes($hashedCodes);
        $user->setTwoFactorEnabledAt(new \DateTimeImmutable());
        $user->setBackupCodesGeneratedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $secret;
    }
}
