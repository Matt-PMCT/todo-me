<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Interface\EncryptionServiceInterface;
use App\Service\TotpService;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for 2FA setup API endpoints.
 */
class TwoFactorSetupApiTest extends ApiTestCase
{
    // ========================================
    // Status Endpoint Tests
    // ========================================

    public function testStatusReturnsDisabledForNewUser(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'GET', '/api/v1/2fa/status');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertFalse($data['data']['enabled']);
        $this->assertNull($data['data']['enabledAt']);
        $this->assertEquals(0, $data['data']['backupCodesRemaining']);
    }

    public function testStatusRequiresAuthentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/2fa/status');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Setup Initiation Tests
    // ========================================

    public function testSetupReturnsQRCodeUri(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/setup');

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('setupToken', $data['data']);
        $this->assertArrayHasKey('secret', $data['data']);
        $this->assertArrayHasKey('qrCodeUri', $data['data']);
        $this->assertArrayHasKey('expiresIn', $data['data']);

        $this->assertNotEmpty($data['data']['setupToken']);
        $this->assertNotEmpty($data['data']['secret']);
        $this->assertStringStartsWith('otpauth://totp/', $data['data']['qrCodeUri']);
        $this->assertEquals(600, $data['data']['expiresIn']);
    }

    public function testSetupFailsIfAlreadyEnabled(): void
    {
        $user = $this->createUser();
        $this->enable2faForUser($user);

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/setup');

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, '2FA_ALREADY_ENABLED');
    }

    public function testSetupRequiresAuthentication(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/2fa/setup');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Setup Verification Tests
    // ========================================

    public function testSetupVerifyEnables2FA(): void
    {
        $user = $this->createUser();

        // Start setup
        $setupResponse = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/setup');
        $setupData = $this->getResponseData($setupResponse);

        // Get valid TOTP code
        $totpService = new TotpService();
        $code = $totpService->getCurrentCode($setupData['secret']);

        // Verify setup
        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/setup/verify', [
            'setupToken' => $setupData['setupToken'],
            'code' => $code,
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertTrue($data['data']['enabled']);
        $this->assertArrayHasKey('backupCodes', $data['data']);
        $this->assertCount(10, $data['data']['backupCodes']);
    }

    public function testSetupVerifyFailsWithInvalidCode(): void
    {
        $user = $this->createUser();

        // Start setup
        $setupResponse = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/setup');
        $setupData = $this->getResponseData($setupResponse);

        // Verify with invalid code
        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/setup/verify', [
            'setupToken' => $setupData['setupToken'],
            'code' => '000000',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, '2FA_SETUP_FAILED');
    }

    public function testSetupVerifyFailsWithInvalidToken(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/setup/verify', [
            'setupToken' => 'invalid-token',
            'code' => '123456',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, '2FA_SETUP_FAILED');
    }

    public function testSetupVerifyFailsIfAlreadyEnabled(): void
    {
        $user = $this->createUser();
        $this->enable2faForUser($user);

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/setup/verify', [
            'setupToken' => 'some-token',
            'code' => '123456',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, '2FA_ALREADY_ENABLED');
    }

    public function testSetupVerifyValidatesInput(): void
    {
        $user = $this->createUser();

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/setup/verify', [
            'setupToken' => '',
            'code' => '',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    // ========================================
    // Disable Tests
    // ========================================

    public function testDisableRemoves2FA(): void
    {
        $user = $this->createUser('disable@example.com', 'Password123');
        $this->enable2faForUser($user);

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/disable', [
            'password' => 'Password123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertStringContainsString('disabled', $data['data']['message']);

        // Verify 2FA is disabled
        $this->entityManager->refresh($user);
        $this->assertFalse($user->isTwoFactorEnabled());
        $this->assertNull($user->getTotpSecret());
    }

    public function testDisableFailsWithWrongPassword(): void
    {
        $user = $this->createUser('disable@example.com', 'Password123');
        $this->enable2faForUser($user);

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/disable', [
            'password' => 'WrongPassword',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, 'INVALID_PASSWORD');
    }

    public function testDisableFailsWithoutPassword(): void
    {
        $user = $this->createUser();
        $this->enable2faForUser($user);

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/disable', []);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'PASSWORD_REQUIRED');
    }

    public function testDisableFailsIf2FANotEnabled(): void
    {
        $user = $this->createUser('disable@example.com', 'Password123');

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/disable', [
            'password' => 'Password123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, '2FA_NOT_ENABLED');
    }

    // ========================================
    // Backup Codes Regeneration Tests
    // ========================================

    public function testRegenerateBackupCodesReturnsNewCodes(): void
    {
        $user = $this->createUser('backup@example.com', 'Password123');
        $this->enable2faForUser($user);

        $originalCodes = $user->getBackupCodes();

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/backup-codes', [
            'password' => 'Password123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->assertSuccessResponse($response);

        $this->assertArrayHasKey('backupCodes', $data['data']);
        $this->assertCount(10, $data['data']['backupCodes']);

        // Verify codes changed
        $this->entityManager->refresh($user);
        $this->assertNotEquals($originalCodes, $user->getBackupCodes());
    }

    public function testRegenerateBackupCodesFailsWithWrongPassword(): void
    {
        $user = $this->createUser('backup@example.com', 'Password123');
        $this->enable2faForUser($user);

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/backup-codes', [
            'password' => 'WrongPassword',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, 'INVALID_PASSWORD');
    }

    public function testRegenerateBackupCodesFailsIf2FANotEnabled(): void
    {
        $user = $this->createUser('backup@example.com', 'Password123');

        $response = $this->authenticatedApiRequest($user, 'POST', '/api/v1/2fa/backup-codes', [
            'password' => 'Password123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, '2FA_NOT_ENABLED');
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function enable2faForUser($user): void
    {
        $totpService = new TotpService();
        $secret = $totpService->generateSecret();

        // Encrypt secret before storing
        $encryptionService = static::getContainer()->get(EncryptionServiceInterface::class);

        $user->setTwoFactorEnabled(true);
        $user->setTotpSecret($encryptionService->encrypt($secret));
        $user->setBackupCodes([
            ['hash' => password_hash('AAAA-BBBB', PASSWORD_DEFAULT), 'used' => false],
            ['hash' => password_hash('CCCC-DDDD', PASSWORD_DEFAULT), 'used' => false],
        ]);
        $user->setTwoFactorEnabledAt(new \DateTimeImmutable());
        $user->setBackupCodesGeneratedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }
}
