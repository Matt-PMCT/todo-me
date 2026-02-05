<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\RedisService;
use App\Service\SyncService;
use App\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SyncServiceTest extends UnitTestCase
{
    private RedisService&MockObject $redisService;
    private SyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redisService = $this->createMock(RedisService::class);
        $this->syncService = new SyncService($this->redisService);
    }

    public function testRecordChangeIncrementsVersionAndStoresChange(): void
    {
        $user = $this->createUserWithId('user-abc');

        $this->redisService->expects($this->once())
            ->method('increment')
            ->with('sync:version:user-abc')
            ->willReturn(1);

        $this->redisService->expects($this->once())
            ->method('listPush')
            ->with(
                'sync:changes:user-abc',
                $this->callback(function (string $json) {
                    $data = json_decode($json, true);

                    return $data['version'] === 1
                        && $data['entityType'] === 'task'
                        && $data['action'] === 'created'
                        && $data['entityId'] === 'task-123'
                        && $data['originTabId'] === 'tab-1';
                })
            );

        $this->redisService->expects($this->once())
            ->method('listTrim')
            ->with('sync:changes:user-abc', -100, -1);

        $expireCalls = [];
        $this->redisService->expects($this->exactly(2))
            ->method('expire')
            ->willReturnCallback(function (string $key, int $ttl) use (&$expireCalls): bool {
                $expireCalls[] = [$key, $ttl];

                return true;
            });

        $this->syncService->recordChange($user, 'task', 'created', 'task-123', 'tab-1');

        $this->assertSame([
            ['sync:version:user-abc', 300],
            ['sync:changes:user-abc', 300],
        ], $expireCalls);
    }

    public function testRecordChangeWithNullTabId(): void
    {
        $user = $this->createUserWithId('user-abc');

        $this->redisService->expects($this->once())
            ->method('increment')
            ->willReturn(1);

        $this->redisService->expects($this->once())
            ->method('listPush')
            ->with(
                $this->anything(),
                $this->callback(function (string $json) {
                    $data = json_decode($json, true);

                    return $data['originTabId'] === null;
                })
            );

        $this->redisService->method('listTrim');
        $this->redisService->method('expire');

        $this->syncService->recordChange($user, 'task', 'created', 'task-123');
    }

    public function testGetCurrentVersionReturnsValueFromRedis(): void
    {
        $user = $this->createUserWithId('user-abc');

        $this->redisService->expects($this->once())
            ->method('get')
            ->with('sync:version:user-abc')
            ->willReturn('42');

        $version = $this->syncService->getCurrentVersion($user);

        $this->assertSame(42, $version);
    }

    public function testGetCurrentVersionReturnsZeroWhenNoVersion(): void
    {
        $user = $this->createUserWithId('user-abc');

        $this->redisService->expects($this->once())
            ->method('get')
            ->with('sync:version:user-abc')
            ->willReturn(null);

        $version = $this->syncService->getCurrentVersion($user);

        $this->assertSame(0, $version);
    }

    public function testGetChangesSinceReturnsChangesAfterVersion(): void
    {
        $user = $this->createUserWithId('user-abc');

        $this->redisService->method('get')
            ->with('sync:version:user-abc')
            ->willReturn('3');

        $this->redisService->method('listRange')
            ->with('sync:changes:user-abc', 0, -1)
            ->willReturn([
                json_encode(['version' => 1, 'entityType' => 'task', 'action' => 'created', 'entityId' => 'a']),
                json_encode(['version' => 2, 'entityType' => 'task', 'action' => 'updated', 'entityId' => 'b']),
                json_encode(['version' => 3, 'entityType' => 'project', 'action' => 'created', 'entityId' => 'c']),
            ]);

        $result = $this->syncService->getChangesSince($user, 1);

        $this->assertSame(3, $result['version']);
        $this->assertCount(2, $result['changes']);
        $this->assertSame('b', $result['changes'][0]['entityId']);
        $this->assertSame('c', $result['changes'][1]['entityId']);
    }

    public function testGetChangesSinceReturnsEmptyWhenUpToDate(): void
    {
        $user = $this->createUserWithId('user-abc');

        $this->redisService->method('get')
            ->with('sync:version:user-abc')
            ->willReturn('5');

        $result = $this->syncService->getChangesSince($user, 5);

        $this->assertSame(5, $result['version']);
        $this->assertEmpty($result['changes']);
    }

    public function testGetChangesSinceReturnsEmptyWhenAheadOfServer(): void
    {
        $user = $this->createUserWithId('user-abc');

        $this->redisService->method('get')
            ->with('sync:version:user-abc')
            ->willReturn('3');

        $result = $this->syncService->getChangesSince($user, 10);

        $this->assertSame(3, $result['version']);
        $this->assertEmpty($result['changes']);
    }

    public function testRecordChangeSkipsWhenUserIdIsNull(): void
    {
        $user = new \App\Entity\User();
        // User without ID (not persisted)

        $this->redisService->expects($this->never())
            ->method('increment');

        $this->syncService->recordChange($user, 'task', 'created', 'task-123');
    }

    public function testGetCurrentVersionReturnsZeroForNullUserId(): void
    {
        $user = new \App\Entity\User();

        $this->redisService->expects($this->never())
            ->method('get');

        $version = $this->syncService->getCurrentVersion($user);
        $this->assertSame(0, $version);
    }

    public function testRecordChangeSetsVersionKeyTtl(): void
    {
        $user = $this->createUserWithId('user-ttl');

        $this->redisService->method('increment')
            ->willReturn(5);

        $this->redisService->method('listPush');
        $this->redisService->method('listTrim');

        $expiredKeys = [];
        $this->redisService->expects($this->exactly(2))
            ->method('expire')
            ->willReturnCallback(function (string $key, int $ttl) use (&$expiredKeys): bool {
                $expiredKeys[$key] = $ttl;

                return true;
            });

        $this->syncService->recordChange($user, 'task', 'updated', 'task-456', 'tab-x');

        // Both version and changes keys get the same TTL
        $this->assertArrayHasKey('sync:version:user-ttl', $expiredKeys);
        $this->assertArrayHasKey('sync:changes:user-ttl', $expiredKeys);
        $this->assertSame(300, $expiredKeys['sync:version:user-ttl']);
        $this->assertSame(300, $expiredKeys['sync:changes:user-ttl']);
    }
}
