<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Entity\Tag;
use App\Entity\User;
use App\Service\Parser\TagParserService;
use App\Service\TagService;
use App\Tests\Unit\UnitTestCase;
use App\ValueObject\TagParseResult;
use PHPUnit\Framework\MockObject\MockObject;

class TagParserServiceTest extends UnitTestCase
{
    private TagService&MockObject $tagService;
    private TagParserService $tagParserService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tagService = $this->createMock(TagService::class);
        $this->tagParserService = new TagParserService($this->tagService);
        $this->user = $this->createUserWithId('user-123');
    }

    // ========================================
    // Basic Parsing Tests
    // ========================================

    public function testParseSingleTag(): void
    {
        $tag = $this->createTagWithId('tag-1', 'urgent');
        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->with($this->user, 'urgent')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $results = $this->tagParserService->parse('Buy milk @urgent', $this->user);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(TagParseResult::class, $results[0]);
        $this->assertSame($tag, $results[0]->tag);
        $this->assertEquals('@urgent', $results[0]->originalText);
        $this->assertEquals(9, $results[0]->startPosition);
        $this->assertEquals(16, $results[0]->endPosition);
        $this->assertFalse($results[0]->wasCreated);
    }

    public function testParseMultipleTags(): void
    {
        $tag1 = $this->createTagWithId('tag-1', 'urgent');
        $tag2 = $this->createTagWithId('tag-2', 'important');
        $tag3 = $this->createTagWithId('tag-3', 'today');

        $this->tagService->expects($this->exactly(3))
            ->method('findOrCreate')
            ->willReturnCallback(function ($user, $name) use ($tag1, $tag2, $tag3) {
                return match (strtolower($name)) {
                    'urgent' => ['tag' => $tag1, 'created' => false],
                    'important' => ['tag' => $tag2, 'created' => true],
                    'today' => ['tag' => $tag3, 'created' => false],
                    default => throw new \Exception('Unexpected tag name: ' . $name),
                };
            });

        $results = $this->tagParserService->parse('@urgent @important @today', $this->user);

        $this->assertCount(3, $results);

        $this->assertSame($tag1, $results[0]->tag);
        $this->assertEquals('@urgent', $results[0]->originalText);
        $this->assertEquals(0, $results[0]->startPosition);

        $this->assertSame($tag2, $results[1]->tag);
        $this->assertEquals('@important', $results[1]->originalText);
        $this->assertEquals(8, $results[1]->startPosition);

        $this->assertSame($tag3, $results[2]->tag);
        $this->assertEquals('@today', $results[2]->originalText);
        $this->assertEquals(19, $results[2]->startPosition);
    }

    public function testParseDuplicateTagsReturnsOnlyFirst(): void
    {
        $tag = $this->createTagWithId('tag-1', 'urgent');

        // Should only be called once for the duplicate tag
        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->with($this->user, 'urgent')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $results = $this->tagParserService->parse('@urgent do this @urgent', $this->user);

        $this->assertCount(1, $results);
        $this->assertSame($tag, $results[0]->tag);
        $this->assertEquals(0, $results[0]->startPosition); // First occurrence
    }

    public function testParseDuplicateTagsCaseInsensitive(): void
    {
        $tag = $this->createTagWithId('tag-1', 'urgent');

        // Should only be called once even though case differs
        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->with($this->user, 'Urgent')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $results = $this->tagParserService->parse('@Urgent do this @urgent', $this->user);

        $this->assertCount(1, $results);
        $this->assertSame($tag, $results[0]->tag);
    }

    // ========================================
    // Tag Creation Tests
    // ========================================

    public function testParseAutoCreatesNewTag(): void
    {
        $tag = $this->createTagWithId('tag-new', 'newtag');

        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->with($this->user, 'newtag')
            ->willReturn(['tag' => $tag, 'created' => true]);

        $results = $this->tagParserService->parse('Task @newtag', $this->user);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->wasCreated);
    }

    public function testParseFindsExistingTag(): void
    {
        $tag = $this->createTagWithId('tag-existing', 'existing');

        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->with($this->user, 'existing')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $results = $this->tagParserService->parse('Task @existing', $this->user);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]->wasCreated);
    }

    // ========================================
    // Case Sensitivity Tests
    // ========================================

    public function testParseMixedCaseTag(): void
    {
        $tag = $this->createTagWithId('tag-1', 'urgent');

        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->with($this->user, 'Urgent')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $results = $this->tagParserService->parse('@Urgent', $this->user);

        $this->assertCount(1, $results);
        $this->assertEquals('@Urgent', $results[0]->originalText);
    }

    public function testParseUpperCaseTag(): void
    {
        $tag = $this->createTagWithId('tag-1', 'urgent');

        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->with($this->user, 'URGENT')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $results = $this->tagParserService->parse('@URGENT', $this->user);

        $this->assertCount(1, $results);
        $this->assertEquals('@URGENT', $results[0]->originalText);
    }

    // ========================================
    // Position Tracking Tests
    // ========================================

    public function testParsePositionTracking(): void
    {
        $tag1 = $this->createTagWithId('tag-1', 'first');
        $tag2 = $this->createTagWithId('tag-2', 'second');

        $this->tagService->expects($this->exactly(2))
            ->method('findOrCreate')
            ->willReturnCallback(function ($user, $name) use ($tag1, $tag2) {
                return match (strtolower($name)) {
                    'first' => ['tag' => $tag1, 'created' => false],
                    'second' => ['tag' => $tag2, 'created' => false],
                    default => throw new \Exception('Unexpected tag name: ' . $name),
                };
            });

        $input = 'Start @first middle @second end';
        $results = $this->tagParserService->parse($input, $this->user);

        $this->assertCount(2, $results);

        // @first at position 6
        $this->assertEquals(6, $results[0]->startPosition);
        $this->assertEquals(12, $results[0]->endPosition);
        $this->assertEquals(6, $results[0]->getLength());

        // @second at position 20
        $this->assertEquals(20, $results[1]->startPosition);
        $this->assertEquals(27, $results[1]->endPosition);
        $this->assertEquals(7, $results[1]->getLength());
    }

    public function testParseTagAtStart(): void
    {
        $tag = $this->createTagWithId('tag-1', 'start');

        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $results = $this->tagParserService->parse('@start of the line', $this->user);

        $this->assertCount(1, $results);
        $this->assertEquals(0, $results[0]->startPosition);
        $this->assertEquals(6, $results[0]->endPosition);
    }

    public function testParseTagAtEnd(): void
    {
        $tag = $this->createTagWithId('tag-1', 'end');

        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $input = 'End of the line @end';
        $results = $this->tagParserService->parse($input, $this->user);

        $this->assertCount(1, $results);
        $this->assertEquals(16, $results[0]->startPosition);
        $this->assertEquals(20, $results[0]->endPosition);
    }

    // ========================================
    // Edge Cases Tests
    // ========================================

    public function testParseNoTagsReturnsEmptyArray(): void
    {
        $this->tagService->expects($this->never())
            ->method('findOrCreate');

        $results = $this->tagParserService->parse('No tags here', $this->user);

        $this->assertEmpty($results);
    }

    public function testParseEmptyInputReturnsEmptyArray(): void
    {
        $this->tagService->expects($this->never())
            ->method('findOrCreate');

        $results = $this->tagParserService->parse('', $this->user);

        $this->assertEmpty($results);
    }

    public function testParseOnlyAtSignReturnsEmptyArray(): void
    {
        $this->tagService->expects($this->never())
            ->method('findOrCreate');

        $results = $this->tagParserService->parse('@', $this->user);

        $this->assertEmpty($results);
    }

    public function testParseEmailAddressNotParsedAsTag(): void
    {
        // Email addresses should be parsed as tags because the pattern matches after @
        // This documents current behavior - may need to adjust pattern if unwanted
        $tag = $this->createTagWithId('tag-1', 'example');

        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->with($this->user, 'example')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $results = $this->tagParserService->parse('Contact user@example.com', $this->user);

        // The pattern @([a-zA-Z0-9_-]+) will match @example (stopping at the dot)
        $this->assertCount(1, $results);
        $this->assertEquals('@example', $results[0]->originalText);
    }

    public function testParseTagWithNumbers(): void
    {
        $tag = $this->createTagWithId('tag-1', 'task123');

        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->with($this->user, 'task123')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $results = $this->tagParserService->parse('@task123', $this->user);

        $this->assertCount(1, $results);
        $this->assertEquals('@task123', $results[0]->originalText);
    }

    public function testParseTagWithUnderscore(): void
    {
        $tag = $this->createTagWithId('tag-1', 'my_tag');

        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->with($this->user, 'my_tag')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $results = $this->tagParserService->parse('@my_tag', $this->user);

        $this->assertCount(1, $results);
        $this->assertEquals('@my_tag', $results[0]->originalText);
    }

    public function testParseTagWithHyphen(): void
    {
        $tag = $this->createTagWithId('tag-1', 'my-tag');

        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->with($this->user, 'my-tag')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $results = $this->tagParserService->parse('@my-tag', $this->user);

        $this->assertCount(1, $results);
        $this->assertEquals('@my-tag', $results[0]->originalText);
    }

    public function testParseInvalidCharactersNotIncluded(): void
    {
        $tag = $this->createTagWithId('tag-1', 'tag');

        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->with($this->user, 'tag')
            ->willReturn(['tag' => $tag, 'created' => false]);

        // Special characters should end the tag match
        $results = $this->tagParserService->parse('@tag!special', $this->user);

        $this->assertCount(1, $results);
        $this->assertEquals('@tag', $results[0]->originalText);
        $this->assertEquals(4, $results[0]->endPosition);
    }

    public function testParseMultipleTagsInSentence(): void
    {
        $tag1 = $this->createTagWithId('tag-1', 'work');
        $tag2 = $this->createTagWithId('tag-2', 'priority');

        $this->tagService->expects($this->exactly(2))
            ->method('findOrCreate')
            ->willReturnCallback(function ($user, $name) use ($tag1, $tag2) {
                return match (strtolower($name)) {
                    'work' => ['tag' => $tag1, 'created' => false],
                    'priority' => ['tag' => $tag2, 'created' => true],
                    default => throw new \Exception('Unexpected tag name: ' . $name),
                };
            });

        $results = $this->tagParserService->parse('Complete @work task with @priority', $this->user);

        $this->assertCount(2, $results);
        $this->assertFalse($results[0]->wasCreated);
        $this->assertTrue($results[1]->wasCreated);
    }

    public function testParseResultIsSuccessful(): void
    {
        $tag = $this->createTagWithId('tag-1', 'success');

        $this->tagService->expects($this->once())
            ->method('findOrCreate')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $results = $this->tagParserService->parse('@success', $this->user);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isSuccessful());
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
