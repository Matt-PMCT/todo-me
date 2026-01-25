<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Interface\BackupCodeServiceInterface;
use App\Interface\TotpServiceInterface;
use App\Service\RedisService;
use App\Service\TwoFactorService;
use App\Tests\Unit\UnitTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class TwoFactorServiceTest extends UnitTestCase
{
    private TotpServiceInterface&MockObject $totpService;
    private BackupCodeServiceInterface&MockObject $backupCodeService;
    private RedisService&MockObject $redisService;
    private EntityManagerInterface&MockObject $entityManager;
    private TwoFactorService $twoFactorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->totpService = $this->createMock(TotpServiceInterface::class);
        $this->backupCodeService = $this->createMock(BackupCodeServiceInterface::class);
        $this->redisService = $this->createMock(RedisService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->twoFactorService = new TwoFactorService(
            $this->totpService,
            $this->backupCodeService,
            $this->redisService,
            $this->entityManager,
        );
    }

    // ========================================
    // Initialize Setup Tests
    // ========================================

    public function testInitializeSetupReturnsSetupData(): void
    {
        $user = $this->createUserWithId();
        $secret = 'TESTSECRET123';
        $qrCodeUri = 'otpauth://totp/TodoMe:test@example.com?secret=TESTSECRET123';

        $this->totpService->expects($this->once())
            ->method('generateSecret')
            ->willReturn($secret);

        $this->totpService->expects($this->once())
            ->method('getProvisioningUri')
            ->with($user, $secret)
            ->willReturn($qrCodeUri);

        $this->redisService->expects($this->once())
            ->method('setJson')
            ->willReturn(true);

        $result = $this->twoFactorService->initializeSetup($user);

        $this->assertArrayHasKey('setupToken', $result);
        $this->assertArrayHasKey('secret', $result);
        $this->assertArrayHasKey('qrCodeUri', $result);
        $this->assertArrayHasKey('expiresIn', $result);
        $this->assertEquals($secret, $result['secret']);
        $this->assertEquals($qrCodeUri, $result['qrCodeUri']);
        $this->assertEquals(600, $result['expiresIn']);
    }

    // ========================================
    // Complete Setup Tests
    // ========================================

    public function testCompleteSetupEnables2FAOnValidCode(): void
    {
        $user = $this->createUserWithId();
        $secret = 'TESTSECRET123';
        $code = '123456';
        $backupCodes = ['AAAA-BBBB', 'CCCC-DDDD'];

        $this->redisService->expects($this->once())
            ->method('getJsonAndDelete')
            ->willReturn(['secret' => $secret]);

        $this->totpService->expects($this->once())
            ->method('verifyCode')
            ->with($secret, $code)
            ->willReturn(true);

        $this->backupCodeService->expects($this->once())
            ->method('generateBackupCodes')
            ->willReturn([
                'codes' => $backupCodes,
                'hashedCodes' => [['hash' => 'h1', 'used' => false], ['hash' => 'h2', 'used' => false]],
            ]);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->twoFactorService->completeSetup($user, 'setup-token', $code);

        $this->assertNotNull($result);
        $this->assertTrue($result['enabled']);
        $this->assertEquals($backupCodes, $result['backupCodes']);
        $this->assertTrue($user->isTwoFactorEnabled());
        $this->assertEquals($secret, $user->getTotpSecret());
    }

    public function testCompleteSetupReturnsNullOnInvalidToken(): void
    {
        $user = $this->createUserWithId();

        $this->redisService->expects($this->once())
            ->method('getJsonAndDelete')
            ->willReturn(null);

        $result = $this->twoFactorService->completeSetup($user, 'invalid-token', '123456');

        $this->assertNull($result);
    }

    public function testCompleteSetupReturnsNullOnInvalidCode(): void
    {
        $user = $this->createUserWithId();

        $this->redisService->expects($this->once())
            ->method('getJsonAndDelete')
            ->willReturn(['secret' => 'TESTSECRET']);

        $this->totpService->expects($this->once())
            ->method('verifyCode')
            ->willReturn(false);

        $result = $this->twoFactorService->completeSetup($user, 'setup-token', '000000');

        $this->assertNull($result);
    }

    // ========================================
    // Disable Tests
    // ========================================

    public function testDisableClears2FAFields(): void
    {
        $user = $this->createUserWithId();
        $user->setTwoFactorEnabled(true);
        $user->setTotpSecret('TESTSECRET');
        $user->setBackupCodes([['hash' => 'h1', 'used' => false]]);
        $user->setTwoFactorEnabledAt(new \DateTimeImmutable());
        $user->setBackupCodesGeneratedAt(new \DateTimeImmutable());

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->twoFactorService->disable($user);

        $this->assertFalse($user->isTwoFactorEnabled());
        $this->assertNull($user->getTotpSecret());
        $this->assertNull($user->getBackupCodes());
        $this->assertNull($user->getTwoFactorEnabledAt());
        $this->assertNull($user->getBackupCodesGeneratedAt());
    }

    // ========================================
    // Verify Tests
    // ========================================

    public function testVerifyReturnsTrueForValidCode(): void
    {
        $user = $this->createUserWithId();
        $user->setTotpSecret('TESTSECRET');

        $this->totpService->expects($this->once())
            ->method('verifyCode')
            ->with('TESTSECRET', '123456')
            ->willReturn(true);

        $result = $this->twoFactorService->verify($user, '123456');

        $this->assertTrue($result);
    }

    public function testVerifyReturnsFalseForInvalidCode(): void
    {
        $user = $this->createUserWithId();
        $user->setTotpSecret('TESTSECRET');

        $this->totpService->expects($this->once())
            ->method('verifyCode')
            ->willReturn(false);

        $result = $this->twoFactorService->verify($user, '000000');

        $this->assertFalse($result);
    }

    public function testVerifyReturnsFalseWhenNoSecret(): void
    {
        $user = $this->createUserWithId();
        $user->setTotpSecret(null);

        $result = $this->twoFactorService->verify($user, '123456');

        $this->assertFalse($result);
    }

    // ========================================
    // Verify With Backup Code Tests
    // ========================================

    public function testVerifyWithBackupCodeDelegatesToBackupCodeService(): void
    {
        $user = $this->createUserWithId();

        $this->backupCodeService->expects($this->once())
            ->method('verifyBackupCode')
            ->with($user, 'AAAA-BBBB')
            ->willReturn(true);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->twoFactorService->verifyWithBackupCode($user, 'AAAA-BBBB');

        $this->assertTrue($result);
    }

    // ========================================
    // Regenerate Backup Codes Tests
    // ========================================

    public function testRegenerateBackupCodesReturnsNewCodes(): void
    {
        $user = $this->createUserWithId();
        $newCodes = ['XXXX-YYYY', 'ZZZZ-WWWW'];

        $this->backupCodeService->expects($this->once())
            ->method('generateBackupCodes')
            ->willReturn([
                'codes' => $newCodes,
                'hashedCodes' => [['hash' => 'h1', 'used' => false], ['hash' => 'h2', 'used' => false]],
            ]);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->twoFactorService->regenerateBackupCodes($user);

        $this->assertEquals($newCodes, $result);
        $this->assertNotNull($user->getBackupCodesGeneratedAt());
    }

    // ========================================
    // Get Status Tests
    // ========================================

    public function testGetStatusReturnsCorrectData(): void
    {
        $enabledAt = new \DateTimeImmutable('2024-01-01 12:00:00');
        $user = $this->createUserWithId();
        $user->setTwoFactorEnabled(true);
        $user->setTwoFactorEnabledAt($enabledAt);
        $user->setBackupCodes([
            ['hash' => 'h1', 'used' => false],
            ['hash' => 'h2', 'used' => true],
            ['hash' => 'h3', 'used' => false],
        ]);

        $result = $this->twoFactorService->getStatus($user);

        $this->assertTrue($result['enabled']);
        $this->assertEquals($enabledAt->format(\DateTimeInterface::ATOM), $result['enabledAt']);
        $this->assertEquals(2, $result['backupCodesRemaining']);
    }

    public function testGetStatusReturnsDisabledStatus(): void
    {
        $user = $this->createUserWithId();
        $user->setTwoFactorEnabled(false);

        $result = $this->twoFactorService->getStatus($user);

        $this->assertFalse($result['enabled']);
        $this->assertNull($result['enabledAt']);
        $this->assertEquals(0, $result['backupCodesRemaining']);
    }
}
