<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Enum\UndoAction;
use App\Service\RedisService;
use App\Service\UndoService;
use App\Tests\Unit\UnitTestCase;
use App\ValueObject\UndoToken;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class UndoServiceTest extends UnitTestCase
{
    private RedisService&MockObject $redisService;
    private LoggerInterface&MockObject $logger;
    private UndoService $undoService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redisService = $this->createMock(RedisService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->undoService = new UndoService($this->redisService, $this->logger);
    }

    // ========================================
    // Create Undo Token Tests
    // ========================================

    public function testCreateUndoTokenStoresInRedisWithTtl(): void
    {
        $userId = 'user-123';
        $action = UndoAction::DELETE->value;
        $entityType = 'task';
        $entityId = 'task-123';
        $previousState = ['title' => 'Test Task'];
        $ttl = 60;

        $this->redisService->expects($this->once())
            ->method('setJson')
            ->with(
                $this->stringContains("undo:{$userId}:"),
                $this->isType('array'),
                $ttl
            )
            ->willReturn(true);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Undo token created', $this->anything());

        $result = $this->undoService->createUndoToken(
            $userId,
            $action,
            $entityType,
            $entityId,
            $previousState,
            $ttl
        );

        $this->assertInstanceOf(UndoToken::class, $result);
        $this->assertEquals($action, $result->action);
        $this->assertEquals($entityType, $result->entityType);
        $this->assertEquals($entityId, $result->entityId);
        $this->assertEquals($previousState, $result->previousState);
    }

    public function testCreateUndoTokenWithCustomTtl(): void
    {
        $customTtl = 120;

        $this->redisService->expects($this->once())
            ->method('setJson')
            ->with(
                $this->anything(),
                $this->anything(),
                $customTtl
            )
            ->willReturn(true);

        $result = $this->undoService->createUndoToken(
            'user-123',
            UndoAction::DELETE->value,
            'task',
            'task-123',
            [],
            $customTtl
        );

        $this->assertNotNull($result);
    }

    public function testCreateUndoTokenReturnsNullOnInvalidAction(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('Invalid undo action attempted', $this->anything());

        $result = $this->undoService->createUndoToken(
            'user-123',
            'invalid_action',
            'task',
            'task-123',
            []
        );

        $this->assertNull($result);
    }

    public function testCreateUndoTokenReturnsNullOnRedisFailure(): void
    {
        $this->redisService->expects($this->once())
            ->method('setJson')
            ->willReturn(false);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to store undo token in Redis', $this->anything());

        $result = $this->undoService->createUndoToken(
            'user-123',
            UndoAction::DELETE->value,
            'task',
            'task-123',
            []
        );

        $this->assertNull($result);
    }

    // ========================================
    // Get Undo Token Tests
    // ========================================

    public function testGetUndoTokenRetrievesFromRedis(): void
    {
        $userId = 'user-123';
        $token = bin2hex(random_bytes(16));
        $tokenData = [
            'token' => $token,
            'action' => UndoAction::DELETE->value,
            'entityType' => 'task',
            'entityId' => 'task-123',
            'previousState' => ['title' => 'Test'],
            'createdAt' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
            'expiresAt' => (new \DateTimeImmutable('+60 seconds'))->format(\DateTimeImmutable::ATOM),
        ];

        $this->redisService->expects($this->once())
            ->method('getJson')
            ->with("undo:{$userId}:{$token}")
            ->willReturn($tokenData);

        $result = $this->undoService->getUndoToken($userId, $token);

        $this->assertInstanceOf(UndoToken::class, $result);
        $this->assertEquals($token, $result->token);
        $this->assertEquals(UndoAction::DELETE->value, $result->action);
    }

    public function testGetUndoTokenReturnsNullWhenNotFound(): void
    {
        $userId = 'user-123';
        $token = 'non-existent-token';

        $this->redisService->expects($this->once())
            ->method('getJson')
            ->with("undo:{$userId}:{$token}")
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('Undo token not found', $this->anything());

        $result = $this->undoService->getUndoToken($userId, $token);

        $this->assertNull($result);
    }

    public function testGetUndoTokenReturnsNullAndCleansUpWhenExpired(): void
    {
        $userId = 'user-123';
        $token = bin2hex(random_bytes(16));
        $tokenData = [
            'token' => $token,
            'action' => UndoAction::DELETE->value,
            'entityType' => 'task',
            'entityId' => 'task-123',
            'previousState' => [],
            'createdAt' => (new \DateTimeImmutable('-120 seconds'))->format(\DateTimeImmutable::ATOM),
            'expiresAt' => (new \DateTimeImmutable('-60 seconds'))->format(\DateTimeImmutable::ATOM),
        ];

        $this->redisService->expects($this->once())
            ->method('getJson')
            ->with("undo:{$userId}:{$token}")
            ->willReturn($tokenData);

        $this->redisService->expects($this->once())
            ->method('delete')
            ->with("undo:{$userId}:{$token}");

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('Undo token has expired', $this->anything());

        $result = $this->undoService->getUndoToken($userId, $token);

        $this->assertNull($result);
    }

    public function testGetUndoTokenReturnsNullOnDeserializationError(): void
    {
        $userId = 'user-123';
        $token = 'some-token';

        $this->redisService->expects($this->once())
            ->method('getJson')
            ->with("undo:{$userId}:{$token}")
            ->willReturn(['invalid' => 'data']);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to deserialize undo token', $this->anything());

        $result = $this->undoService->getUndoToken($userId, $token);

        $this->assertNull($result);
    }

    // ========================================
    // Consume Undo Token Tests
    // ========================================

    public function testConsumeUndoTokenUsesAtomicGetAndDelete(): void
    {
        $userId = 'user-123';
        $token = bin2hex(random_bytes(16));
        $tokenData = [
            'token' => $token,
            'action' => UndoAction::DELETE->value,
            'entityType' => 'task',
            'entityId' => 'task-123',
            'previousState' => ['title' => 'Test'],
            'createdAt' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
            'expiresAt' => (new \DateTimeImmutable('+60 seconds'))->format(\DateTimeImmutable::ATOM),
        ];

        // Uses atomic getJsonAndDelete instead of separate get + delete
        $this->redisService->expects($this->once())
            ->method('getJsonAndDelete')
            ->with("undo:{$userId}:{$token}")
            ->willReturn($tokenData);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Undo token consumed', $this->anything());

        $result = $this->undoService->consumeUndoToken($userId, $token);

        $this->assertInstanceOf(UndoToken::class, $result);
        $this->assertEquals($token, $result->token);
    }

    public function testConsumeUndoTokenIsOneTimeUse(): void
    {
        $userId = 'user-123';
        $token = bin2hex(random_bytes(16));
        $tokenData = [
            'token' => $token,
            'action' => UndoAction::DELETE->value,
            'entityType' => 'task',
            'entityId' => 'task-123',
            'previousState' => [],
            'createdAt' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
            'expiresAt' => (new \DateTimeImmutable('+60 seconds'))->format(\DateTimeImmutable::ATOM),
        ];

        // First call returns data and atomically deletes, second call returns null
        $this->redisService->expects($this->exactly(2))
            ->method('getJsonAndDelete')
            ->with("undo:{$userId}:{$token}")
            ->willReturnOnConsecutiveCalls($tokenData, null);

        // First consume succeeds
        $result1 = $this->undoService->consumeUndoToken($userId, $token);
        $this->assertNotNull($result1);

        // Second consume returns null (token already consumed atomically)
        $result2 = $this->undoService->consumeUndoToken($userId, $token);
        $this->assertNull($result2);
    }

    public function testConsumeUndoTokenReturnsNullWhenNotFound(): void
    {
        $userId = 'user-123';
        $token = 'non-existent';

        $this->redisService->expects($this->once())
            ->method('getJsonAndDelete')
            ->willReturn(null);

        $result = $this->undoService->consumeUndoToken($userId, $token);

        $this->assertNull($result);
    }

    public function testConsumeUndoTokenReturnsNullWhenExpired(): void
    {
        $userId = 'user-123';
        $token = bin2hex(random_bytes(16));
        $tokenData = [
            'token' => $token,
            'action' => UndoAction::DELETE->value,
            'entityType' => 'task',
            'entityId' => 'task-123',
            'previousState' => [],
            'createdAt' => (new \DateTimeImmutable('-120 seconds'))->format(\DateTimeImmutable::ATOM),
            'expiresAt' => (new \DateTimeImmutable('-60 seconds'))->format(\DateTimeImmutable::ATOM),
        ];

        // Token is retrieved and deleted atomically, but it's expired
        $this->redisService->expects($this->once())
            ->method('getJsonAndDelete')
            ->willReturn($tokenData);

        $this->logger->expects($this->once())
            ->method('debug')
            ->with('Consumed undo token was expired', $this->anything());

        $result = $this->undoService->consumeUndoToken($userId, $token);

        // Should return null for expired token (already deleted by atomic operation)
        $this->assertNull($result);
    }

    public function testConsumeUndoTokenReturnsNullOnDeserializationError(): void
    {
        $userId = 'user-123';
        $token = 'some-token';

        $this->redisService->expects($this->once())
            ->method('getJsonAndDelete')
            ->with("undo:{$userId}:{$token}")
            ->willReturn(['invalid' => 'data']);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to deserialize consumed undo token', $this->anything());

        $result = $this->undoService->consumeUndoToken($userId, $token);

        $this->assertNull($result);
    }

    // ========================================
    // Has Valid Token Tests
    // ========================================

    public function testHasValidTokenReturnsTrueWhenTokenExists(): void
    {
        $userId = 'user-123';
        $token = bin2hex(random_bytes(16));
        $tokenData = [
            'token' => $token,
            'action' => UndoAction::DELETE->value,
            'entityType' => 'task',
            'entityId' => 'task-123',
            'previousState' => [],
            'createdAt' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
            'expiresAt' => (new \DateTimeImmutable('+60 seconds'))->format(\DateTimeImmutable::ATOM),
        ];

        $this->redisService->expects($this->once())
            ->method('getJson')
            ->with("undo:{$userId}:{$token}")
            ->willReturn($tokenData);

        $result = $this->undoService->hasValidToken($userId, $token);

        $this->assertTrue($result);
    }

    public function testHasValidTokenReturnsFalseWhenTokenNotFound(): void
    {
        $userId = 'user-123';
        $token = 'non-existent';

        $this->redisService->expects($this->once())
            ->method('getJson')
            ->willReturn(null);

        $result = $this->undoService->hasValidToken($userId, $token);

        $this->assertFalse($result);
    }

    public function testHasValidTokenReturnsFalseWhenTokenExpired(): void
    {
        $userId = 'user-123';
        $token = bin2hex(random_bytes(16));
        $tokenData = [
            'token' => $token,
            'action' => UndoAction::DELETE->value,
            'entityType' => 'task',
            'entityId' => 'task-123',
            'previousState' => [],
            'createdAt' => (new \DateTimeImmutable('-120 seconds'))->format(\DateTimeImmutable::ATOM),
            'expiresAt' => (new \DateTimeImmutable('-60 seconds'))->format(\DateTimeImmutable::ATOM),
        ];

        $this->redisService->expects($this->once())
            ->method('getJson')
            ->willReturn($tokenData);

        $this->redisService->expects($this->once())
            ->method('delete');

        $result = $this->undoService->hasValidToken($userId, $token);

        $this->assertFalse($result);
    }

    // ========================================
    // User Scoping Tests
    // ========================================

    public function testUserCannotAccessOtherUsersTokens(): void
    {
        $user1Id = 'user-1';
        $user2Id = 'user-2';
        $tokenString = 'abc123def456789012345678901234ab'; // Fixed 32-char token

        // When user2 tries to access user1's token, it looks in user2's namespace
        // which won't have the token, so it returns null
        $this->redisService->expects($this->once())
            ->method('getJson')
            ->with("undo:{$user2Id}:{$tokenString}")
            ->willReturn(null);

        // User2 cannot access user1's token because it's stored under user1's key
        $result = $this->undoService->getUndoToken($user2Id, $tokenString);

        $this->assertNull($result);
    }

    public function testTokenKeyIncludesUserId(): void
    {
        $userId = 'specific-user-id';
        $action = UndoAction::DELETE->value;

        $capturedKey = null;

        $this->redisService->expects($this->once())
            ->method('setJson')
            ->willReturnCallback(function ($key, $data, $ttl) use (&$capturedKey) {
                $capturedKey = $key;

                return true;
            });

        $this->undoService->createUndoToken(
            $userId,
            $action,
            'task',
            'task-123',
            []
        );

        $this->assertStringStartsWith("undo:{$userId}:", $capturedKey);
    }

    // ========================================
    // All Action Types Tests
    // ========================================

    public function testCreateUndoTokenSupportsDeleteAction(): void
    {
        $this->redisService->method('setJson')->willReturn(true);

        $result = $this->undoService->createUndoToken(
            'user-123',
            UndoAction::DELETE->value,
            'task',
            'task-123',
            []
        );

        $this->assertNotNull($result);
        $this->assertEquals(UndoAction::DELETE->value, $result->action);
    }

    public function testCreateUndoTokenSupportsUpdateAction(): void
    {
        $this->redisService->method('setJson')->willReturn(true);

        $result = $this->undoService->createUndoToken(
            'user-123',
            UndoAction::UPDATE->value,
            'task',
            'task-123',
            []
        );

        $this->assertNotNull($result);
        $this->assertEquals(UndoAction::UPDATE->value, $result->action);
    }

    public function testCreateUndoTokenSupportsStatusChangeAction(): void
    {
        $this->redisService->method('setJson')->willReturn(true);

        $result = $this->undoService->createUndoToken(
            'user-123',
            UndoAction::STATUS_CHANGE->value,
            'task',
            'task-123',
            []
        );

        $this->assertNotNull($result);
        $this->assertEquals(UndoAction::STATUS_CHANGE->value, $result->action);
    }

    public function testCreateUndoTokenSupportsArchiveAction(): void
    {
        $this->redisService->method('setJson')->willReturn(true);

        $result = $this->undoService->createUndoToken(
            'user-123',
            UndoAction::ARCHIVE->value,
            'project',
            'project-123',
            []
        );

        $this->assertNotNull($result);
        $this->assertEquals(UndoAction::ARCHIVE->value, $result->action);
    }
}
