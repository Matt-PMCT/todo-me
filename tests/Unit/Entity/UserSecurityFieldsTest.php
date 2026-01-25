<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Tests\Unit\UnitTestCase;

final class UserSecurityFieldsTest extends UnitTestCase
{
    public function testIncrementFailedLoginAttempts(): void
    {
        $user = new User();
        $this->assertEquals(0, $user->getFailedLoginAttempts());

        $user->incrementFailedLoginAttempts();
        $this->assertEquals(1, $user->getFailedLoginAttempts());
        $this->assertNotNull($user->getLastFailedLoginAt());

        $user->incrementFailedLoginAttempts();
        $this->assertEquals(2, $user->getFailedLoginAttempts());
    }

    public function testResetFailedLoginAttempts(): void
    {
        $user = new User();
        $user->incrementFailedLoginAttempts();
        $user->incrementFailedLoginAttempts();
        $user->setLockedUntil(new \DateTimeImmutable('+15 minutes'));

        $user->resetFailedLoginAttempts();

        $this->assertEquals(0, $user->getFailedLoginAttempts());
        $this->assertNull($user->getLastFailedLoginAt());
        $this->assertNull($user->getLockedUntil());
    }

    public function testIsLockedReturnsTrueWhenLocked(): void
    {
        $user = new User();
        $user->setLockedUntil(new \DateTimeImmutable('+15 minutes'));

        $this->assertTrue($user->isLocked());
    }

    public function testIsLockedReturnsFalseWhenExpired(): void
    {
        $user = new User();
        $user->setLockedUntil(new \DateTimeImmutable('-1 minute'));

        $this->assertFalse($user->isLocked());
    }

    public function testIsLockedReturnsFalseWhenNull(): void
    {
        $user = new User();
        $this->assertFalse($user->isLocked());
    }

    public function testGetLockoutRemainingSecondsWhenLocked(): void
    {
        $user = new User();
        $user->setLockedUntil(new \DateTimeImmutable('+60 seconds'));

        $remaining = $user->getLockoutRemainingSeconds();
        $this->assertGreaterThan(50, $remaining);
        $this->assertLessThanOrEqual(60, $remaining);
    }

    public function testGetLockoutRemainingSecondsWhenNotLocked(): void
    {
        $user = new User();
        $this->assertEquals(0, $user->getLockoutRemainingSeconds());
    }

    public function testIsPasswordResetTokenValidWithValidToken(): void
    {
        $user = new User();
        $user->setPasswordResetToken('validtoken');
        $user->setPasswordResetExpiresAt(new \DateTimeImmutable('+30 minutes'));

        $this->assertTrue($user->isPasswordResetTokenValid());
    }

    public function testIsPasswordResetTokenValidWithExpiredToken(): void
    {
        $user = new User();
        $user->setPasswordResetToken('expiredtoken');
        $user->setPasswordResetExpiresAt(new \DateTimeImmutable('-1 minute'));

        $this->assertFalse($user->isPasswordResetTokenValid());
    }

    public function testIsPasswordResetTokenValidWithNoToken(): void
    {
        $user = new User();
        $this->assertFalse($user->isPasswordResetTokenValid());
    }

    public function testEmailVerificationFields(): void
    {
        $user = new User();

        $this->assertFalse($user->isEmailVerified());

        $user->setEmailVerified(true);
        $this->assertTrue($user->isEmailVerified());

        $user->setEmailVerificationToken('token123');
        $this->assertEquals('token123', $user->getEmailVerificationToken());

        $now = new \DateTimeImmutable();
        $user->setEmailVerificationSentAt($now);
        $this->assertEquals($now, $user->getEmailVerificationSentAt());
    }
}
