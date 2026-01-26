<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\TokenGenerator;
use App\Service\UserService;
use App\Tests\Unit\UnitTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserServiceTest extends UnitTestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private TokenGenerator&MockObject $tokenGenerator;
    private UserRepository&MockObject $userRepository;
    private UserService $userService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->tokenGenerator = $this->createMock(TokenGenerator::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->userService = new UserService(
            $this->entityManager,
            $this->passwordHasher,
            $this->tokenGenerator,
            $this->userRepository,
            48, // apiTokenTtlHours
        );
    }

    // ========================================
    // Register User Tests
    // ========================================

    public function testRegisterCreatesNewUser(): void
    {
        $email = 'test@example.com';
        $password = 'password123';
        $hashedPassword = 'hashed_password_value';
        $apiToken = 'generated_api_token_value';

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($this->isInstanceOf(User::class), $password)
            ->willReturn($hashedPassword);

        $this->tokenGenerator->expects($this->once())
            ->method('generateApiToken')
            ->willReturn($apiToken);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(User::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->userService->register($email, $password);

        $user = $result['user'];
        $plainToken = $result['token'];

        $this->assertEquals($email, $user->getEmail());
        $this->assertEquals($hashedPassword, $user->getPasswordHash());
        // Token is stored as SHA256 hash, plaintext returned separately
        $this->assertEquals(hash('sha256', $plainToken), $user->getApiTokenHash());
        $this->assertEquals($apiToken, $plainToken);
    }

    public function testRegisterHashesPassword(): void
    {
        $plainPassword = 'mySecurePassword123';

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with(
                $this->isInstanceOf(User::class),
                $plainPassword
            )
            ->willReturn('hashed_value');

        $this->tokenGenerator->method('generateApiToken')->willReturn('token');
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $result = $this->userService->register('test@example.com', $plainPassword);
        $user = $result['user'];

        $this->assertEquals('hashed_value', $user->getPasswordHash());
        $this->assertNotEquals($plainPassword, $user->getPasswordHash());
    }

    public function testRegisterGeneratesApiToken(): void
    {
        $expectedToken = 'abc123def456ghi789';

        $this->passwordHasher->method('hashPassword')->willReturn('hashed');

        $this->tokenGenerator->expects($this->once())
            ->method('generateApiToken')
            ->willReturn($expectedToken);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $result = $this->userService->register('test@example.com', 'password123');
        $user = $result['user'];
        $plainToken = $result['token'];

        // Token is stored as SHA256 hash, plaintext returned separately
        $this->assertEquals(hash('sha256', $expectedToken), $user->getApiTokenHash());
        $this->assertEquals($expectedToken, $plainToken);
    }

    public function testRegisterPersistsUser(): void
    {
        $this->passwordHasher->method('hashPassword')->willReturn('hashed');
        $this->tokenGenerator->method('generateApiToken')->willReturn('token');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($user) {
                return $user instanceof User
                    && $user->getEmail() === 'test@example.com';
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->userService->register('test@example.com', 'password123');
    }

    // ========================================
    // Generate New API Token Tests
    // ========================================

    public function testGenerateNewApiTokenUpdatesToken(): void
    {
        $user = $this->createUserWithId();
        $user->setApiTokenHash(hash('sha256', 'old_token'));
        $newToken = 'new_generated_token';

        $this->tokenGenerator->expects($this->once())
            ->method('generateApiToken')
            ->willReturn($newToken);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($user);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->userService->generateNewApiToken($user);

        // Method returns plaintext token for the user to store
        $this->assertEquals($newToken, $result);
        // User entity stores hash of the token
        $this->assertEquals(hash('sha256', $newToken), $user->getApiTokenHash());
    }

    public function testGenerateNewApiTokenReturnsNewToken(): void
    {
        $user = $this->createUserWithId();
        $expectedToken = 'brand_new_token_123';

        $this->tokenGenerator->expects($this->once())
            ->method('generateApiToken')
            ->willReturn($expectedToken);

        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $result = $this->userService->generateNewApiToken($user);

        $this->assertEquals($expectedToken, $result);
    }

    // ========================================
    // Revoke API Token Tests
    // ========================================

    public function testRevokeApiTokenSetsToNull(): void
    {
        $user = $this->createUserWithId();
        $user->setApiTokenHash(hash('sha256', 'existing_token'));

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($user);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->userService->revokeApiToken($user);

        $this->assertNull($user->getApiTokenHash());
    }

    public function testRevokeApiTokenPersistsUser(): void
    {
        $user = $this->createUserWithId();
        $user->setApiTokenHash(hash('sha256', 'token'));

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($user);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->userService->revokeApiToken($user);
    }

    // ========================================
    // Validate Password Tests
    // ========================================

    public function testValidatePasswordReturnsTrueForCorrectPassword(): void
    {
        $user = $this->createUserWithId();
        $correctPassword = 'correctPassword123';

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, $correctPassword)
            ->willReturn(true);

        $result = $this->userService->validatePassword($user, $correctPassword);

        $this->assertTrue($result);
    }

    public function testValidatePasswordReturnsFalseForIncorrectPassword(): void
    {
        $user = $this->createUserWithId();
        $incorrectPassword = 'wrongPassword';

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, $incorrectPassword)
            ->willReturn(false);

        $result = $this->userService->validatePassword($user, $incorrectPassword);

        $this->assertFalse($result);
    }

    public function testValidatePasswordUsesPasswordHasher(): void
    {
        $user = $this->createUserWithId();
        $password = 'testPassword';

        $this->passwordHasher->expects($this->once())
            ->method('isPasswordValid')
            ->with($user, $password);

        $this->userService->validatePassword($user, $password);
    }

    // ========================================
    // Change Password Tests
    // ========================================

    public function testChangePasswordHashesNewPassword(): void
    {
        $user = $this->createUserWithId();
        $newPassword = 'newSecurePassword123';
        $hashedNewPassword = 'hashed_new_password';

        $this->passwordHasher->expects($this->once())
            ->method('hashPassword')
            ->with($user, $newPassword)
            ->willReturn($hashedNewPassword);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($user);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->userService->changePassword($user, $newPassword);

        $this->assertEquals($hashedNewPassword, $user->getPasswordHash());
    }

    public function testChangePasswordPersistsUser(): void
    {
        $user = $this->createUserWithId();

        $this->passwordHasher->method('hashPassword')->willReturn('hashed');

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($user);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->userService->changePassword($user, 'newPassword123');
    }

    // ========================================
    // Find By Email Tests
    // ========================================

    public function testFindByEmailReturnsUserWhenFound(): void
    {
        $email = 'test@example.com';
        $user = $this->createUserWithId();
        $user->setEmail($email);

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn($user);

        $result = $this->userService->findByEmail($email);

        $this->assertSame($user, $result);
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $email = 'nonexistent@example.com';

        $this->userRepository->expects($this->once())
            ->method('findByEmail')
            ->with($email)
            ->willReturn(null);

        $result = $this->userService->findByEmail($email);

        $this->assertNull($result);
    }

    // ========================================
    // Find By API Token Tests
    // ========================================

    public function testFindByApiTokenReturnsUserWhenFound(): void
    {
        $token = 'valid_api_token';
        $user = $this->createUserWithId();
        $user->setApiTokenHash(hash('sha256', $token));
        $user->setApiTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        // Service hashes token before querying repository
        $this->userRepository->expects($this->once())
            ->method('findByApiTokenHash')
            ->with(hash('sha256', $token))
            ->willReturn($user);

        $result = $this->userService->findByApiToken($token);

        $this->assertSame($user, $result);
    }

    public function testFindByApiTokenReturnsNullWhenNotFound(): void
    {
        $token = 'invalid_token';

        // Service hashes token before querying repository
        $this->userRepository->expects($this->once())
            ->method('findByApiTokenHash')
            ->with(hash('sha256', $token))
            ->willReturn(null);

        $result = $this->userService->findByApiToken($token);

        $this->assertNull($result);
    }

    // ========================================
    // Token Expiration Tests
    // ========================================

    public function testRegisterSetsTokenExpiration(): void
    {
        $email = 'test@example.com';
        $password = 'password123';

        $this->passwordHasher->method('hashPassword')->willReturn('hashed');
        $this->tokenGenerator->method('generateApiToken')->willReturn('token');
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $result = $this->userService->register($email, $password);
        $user = $result['user'];

        $this->assertNotNull($user->getApiTokenIssuedAt());
        $this->assertNotNull($user->getApiTokenExpiresAt());
        $this->assertGreaterThan($user->getApiTokenIssuedAt(), $user->getApiTokenExpiresAt());
    }

    public function testGenerateNewApiTokenSetsExpiration(): void
    {
        $user = $this->createUserWithId();
        $user->setApiTokenHash(hash('sha256', 'old_token'));

        $this->tokenGenerator->method('generateApiToken')->willReturn('new_token');
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $this->userService->generateNewApiToken($user);

        $this->assertNotNull($user->getApiTokenIssuedAt());
        $this->assertNotNull($user->getApiTokenExpiresAt());
    }

    public function testFindByApiTokenReturnsNullForExpiredToken(): void
    {
        $user = $this->createUserWithId();
        $user->setApiTokenHash(hash('sha256', 'expired_token'));
        $user->setApiTokenExpiresAt(new \DateTimeImmutable('-1 hour'));

        // Service hashes token before querying repository
        $this->userRepository->method('findByApiTokenHash')
            ->with(hash('sha256', 'expired_token'))
            ->willReturn($user);

        $result = $this->userService->findByApiToken('expired_token');

        $this->assertNull($result);
    }

    public function testFindByApiTokenReturnsUserForValidToken(): void
    {
        $user = $this->createUserWithId();
        $user->setApiTokenHash(hash('sha256', 'valid_token'));
        $user->setApiTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        // Service hashes token before querying repository
        $this->userRepository->method('findByApiTokenHash')
            ->with(hash('sha256', 'valid_token'))
            ->willReturn($user);

        $result = $this->userService->findByApiToken('valid_token');

        $this->assertSame($user, $result);
    }

    public function testFindByApiTokenIgnoreExpirationReturnsExpiredUser(): void
    {
        $user = $this->createUserWithId();
        $user->setApiTokenHash(hash('sha256', 'expired_token'));
        $user->setApiTokenExpiresAt(new \DateTimeImmutable('-1 hour'));

        // Service hashes token before querying repository
        $this->userRepository->method('findByApiTokenHash')
            ->with(hash('sha256', 'expired_token'))
            ->willReturn($user);

        $result = $this->userService->findByApiTokenIgnoreExpiration('expired_token');

        $this->assertSame($user, $result);
    }

    public function testGetTokenExpiresAtReturnsUserExpiration(): void
    {
        $user = $this->createUserWithId();
        $expiresAt = new \DateTimeImmutable('+48 hours');
        $user->setApiTokenExpiresAt($expiresAt);

        $result = $this->userService->getTokenExpiresAt($user);

        $this->assertSame($expiresAt, $result);
    }
}
