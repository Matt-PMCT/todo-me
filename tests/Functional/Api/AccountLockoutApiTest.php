<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Repository\UserRepository;
use App\Tests\Functional\ApiTestCase;

final class AccountLockoutApiTest extends ApiTestCase
{
    public function testLoginFailsWhenAccountLocked(): void
    {
        $user = $this->createUser('locked@test.com', 'ValidPass123!');

        // Lock the user manually
        $user->setLockedUntil(new \DateTimeImmutable('+15 minutes'));
        $this->entityManager->flush();

        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'locked@test.com',
            'password' => 'ValidPass123!',
        ]);

        $this->assertResponseStatusCodeSame(423);
        $this->assertErrorCode($response, 'ACCOUNT_LOCKED');
    }

    public function testAccountLocksAfterFailedAttempts(): void
    {
        $user = $this->createUser('locktest@test.com', 'ValidPass123!');
        $userId = $user->getId();

        // Make 5 failed login attempts
        for ($i = 0; $i < 5; $i++) {
            $this->apiRequest('POST', '/api/v1/auth/token', [
                'email' => 'locktest@test.com',
                'password' => 'WrongPassword123!',
            ]);
        }

        // Fetch fresh user from database
        $userRepository = self::getContainer()->get(UserRepository::class);
        $freshUser = $userRepository->find($userId);

        // User should now be locked
        $this->assertTrue($freshUser->isLocked());
    }

    public function testSuccessfulLoginResetsFailedAttempts(): void
    {
        $user = $this->createUser('resetattempts@test.com', 'ValidPass123!');
        $userId = $user->getId();

        // Make 2 failed attempts
        for ($i = 0; $i < 2; $i++) {
            $this->apiRequest('POST', '/api/v1/auth/token', [
                'email' => 'resetattempts@test.com',
                'password' => 'WrongPassword123!',
            ]);
        }

        // Fetch fresh user and verify failed attempts recorded
        $userRepository = self::getContainer()->get(UserRepository::class);
        $freshUser = $userRepository->find($userId);
        $this->assertEquals(2, $freshUser->getFailedLoginAttempts());

        // Successful login
        $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'resetattempts@test.com',
            'password' => 'ValidPass123!',
        ]);

        // Fetch fresh user again and verify failed attempts are reset
        $this->entityManager->clear();
        $freshUser = $userRepository->find($userId);
        $this->assertEquals(0, $freshUser->getFailedLoginAttempts());
    }

    public function testExpiredLockoutAllowsLogin(): void
    {
        $user = $this->createUser('expiredlock@test.com', 'ValidPass123!');

        // Set expired lockout
        $user->setLockedUntil(new \DateTimeImmutable('-1 minute'));
        $this->entityManager->flush();

        $response = $this->apiRequest('POST', '/api/v1/auth/token', [
            'email' => 'expiredlock@test.com',
            'password' => 'ValidPass123!',
        ]);

        $this->assertSuccessResponse($response);
    }
}
