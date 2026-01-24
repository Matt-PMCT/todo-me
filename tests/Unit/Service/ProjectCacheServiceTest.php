<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ProjectCacheService;
use App\Tests\Unit\UnitTestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ProjectCacheServiceTest extends UnitTestCase
{
    private CacheInterface $cache;
    private ProjectCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->createMock(CacheInterface::class);
        $this->service = new ProjectCacheService($this->cache);
    }

    // ========================================
    // buildKey Tests
    // ========================================

    public function testBuildKeyWithAllFalse(): void
    {
        $key = $this->service->buildKey('user-123', false, false);

        $this->assertEquals('project_tree:user-123:0:0', $key);
    }

    public function testBuildKeyWithIncludeArchivedTrue(): void
    {
        $key = $this->service->buildKey('user-123', true, false);

        $this->assertEquals('project_tree:user-123:1:0', $key);
    }

    public function testBuildKeyWithIncludeTaskCountsTrue(): void
    {
        $key = $this->service->buildKey('user-123', false, true);

        $this->assertEquals('project_tree:user-123:0:1', $key);
    }

    public function testBuildKeyWithAllTrue(): void
    {
        $key = $this->service->buildKey('user-123', true, true);

        $this->assertEquals('project_tree:user-123:1:1', $key);
    }

    public function testBuildKeyWithDifferentUserId(): void
    {
        $key1 = $this->service->buildKey('user-1', false, false);
        $key2 = $this->service->buildKey('user-2', false, false);

        $this->assertNotEquals($key1, $key2);
        $this->assertStringContainsString('user-1', $key1);
        $this->assertStringContainsString('user-2', $key2);
    }

    // ========================================
    // invalidate Tests
    // ========================================

    public function testInvalidateDeletesAllFourVariants(): void
    {
        $deletedKeys = [];

        $this->cache->expects($this->exactly(4))
            ->method('delete')
            ->willReturnCallback(function ($key) use (&$deletedKeys) {
                $deletedKeys[] = $key;
                return true;
            });

        $this->service->invalidate('user-123');

        $this->assertContains('project_tree:user-123:0:0', $deletedKeys);
        $this->assertContains('project_tree:user-123:0:1', $deletedKeys);
        $this->assertContains('project_tree:user-123:1:0', $deletedKeys);
        $this->assertContains('project_tree:user-123:1:1', $deletedKeys);
    }

    public function testInvalidateHandlesCacheException(): void
    {
        $this->cache->expects($this->exactly(4))
            ->method('delete')
            ->willThrowException(new \Exception('Cache error'));

        // Should not throw - exceptions are silently caught
        $this->service->invalidate('user-123');

        $this->assertTrue(true); // If we got here, no exception was thrown
    }

    // ========================================
    // set Tests
    // ========================================

    public function testSetStoresTreeInCache(): void
    {
        $tree = [
            ['id' => 'project-1', 'name' => 'Project 1'],
            ['id' => 'project-2', 'name' => 'Project 2'],
        ];

        $this->cache->expects($this->once())
            ->method('delete')
            ->with('project_tree:user-123:0:1');

        $this->cache->expects($this->once())
            ->method('get')
            ->with('project_tree:user-123:0:1')
            ->willReturnCallback(function ($key, $callback) use ($tree) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())
                    ->method('expiresAfter')
                    ->with(300);
                return $callback($item);
            });

        $this->service->set('user-123', false, true, $tree);
    }

    // ========================================
    // get Tests
    // ========================================

    public function testGetReturnsNullWhenNotCached(): void
    {
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(['__not_found__' => true]);

        $result = $this->service->get('user-123', false, true);

        $this->assertNull($result);
    }

    public function testGetReturnsCachedData(): void
    {
        $tree = [
            ['id' => 'project-1', 'name' => 'Project 1'],
        ];

        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn($tree);

        $result = $this->service->get('user-123', false, true);

        $this->assertEquals($tree, $result);
    }

    public function testGetReturnsNullOnException(): void
    {
        $this->cache->expects($this->once())
            ->method('get')
            ->willThrowException(new \Exception('Cache error'));

        $result = $this->service->get('user-123', false, true);

        $this->assertNull($result);
    }
}
