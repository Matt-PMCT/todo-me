<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Repository\TagRepository;
use App\Tests\Integration\IntegrationTestCase;

/**
 * Integration tests for TagRepository.
 *
 * Tests query building logic including:
 * - Owner scoping (multi-tenant isolation)
 * - Case-insensitive search
 * - Prefix search for autocomplete
 */
class TagRepositoryTest extends IntegrationTestCase
{
    private TagRepository $tagRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tagRepository = static::getContainer()->get(TagRepository::class);
    }

    // ========================================
    // Owner Scoping Tests
    // ========================================

    public function testFindByOwnerReturnsOnlyOwnedTags(): void
    {
        $user1 = $this->createUser('user1-tag@example.com');
        $user2 = $this->createUser('user2-tag@example.com');

        $this->createTag($user1, 'urgent');
        $this->createTag($user1, 'home');
        $this->createTag($user2, 'work');

        $results = $this->tagRepository->findByOwner($user1);

        $this->assertCount(2, $results);
        $names = array_map(fn($t) => $t->getName(), $results);
        $this->assertContains('urgent', $names);
        $this->assertContains('home', $names);
        $this->assertNotContains('work', $names);
    }

    public function testFindOneByOwnerAndIdReturnsOnlyOwnedTag(): void
    {
        $user1 = $this->createUser('owner1-tag@example.com');
        $user2 = $this->createUser('owner2-tag@example.com');

        $tag1 = $this->createTag($user1, 'urgent');
        $tag2 = $this->createTag($user2, 'important');

        $found = $this->tagRepository->findOneByOwnerAndId($user1, $tag1->getId());
        $this->assertNotNull($found);
        $this->assertEquals('urgent', $found->getName());

        $notFound = $this->tagRepository->findOneByOwnerAndId($user1, $tag2->getId());
        $this->assertNull($notFound);
    }

    public function testFindOneByOwnerAndName(): void
    {
        $user = $this->createUser('find-name@example.com');
        $this->createTag($user, 'urgent');
        $this->createTag($user, 'home');

        $result = $this->tagRepository->findOneByOwnerAndName($user, 'urgent');

        $this->assertNotNull($result);
        $this->assertEquals('urgent', $result->getName());
    }

    public function testFindOneByOwnerAndNameNotFound(): void
    {
        $user = $this->createUser('find-name-none@example.com');
        $this->createTag($user, 'urgent');

        $result = $this->tagRepository->findOneByOwnerAndName($user, 'nonexistent');

        $this->assertNull($result);
    }

    // ========================================
    // Batch Find Tests
    // ========================================

    public function testFindByOwnerAndNames(): void
    {
        $user = $this->createUser('batch-names@example.com');
        $this->createTag($user, 'urgent');
        $this->createTag($user, 'home');
        $this->createTag($user, 'work');
        $this->createTag($user, 'personal');

        $results = $this->tagRepository->findByOwnerAndNames($user, ['urgent', 'home', 'nonexistent']);

        $this->assertCount(2, $results);
        $names = array_map(fn($t) => $t->getName(), $results);
        $this->assertContains('urgent', $names);
        $this->assertContains('home', $names);
    }

    public function testFindByOwnerAndNamesWithEmptyArray(): void
    {
        $user = $this->createUser('batch-empty@example.com');
        $this->createTag($user, 'urgent');

        // Note: This may return all tags or empty depending on implementation
        // Testing the method doesn't error
        $results = $this->tagRepository->findByOwnerAndNames($user, []);
        $this->assertIsArray($results);
    }

    // ========================================
    // Case-Insensitive Search Tests
    // ========================================

    public function testFindByNameInsensitive(): void
    {
        $user = $this->createUser('case-search@example.com');
        $this->createTag($user, 'URGENT');
        $this->createTag($user, 'Home');

        $result = $this->tagRepository->findByNameInsensitive($user, 'urgent');

        $this->assertNotNull($result);
        $this->assertEquals('URGENT', $result->getName());
    }

    public function testFindByNameInsensitiveWithMixedCase(): void
    {
        $user = $this->createUser('mixed-case@example.com');
        $this->createTag($user, 'WorkMeeting');

        $result = $this->tagRepository->findByNameInsensitive($user, 'workmeeting');

        $this->assertNotNull($result);
        $this->assertEquals('WorkMeeting', $result->getName());
    }

    public function testFindByNameInsensitiveNotFound(): void
    {
        $user = $this->createUser('case-notfound@example.com');
        $this->createTag($user, 'urgent');

        $result = $this->tagRepository->findByNameInsensitive($user, 'nonexistent');

        $this->assertNull($result);
    }

    // ========================================
    // Prefix Search Tests (Autocomplete)
    // ========================================

    public function testSearchByPrefix(): void
    {
        $user = $this->createUser('prefix@example.com');
        $this->createTag($user, 'work');
        $this->createTag($user, 'work-meeting');
        $this->createTag($user, 'work-project');
        $this->createTag($user, 'home');

        $results = $this->tagRepository->searchByPrefix($user, 'work');

        $this->assertCount(3, $results);
    }

    public function testSearchByPrefixIsCaseInsensitive(): void
    {
        $user = $this->createUser('prefix-case@example.com');
        $this->createTag($user, 'WORK');
        $this->createTag($user, 'Work-Meeting');
        $this->createTag($user, 'home');

        $results = $this->tagRepository->searchByPrefix($user, 'work');

        $this->assertCount(2, $results);
    }

    public function testSearchByPrefixRespectsLimit(): void
    {
        $user = $this->createUser('prefix-limit@example.com');
        for ($i = 1; $i <= 20; $i++) {
            $this->createTag($user, "tag$i");
        }

        $results = $this->tagRepository->searchByPrefix($user, 'tag', 5);

        $this->assertCount(5, $results);
    }

    public function testSearchByPrefixOrdersByName(): void
    {
        $user = $this->createUser('prefix-order@example.com');
        $this->createTag($user, 'work-z');
        $this->createTag($user, 'work-a');
        $this->createTag($user, 'work-m');

        $results = $this->tagRepository->searchByPrefix($user, 'work');

        $this->assertEquals('work-a', $results[0]->getName());
        $this->assertEquals('work-m', $results[1]->getName());
        $this->assertEquals('work-z', $results[2]->getName());
    }

    public function testSearchByPrefixNoMatches(): void
    {
        $user = $this->createUser('prefix-none@example.com');
        $this->createTag($user, 'urgent');
        $this->createTag($user, 'home');

        $results = $this->tagRepository->searchByPrefix($user, 'xyz');

        $this->assertEmpty($results);
    }

    // ========================================
    // Ordering Tests
    // ========================================

    public function testFindByOwnerOrdersByName(): void
    {
        $user = $this->createUser('order@example.com');
        $this->createTag($user, 'zebra');
        $this->createTag($user, 'alpha');
        $this->createTag($user, 'middle');

        $results = $this->tagRepository->findByOwner($user);

        $this->assertEquals('alpha', $results[0]->getName());
        $this->assertEquals('middle', $results[1]->getName());
        $this->assertEquals('zebra', $results[2]->getName());
    }
}
