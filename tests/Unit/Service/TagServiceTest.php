<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Tag;
use App\Entity\User;
use App\Repository\TagRepository;
use App\Service\TagService;
use App\Tests\Unit\UnitTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class TagServiceTest extends UnitTestCase
{
    private TagRepository&MockObject $tagRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private TagService $tagService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->tagService = new TagService($this->tagRepository, $this->entityManager);
        $this->user = $this->createUserWithId('user-123');
    }

    // ========================================
    // findOrCreate Tests
    // ========================================

    public function testFindOrCreateReturnsExistingTag(): void
    {
        $existingTag = $this->createTagWithId('tag-123', 'urgent');

        $this->tagRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'urgent')
            ->willReturn($existingTag);

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $result = $this->tagService->findOrCreate($this->user, 'urgent');

        $this->assertSame($existingTag, $result['tag']);
        $this->assertFalse($result['created']);
    }

    public function testFindOrCreateCreatesNewTagLowercase(): void
    {
        $this->tagRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'newtag')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($tag) {
                return $tag instanceof Tag
                    && $tag->getName() === 'newtag'
                    && $tag->getOwner() === $this->user;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->tagService->findOrCreate($this->user, 'NewTag');

        $this->assertInstanceOf(Tag::class, $result['tag']);
        $this->assertEquals('newtag', $result['tag']->getName());
        $this->assertSame($this->user, $result['tag']->getOwner());
        $this->assertTrue($result['created']);
    }

    public function testFindOrCreateTrimsWhitespace(): void
    {
        $this->tagRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'trimmed')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($tag) {
                return $tag instanceof Tag && $tag->getName() === 'trimmed';
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->tagService->findOrCreate($this->user, '  trimmed  ');

        $this->assertEquals('trimmed', $result['tag']->getName());
        $this->assertTrue($result['created']);
    }

    public function testFindOrCreateMixedCaseFindExisting(): void
    {
        $existingTag = $this->createTagWithId('tag-123', 'myproject');

        $this->tagRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'myproject')
            ->willReturn($existingTag);

        $result = $this->tagService->findOrCreate($this->user, 'MyProject');

        $this->assertSame($existingTag, $result['tag']);
        $this->assertFalse($result['created']);
    }

    // ========================================
    // findByName Tests
    // ========================================

    public function testFindByNameReturnsTagWhenExists(): void
    {
        $existingTag = $this->createTagWithId('tag-123', 'existing');

        $this->tagRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'existing')
            ->willReturn($existingTag);

        $result = $this->tagService->findByName($this->user, 'existing');

        $this->assertSame($existingTag, $result);
    }

    public function testFindByNameReturnsNullWhenNotExists(): void
    {
        $this->tagRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'nonexistent')
            ->willReturn(null);

        $result = $this->tagService->findByName($this->user, 'nonexistent');

        $this->assertNull($result);
    }

    public function testFindByNameIsCaseInsensitive(): void
    {
        $existingTag = $this->createTagWithId('tag-123', 'lowercase');

        $this->tagRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'lowercase')
            ->willReturn($existingTag);

        $result = $this->tagService->findByName($this->user, 'LOWERCASE');

        $this->assertSame($existingTag, $result);
    }

    public function testFindByNameTrimsWhitespace(): void
    {
        $existingTag = $this->createTagWithId('tag-123', 'padded');

        $this->tagRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'padded')
            ->willReturn($existingTag);

        $result = $this->tagService->findByName($this->user, '  padded  ');

        $this->assertSame($existingTag, $result);
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createTagWithId(string $id, string $name): Tag
    {
        $tag = new Tag();
        $tag->setName($name);
        $tag->setOwner($this->user);
        $this->setEntityId($tag, $id);

        return $tag;
    }
}
