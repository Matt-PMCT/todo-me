<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ProjectCacheService;
use App\Tests\Unit\UnitTestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class ProjectCacheServiceTest extends UnitTestCase
{
    private ArrayAdapter $cache;
    private ProjectCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new ArrayAdapter();
        $logger = $this->createMock(LoggerInterface::class);
        $this->service = new ProjectCacheService($this->cache, $logger);
    }

    // ========================================
    // buildKey Tests
    // ========================================

    public function testBuildKeyWithAllFalse(): void
    {
        $key = $this->service->buildKey('user-123', false, false);

        $this->assertEquals('project_tree_user-123_0_0', $key);
    }

    public function testBuildKeyWithIncludeArchivedTrue(): void
    {
        $key = $this->service->buildKey('user-123', true, false);

        $this->assertEquals('project_tree_user-123_1_0', $key);
    }

    public function testBuildKeyWithIncludeTaskCountsTrue(): void
    {
        $key = $this->service->buildKey('user-123', false, true);

        $this->assertEquals('project_tree_user-123_0_1', $key);
    }

    public function testBuildKeyWithAllTrue(): void
    {
        $key = $this->service->buildKey('user-123', true, true);

        $this->assertEquals('project_tree_user-123_1_1', $key);
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
        $tree = [['id' => 'project-1', 'name' => 'Test']];

        // Pre-populate all four cache variants
        $this->service->set('user-123', false, false, $tree);
        $this->service->set('user-123', false, true, $tree);
        $this->service->set('user-123', true, false, $tree);
        $this->service->set('user-123', true, true, $tree);

        // Verify they exist
        $this->assertNotNull($this->service->get('user-123', false, false));
        $this->assertNotNull($this->service->get('user-123', false, true));
        $this->assertNotNull($this->service->get('user-123', true, false));
        $this->assertNotNull($this->service->get('user-123', true, true));

        // Invalidate all
        $this->service->invalidate('user-123');

        // Verify all are gone
        $this->assertNull($this->service->get('user-123', false, false));
        $this->assertNull($this->service->get('user-123', false, true));
        $this->assertNull($this->service->get('user-123', true, false));
        $this->assertNull($this->service->get('user-123', true, true));
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

        $this->service->set('user-123', false, true, $tree);

        $result = $this->service->get('user-123', false, true);

        $this->assertEquals($tree, $result);
    }

    // ========================================
    // get Tests
    // ========================================

    public function testGetReturnsNullWhenNotCached(): void
    {
        $result = $this->service->get('user-123', false, true);

        $this->assertNull($result);
    }

    public function testGetReturnsCachedData(): void
    {
        $tree = [
            ['id' => 'project-1', 'name' => 'Project 1'],
        ];

        $this->service->set('user-123', false, true, $tree);

        $result = $this->service->get('user-123', false, true);

        $this->assertEquals($tree, $result);
    }
}
