<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\BackupCodeService;
use App\Tests\Unit\UnitTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

class BackupCodeServiceTest extends UnitTestCase
{
    private BackupCodeService $backupCodeService;

    protected function setUp(): void
    {
        parent::setUp();

        $passwordHasherFactory = new PasswordHasherFactory([
            'backup_code' => ['algorithm' => 'auto', 'cost' => 4],
        ]);

        $this->backupCodeService = new BackupCodeService($passwordHasherFactory);
    }

    public function testGenerateBackupCodesReturnsTenCodes(): void
    {
        $result = $this->backupCodeService->generateBackupCodes();

        $this->assertCount(10, $result['codes']);
        $this->assertCount(10, $result['hashedCodes']);
    }

    public function testGenerateBackupCodesReturnsCorrectFormat(): void
    {
        $result = $this->backupCodeService->generateBackupCodes();

        foreach ($result['codes'] as $code) {
            // XXXX-XXXX format (8 hex digits with dash)
            $this->assertMatchesRegularExpression('/^[A-F0-9]{4}-[A-F0-9]{4}$/', $code);
        }
    }

    public function testGenerateBackupCodesReturnsHashedCodes(): void
    {
        $result = $this->backupCodeService->generateBackupCodes();

        foreach ($result['hashedCodes'] as $hashedCode) {
            $this->assertArrayHasKey('hash', $hashedCode);
            $this->assertArrayHasKey('used', $hashedCode);
            $this->assertFalse($hashedCode['used']);
            $this->assertNotEmpty($hashedCode['hash']);
        }
    }

    public function testGenerateBackupCodesAreUnique(): void
    {
        $result = $this->backupCodeService->generateBackupCodes();

        $uniqueCodes = array_unique($result['codes']);
        $this->assertCount(10, $uniqueCodes);
    }

    public function testVerifyBackupCodeReturnsTrueForValidCode(): void
    {
        $result = $this->backupCodeService->generateBackupCodes();
        $user = $this->createUserWithId();
        $user->setBackupCodes($result['hashedCodes']);

        $validCode = $result['codes'][0];
        $isValid = $this->backupCodeService->verifyBackupCode($user, $validCode);

        $this->assertTrue($isValid);
    }

    public function testVerifyBackupCodeReturnsFalseForInvalidCode(): void
    {
        $result = $this->backupCodeService->generateBackupCodes();
        $user = $this->createUserWithId();
        $user->setBackupCodes($result['hashedCodes']);

        $isValid = $this->backupCodeService->verifyBackupCode($user, 'ZZZZ-ZZZZ');

        $this->assertFalse($isValid);
    }

    public function testVerifyBackupCodeMarksCodeAsUsed(): void
    {
        $result = $this->backupCodeService->generateBackupCodes();
        $user = $this->createUserWithId();
        $user->setBackupCodes($result['hashedCodes']);

        $validCode = $result['codes'][0];
        $this->backupCodeService->verifyBackupCode($user, $validCode);

        $backupCodes = $user->getBackupCodes();
        $this->assertTrue($backupCodes[0]['used']);
    }

    public function testVerifyBackupCodeReturnsFalseForUsedCode(): void
    {
        $result = $this->backupCodeService->generateBackupCodes();
        $user = $this->createUserWithId();
        $user->setBackupCodes($result['hashedCodes']);

        $validCode = $result['codes'][0];

        // First use should succeed
        $this->assertTrue($this->backupCodeService->verifyBackupCode($user, $validCode));

        // Second use should fail
        $this->assertFalse($this->backupCodeService->verifyBackupCode($user, $validCode));
    }

    public function testVerifyBackupCodeReturnsFalseForNullBackupCodes(): void
    {
        $user = $this->createUserWithId();
        $user->setBackupCodes(null);

        $isValid = $this->backupCodeService->verifyBackupCode($user, 'AAAA-BBBB');

        $this->assertFalse($isValid);
    }

    public function testVerifyBackupCodeAcceptsCodeWithoutDash(): void
    {
        $result = $this->backupCodeService->generateBackupCodes();
        $user = $this->createUserWithId();
        $user->setBackupCodes($result['hashedCodes']);

        $validCode = $result['codes'][0];
        $codeWithoutDash = str_replace('-', '', $validCode);

        $isValid = $this->backupCodeService->verifyBackupCode($user, $codeWithoutDash);

        $this->assertTrue($isValid);
    }
}
