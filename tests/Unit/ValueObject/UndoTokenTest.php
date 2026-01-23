<?php

declare(strict_types=1);

namespace App\Tests\Unit\ValueObject;

use App\Enum\UndoAction;
use App\Tests\Unit\UnitTestCase;
use App\ValueObject\UndoToken;

class UndoTokenTest extends UnitTestCase
{
    // ========================================
    // Create Factory Method Tests
    // ========================================

    public function testCreateReturnsUndoTokenInstance(): void
    {
        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: ['title' => 'Test'],
        );

        $this->assertInstanceOf(UndoToken::class, $token);
    }

    public function testCreateSetsAllProperties(): void
    {
        $action = UndoAction::UPDATE->value;
        $entityType = 'project';
        $entityId = 'project-456';
        $previousState = ['name' => 'Old Name', 'description' => 'Old Desc'];

        $token = UndoToken::create(
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            previousState: $previousState,
        );

        $this->assertEquals($action, $token->action);
        $this->assertEquals($entityType, $token->entityType);
        $this->assertEquals($entityId, $token->entityId);
        $this->assertEquals($previousState, $token->previousState);
    }

    public function testCreateSetsTimestamps(): void
    {
        $before = new \DateTimeImmutable();
        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
        );
        $after = new \DateTimeImmutable();

        $this->assertInstanceOf(\DateTimeImmutable::class, $token->createdAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $token->expiresAt);
        $this->assertGreaterThanOrEqual($before, $token->createdAt);
        $this->assertLessThanOrEqual($after, $token->createdAt);
    }

    public function testCreateUsesDefaultTtl(): void
    {
        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
        );

        // Default TTL is 60 seconds
        $expectedExpiry = $token->createdAt->modify('+60 seconds');
        $this->assertEquals($expectedExpiry, $token->expiresAt);
    }

    public function testCreateUsesCustomTtl(): void
    {
        $customTtl = 120;

        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
            ttl: $customTtl,
        );

        $expectedExpiry = $token->createdAt->modify("+{$customTtl} seconds");
        $this->assertEquals($expectedExpiry, $token->expiresAt);
    }

    // ========================================
    // Token Generation Tests
    // ========================================

    public function testTokenIs32Characters(): void
    {
        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
        );

        $this->assertEquals(32, strlen($token->token));
    }

    public function testTokenIsHexadecimal(): void
    {
        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
        );

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token->token);
    }

    public function testTokensAreUnique(): void
    {
        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $token = UndoToken::create(
                action: UndoAction::DELETE->value,
                entityType: 'task',
                entityId: 'task-123',
                previousState: [],
            );
            $tokens[] = $token->token;
        }

        // All tokens should be unique
        $uniqueTokens = array_unique($tokens);
        $this->assertCount(100, $uniqueTokens);
    }

    // ========================================
    // Serialization Tests (toArray / fromArray)
    // ========================================

    public function testToArrayReturnsCorrectStructure(): void
    {
        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: ['title' => 'Test'],
        );

        $array = $token->toArray();

        $this->assertArrayHasKey('token', $array);
        $this->assertArrayHasKey('action', $array);
        $this->assertArrayHasKey('entityType', $array);
        $this->assertArrayHasKey('entityId', $array);
        $this->assertArrayHasKey('previousState', $array);
        $this->assertArrayHasKey('createdAt', $array);
        $this->assertArrayHasKey('expiresAt', $array);
    }

    public function testToArraySerializesAllData(): void
    {
        $previousState = ['title' => 'Test', 'priority' => 3];
        $token = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'project',
            entityId: 'project-456',
            previousState: $previousState,
        );

        $array = $token->toArray();

        $this->assertEquals($token->token, $array['token']);
        $this->assertEquals(UndoAction::UPDATE->value, $array['action']);
        $this->assertEquals('project', $array['entityType']);
        $this->assertEquals('project-456', $array['entityId']);
        $this->assertEquals($previousState, $array['previousState']);
    }

    public function testToArrayFormatsTimestampsAsAtom(): void
    {
        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
        );

        $array = $token->toArray();

        // Timestamps should be in ATOM format
        $this->assertEquals(
            $token->createdAt->format(\DateTimeImmutable::ATOM),
            $array['createdAt']
        );
        $this->assertEquals(
            $token->expiresAt->format(\DateTimeImmutable::ATOM),
            $array['expiresAt']
        );
    }

    public function testFromArrayDeserializesCorrectly(): void
    {
        $data = [
            'token' => bin2hex(random_bytes(16)),
            'action' => UndoAction::DELETE->value,
            'entityType' => 'task',
            'entityId' => 'task-123',
            'previousState' => ['title' => 'Test'],
            'createdAt' => '2024-01-15T10:00:00+00:00',
            'expiresAt' => '2024-01-15T10:01:00+00:00',
        ];

        $token = UndoToken::fromArray($data);

        $this->assertEquals($data['token'], $token->token);
        $this->assertEquals($data['action'], $token->action);
        $this->assertEquals($data['entityType'], $token->entityType);
        $this->assertEquals($data['entityId'], $token->entityId);
        $this->assertEquals($data['previousState'], $token->previousState);
        $this->assertEquals(
            new \DateTimeImmutable($data['createdAt']),
            $token->createdAt
        );
        $this->assertEquals(
            new \DateTimeImmutable($data['expiresAt']),
            $token->expiresAt
        );
    }

    public function testToArrayAndFromArrayAreInverses(): void
    {
        $original = UndoToken::create(
            action: UndoAction::STATUS_CHANGE->value,
            entityType: 'task',
            entityId: 'task-789',
            previousState: ['status' => 'pending', 'completedAt' => null],
            ttl: 120,
        );

        $array = $original->toArray();
        $restored = UndoToken::fromArray($array);

        $this->assertEquals($original->token, $restored->token);
        $this->assertEquals($original->action, $restored->action);
        $this->assertEquals($original->entityType, $restored->entityType);
        $this->assertEquals($original->entityId, $restored->entityId);
        $this->assertEquals($original->previousState, $restored->previousState);
        $this->assertEquals(
            $original->createdAt->format(\DateTimeImmutable::ATOM),
            $restored->createdAt->format(\DateTimeImmutable::ATOM)
        );
        $this->assertEquals(
            $original->expiresAt->format(\DateTimeImmutable::ATOM),
            $restored->expiresAt->format(\DateTimeImmutable::ATOM)
        );
    }

    // ========================================
    // Is Expired Tests
    // ========================================

    public function testIsExpiredReturnsFalseForFreshToken(): void
    {
        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
            ttl: 60,
        );

        $this->assertFalse($token->isExpired());
    }

    public function testIsExpiredReturnsTrueForExpiredToken(): void
    {
        $data = [
            'token' => bin2hex(random_bytes(16)),
            'action' => UndoAction::DELETE->value,
            'entityType' => 'task',
            'entityId' => 'task-123',
            'previousState' => [],
            'createdAt' => (new \DateTimeImmutable('-2 minutes'))->format(\DateTimeImmutable::ATOM),
            'expiresAt' => (new \DateTimeImmutable('-1 minute'))->format(\DateTimeImmutable::ATOM),
        ];

        $token = UndoToken::fromArray($data);

        $this->assertTrue($token->isExpired());
    }

    public function testIsExpiredReturnsTrueWhenExactlyExpired(): void
    {
        $data = [
            'token' => bin2hex(random_bytes(16)),
            'action' => UndoAction::DELETE->value,
            'entityType' => 'task',
            'entityId' => 'task-123',
            'previousState' => [],
            'createdAt' => (new \DateTimeImmutable('-61 seconds'))->format(\DateTimeImmutable::ATOM),
            'expiresAt' => (new \DateTimeImmutable('-1 second'))->format(\DateTimeImmutable::ATOM),
        ];

        $token = UndoToken::fromArray($data);

        $this->assertTrue($token->isExpired());
    }

    // ========================================
    // Get Remaining Seconds Tests
    // ========================================

    public function testGetRemainingSecondsReturnsPositiveForFreshToken(): void
    {
        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
            ttl: 60,
        );

        $remaining = $token->getRemainingSeconds();

        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual(60, $remaining);
    }

    public function testGetRemainingSecondsReturnsZeroForExpiredToken(): void
    {
        $data = [
            'token' => bin2hex(random_bytes(16)),
            'action' => UndoAction::DELETE->value,
            'entityType' => 'task',
            'entityId' => 'task-123',
            'previousState' => [],
            'createdAt' => (new \DateTimeImmutable('-2 minutes'))->format(\DateTimeImmutable::ATOM),
            'expiresAt' => (new \DateTimeImmutable('-1 minute'))->format(\DateTimeImmutable::ATOM),
        ];

        $token = UndoToken::fromArray($data);

        $this->assertEquals(0, $token->getRemainingSeconds());
    }

    public function testGetRemainingSecondsNeverNegative(): void
    {
        $data = [
            'token' => bin2hex(random_bytes(16)),
            'action' => UndoAction::DELETE->value,
            'entityType' => 'task',
            'entityId' => 'task-123',
            'previousState' => [],
            'createdAt' => (new \DateTimeImmutable('-1 hour'))->format(\DateTimeImmutable::ATOM),
            'expiresAt' => (new \DateTimeImmutable('-50 minutes'))->format(\DateTimeImmutable::ATOM),
        ];

        $token = UndoToken::fromArray($data);

        // Even though token expired long ago, should return 0 not negative
        $this->assertEquals(0, $token->getRemainingSeconds());
    }

    public function testGetRemainingSecondsDecreases(): void
    {
        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
            ttl: 60,
        );

        $first = $token->getRemainingSeconds();
        usleep(100000); // 100ms
        $second = $token->getRemainingSeconds();

        $this->assertGreaterThanOrEqual($second, $first);
    }

    // ========================================
    // Get Action Enum Tests
    // ========================================

    public function testGetActionEnumReturnsCorrectEnum(): void
    {
        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
        );

        $this->assertEquals(UndoAction::DELETE, $token->getActionEnum());
    }

    public function testGetActionEnumForAllActions(): void
    {
        foreach (UndoAction::cases() as $action) {
            $token = UndoToken::create(
                action: $action->value,
                entityType: 'task',
                entityId: 'task-123',
                previousState: [],
            );

            $this->assertEquals($action, $token->getActionEnum());
        }
    }

    public function testGetActionEnumReturnsNullForInvalidAction(): void
    {
        $data = [
            'token' => bin2hex(random_bytes(16)),
            'action' => 'invalid_action',
            'entityType' => 'task',
            'entityId' => 'task-123',
            'previousState' => [],
            'createdAt' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
            'expiresAt' => (new \DateTimeImmutable('+60 seconds'))->format(\DateTimeImmutable::ATOM),
        ];

        $token = UndoToken::fromArray($data);

        $this->assertNull($token->getActionEnum());
    }

    // ========================================
    // Immutability Tests
    // ========================================

    public function testUndoTokenIsReadonly(): void
    {
        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: ['title' => 'Test'],
        );

        // The class is marked as readonly, so properties cannot be modified
        $reflection = new \ReflectionClass($token);
        $this->assertTrue($reflection->isReadOnly());
    }

    // ========================================
    // Edge Cases Tests
    // ========================================

    public function testCreateWithEmptyPreviousState(): void
    {
        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
        );

        $this->assertEquals([], $token->previousState);
    }

    public function testCreateWithComplexPreviousState(): void
    {
        $previousState = [
            'title' => 'Test Task',
            'description' => 'A description',
            'tags' => ['urgent', 'important'],
            'metadata' => [
                'nested' => ['key' => 'value'],
                'number' => 42,
            ],
        ];

        $token = UndoToken::create(
            action: UndoAction::UPDATE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: $previousState,
        );

        $this->assertEquals($previousState, $token->previousState);

        // Verify round-trip
        $restored = UndoToken::fromArray($token->toArray());
        $this->assertEquals($previousState, $restored->previousState);
    }

    public function testCreateWithZeroTtl(): void
    {
        $token = UndoToken::create(
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: 'task-123',
            previousState: [],
            ttl: 0,
        );

        // Token with 0 TTL should be immediately expired
        $this->assertTrue($token->isExpired());
        $this->assertEquals(0, $token->getRemainingSeconds());
    }
}
