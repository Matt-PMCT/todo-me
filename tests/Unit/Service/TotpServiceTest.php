<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TotpService;
use App\Tests\Unit\UnitTestCase;

class TotpServiceTest extends UnitTestCase
{
    private TotpService $totpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totpService = new TotpService();
    }

    public function testGenerateSecretReturnsValidBase32String(): void
    {
        $secret = $this->totpService->generateSecret();

        $this->assertNotEmpty($secret);
        // Base32 uses only A-Z and 2-7
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function testGenerateSecretReturnsUniqueSecrets(): void
    {
        $secret1 = $this->totpService->generateSecret();
        $secret2 = $this->totpService->generateSecret();

        $this->assertNotEquals($secret1, $secret2);
    }

    public function testGetProvisioningUriReturnsValidUri(): void
    {
        $user = $this->createUserWithId('user-123', 'test@example.com');
        $secret = $this->totpService->generateSecret();

        $uri = $this->totpService->getProvisioningUri($user, $secret);

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret='.$secret, $uri);
        $this->assertStringContainsString('issuer=TodoMe', $uri);
        $this->assertStringContainsString(urlencode('test@example.com'), $uri);
    }

    public function testVerifyCodeReturnsTrueForValidCode(): void
    {
        $secret = $this->totpService->generateSecret();
        $code = $this->totpService->getCurrentCode($secret);

        $result = $this->totpService->verifyCode($secret, $code);

        $this->assertTrue($result);
    }

    public function testVerifyCodeReturnsFalseForInvalidCode(): void
    {
        $secret = $this->totpService->generateSecret();

        $result = $this->totpService->verifyCode($secret, '000000');

        $this->assertFalse($result);
    }

    public function testVerifyCodeReturnsFalseForMalformedCode(): void
    {
        $secret = $this->totpService->generateSecret();

        $this->assertFalse($this->totpService->verifyCode($secret, 'abcdef'));
        $this->assertFalse($this->totpService->verifyCode($secret, '12345'));
        $this->assertFalse($this->totpService->verifyCode($secret, '1234567'));
    }

    public function testGetCurrentCodeReturnsSixDigitCode(): void
    {
        $secret = $this->totpService->generateSecret();

        $code = $this->totpService->getCurrentCode($secret);

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function testGetCurrentCodeIsVerifiable(): void
    {
        $secret = $this->totpService->generateSecret();
        $code = $this->totpService->getCurrentCode($secret);

        $this->assertTrue($this->totpService->verifyCode($secret, $code));
    }
}
