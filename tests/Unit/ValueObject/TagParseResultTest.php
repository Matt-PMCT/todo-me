<?php

declare(strict_types=1);

namespace App\Tests\Unit\ValueObject;

use App\Entity\Tag;
use App\Tests\Unit\UnitTestCase;
use App\ValueObject\TagParseResult;

class TagParseResultTest extends UnitTestCase
{
    // ========================================
    // Create Tests
    // ========================================

    public function testCreateWithTag(): void
    {
        $user = $this->createUserWithId();
        $tag = new Tag();
        $tag->setName('urgent');
        $tag->setOwner($user);
        $this->setEntityId($tag, 'tag-123');

        $result = TagParseResult::create(
            tag: $tag,
            originalText: '@urgent',
            startPosition: 5,
            endPosition: 12,
            wasCreated: true,
        );

        $this->assertSame($tag, $result->tag);
        $this->assertEquals('@urgent', $result->originalText);
        $this->assertEquals(5, $result->startPosition);
        $this->assertEquals(12, $result->endPosition);
        $this->assertTrue($result->wasCreated);
    }

    public function testCreateWithNullTag(): void
    {
        $result = TagParseResult::create(
            tag: null,
            originalText: '@invalid',
            startPosition: 0,
            endPosition: 8,
            wasCreated: false,
        );

        $this->assertNull($result->tag);
        $this->assertEquals('@invalid', $result->originalText);
        $this->assertFalse($result->wasCreated);
    }

    // ========================================
    // toArray Tests
    // ========================================

    public function testToArrayWithTag(): void
    {
        $user = $this->createUserWithId();
        $tag = new Tag();
        $tag->setName('myTag');
        $tag->setColor('#FF5733');
        $tag->setOwner($user);
        $this->setEntityId($tag, 'tag-456');

        $result = TagParseResult::create(
            tag: $tag,
            originalText: '@myTag',
            startPosition: 10,
            endPosition: 16,
            wasCreated: false,
        );

        $array = $result->toArray();

        $this->assertEquals('tag-456', $array['tagId']);
        $this->assertEquals('myTag', $array['tagName']);
        $this->assertEquals('#FF5733', $array['tagColor']);
        $this->assertEquals('@myTag', $array['originalText']);
        $this->assertEquals(10, $array['startPosition']);
        $this->assertEquals(16, $array['endPosition']);
        $this->assertFalse($array['wasCreated']);
    }

    public function testToArrayWithNullTag(): void
    {
        $result = TagParseResult::create(
            tag: null,
            originalText: '@test',
            startPosition: 0,
            endPosition: 5,
            wasCreated: false,
        );

        $array = $result->toArray();

        $this->assertNull($array['tagId']);
        $this->assertNull($array['tagName']);
        $this->assertNull($array['tagColor']);
        $this->assertEquals('@test', $array['originalText']);
    }

    // ========================================
    // fromArray Tests
    // ========================================

    public function testFromArray(): void
    {
        $data = [
            'originalText' => '@parsed',
            'startPosition' => 3,
            'endPosition' => 10,
            'wasCreated' => true,
            'tagId' => 'tag-789',
            'tagName' => 'parsed',
            'tagColor' => '#123456',
        ];

        $result = TagParseResult::fromArray($data);

        // Note: fromArray doesn't hydrate the Tag entity
        $this->assertNull($result->tag);
        $this->assertEquals('@parsed', $result->originalText);
        $this->assertEquals(3, $result->startPosition);
        $this->assertEquals(10, $result->endPosition);
        $this->assertTrue($result->wasCreated);
    }

    public function testFromArrayMissingRequiredKeyThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required key "originalText"');

        TagParseResult::fromArray([
            'startPosition' => 0,
            'endPosition' => 5,
            'wasCreated' => false,
        ]);
    }

    public function testFromArrayMissingStartPositionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required key "startPosition"');

        TagParseResult::fromArray([
            'originalText' => '@test',
            'endPosition' => 5,
            'wasCreated' => false,
        ]);
    }

    public function testFromArrayMissingEndPositionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required key "endPosition"');

        TagParseResult::fromArray([
            'originalText' => '@test',
            'startPosition' => 0,
            'wasCreated' => false,
        ]);
    }

    public function testFromArrayMissingWasCreatedThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required key "wasCreated"');

        TagParseResult::fromArray([
            'originalText' => '@test',
            'startPosition' => 0,
            'endPosition' => 5,
        ]);
    }

    // ========================================
    // Helper Method Tests
    // ========================================

    public function testIsSuccessfulWithTag(): void
    {
        $tag = new Tag();
        $tag->setName('test');

        $result = TagParseResult::create(
            tag: $tag,
            originalText: '@test',
            startPosition: 0,
            endPosition: 5,
            wasCreated: false,
        );

        $this->assertTrue($result->isSuccessful());
    }

    public function testIsSuccessfulWithoutTag(): void
    {
        $result = TagParseResult::create(
            tag: null,
            originalText: '@test',
            startPosition: 0,
            endPosition: 5,
            wasCreated: false,
        );

        $this->assertFalse($result->isSuccessful());
    }

    public function testGetLength(): void
    {
        $result = TagParseResult::create(
            tag: null,
            originalText: '@longertag',
            startPosition: 5,
            endPosition: 15,
            wasCreated: false,
        );

        $this->assertEquals(10, $result->getLength());
    }

    public function testGetLengthAtStart(): void
    {
        $result = TagParseResult::create(
            tag: null,
            originalText: '@short',
            startPosition: 0,
            endPosition: 6,
            wasCreated: false,
        );

        $this->assertEquals(6, $result->getLength());
    }

    // ========================================
    // Immutability Tests
    // ========================================

    public function testIsReadonly(): void
    {
        $result = TagParseResult::create(
            tag: null,
            originalText: '@test',
            startPosition: 0,
            endPosition: 5,
            wasCreated: false,
        );

        $reflection = new \ReflectionClass($result);

        $this->assertTrue($reflection->isReadOnly());
    }
}
