<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Service\Parser\PriorityParserService;
use App\ValueObject\PriorityParseResult;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PriorityParserServiceTest extends TestCase
{
    private PriorityParserService $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new PriorityParserService();
    }

    // ========================================
    // Valid Priority Tests
    // ========================================

    #[DataProvider('validPriorityProvider')]
    public function testParseValidPriorities(string $input, int $expectedPriority): void
    {
        $result = $this->parser->parse($input);

        $this->assertNotNull($result);
        $this->assertInstanceOf(PriorityParseResult::class, $result);
        $this->assertEquals($expectedPriority, $result->priority);
        $this->assertTrue($result->valid);
    }

    public static function validPriorityProvider(): array
    {
        return [
            'p0 highest priority' => ['p0', 0],
            'p1 priority' => ['p1', 1],
            'p2 priority' => ['p2', 2],
            'p3 default priority' => ['p3', 3],
            'p4 lowest priority' => ['p4', 4],
        ];
    }

    // ========================================
    // Case Insensitivity Tests
    // ========================================

    #[DataProvider('caseInsensitivityProvider')]
    public function testParseCaseInsensitivity(string $input, int $expectedPriority): void
    {
        $result = $this->parser->parse($input);

        $this->assertNotNull($result);
        $this->assertEquals($expectedPriority, $result->priority);
        $this->assertTrue($result->valid);
    }

    public static function caseInsensitivityProvider(): array
    {
        return [
            'uppercase P1' => ['P1', 1],
            'uppercase P2' => ['P2', 2],
            'uppercase P0' => ['P0', 0],
            'uppercase P3' => ['P3', 3],
            'uppercase P4' => ['P4', 4],
        ];
    }

    // ========================================
    // Invalid Priority Tests
    // ========================================

    #[DataProvider('invalidPriorityProvider')]
    public function testParseInvalidPrioritiesReturnInvalidResult(string $input, int $expectedPriority): void
    {
        $result = $this->parser->parse($input);

        $this->assertNotNull($result);
        $this->assertEquals($expectedPriority, $result->priority);
        $this->assertFalse($result->valid);
    }

    public static function invalidPriorityProvider(): array
    {
        return [
            'p5 too high' => ['p5', 5],
            'p10 way too high' => ['p10', 10],
            'p99 very high' => ['p99', 99],
            'P6 uppercase too high' => ['P6', 6],
        ];
    }

    // ========================================
    // Position Tracking Tests
    // ========================================

    #[DataProvider('positionTrackingProvider')]
    public function testParsePositionTracking(string $input, int $expectedStart, int $expectedEnd, string $expectedText): void
    {
        $result = $this->parser->parse($input);

        $this->assertNotNull($result);
        $this->assertEquals($expectedStart, $result->startPosition);
        $this->assertEquals($expectedEnd, $result->endPosition);
        $this->assertEquals($expectedText, $result->originalText);
    }

    public static function positionTrackingProvider(): array
    {
        return [
            'priority at start' => ['p1 task description', 0, 2, 'p1'],
            'priority in middle' => ['task p2 description', 5, 7, 'p2'],
            'priority at end' => ['task description p3', 17, 19, 'p3'],
            'two digit priority' => ['task p10 note', 5, 8, 'p10'],
        ];
    }

    // ========================================
    // Priority in Context Tests
    // ========================================

    public function testParsePriorityInTaskContext(): void
    {
        $result = $this->parser->parse('Buy groceries p1 tomorrow');

        $this->assertNotNull($result);
        $this->assertEquals(1, $result->priority);
        $this->assertTrue($result->valid);
        $this->assertEquals(14, $result->startPosition);
        $this->assertEquals(16, $result->endPosition);
        $this->assertEquals('p1', $result->originalText);
    }

    public function testParsePriorityWithSurroundingSpaces(): void
    {
        $result = $this->parser->parse('Task with p2 priority');

        $this->assertNotNull($result);
        $this->assertEquals(2, $result->priority);
        $this->assertTrue($result->valid);
    }

    // ========================================
    // Word Boundary Tests
    // ========================================

    #[DataProvider('wordBoundaryProvider')]
    public function testParseRespectsWordBoundaries(string $input): void
    {
        $result = $this->parser->parse($input);

        $this->assertNull($result);
    }

    public static function wordBoundaryProvider(): array
    {
        return [
            'help1 should not match' => ['need help1 here'],
            'p1a should not match' => ['use p1a format'],
            'ap1 should not match' => ['using ap1 library'],
            'app1 should not match' => ['run app1'],
            'ip1 should not match' => ['using ip1 address'],
        ];
    }

    // ========================================
    // Multiple Priority Tests
    // ========================================

    public function testParseReturnsFirstPriorityOnly(): void
    {
        $result = $this->parser->parse('task p1 then p2 and p3');

        $this->assertNotNull($result);
        $this->assertEquals(1, $result->priority);
        $this->assertEquals(5, $result->startPosition);
        $this->assertEquals(7, $result->endPosition);
    }

    public function testParseReturnsFirstEvenIfInvalid(): void
    {
        $result = $this->parser->parse('task p10 then p1');

        $this->assertNotNull($result);
        $this->assertEquals(10, $result->priority);
        $this->assertFalse($result->valid);
    }

    // ========================================
    // No Priority Found Tests
    // ========================================

    #[DataProvider('noPriorityProvider')]
    public function testParseReturnsNullWhenNoPriorityFound(string $input): void
    {
        $result = $this->parser->parse($input);

        $this->assertNull($result);
    }

    public static function noPriorityProvider(): array
    {
        return [
            'empty string' => [''],
            'no priority marker' => ['buy groceries tomorrow'],
            'just p letter' => ['p is a letter'],
            'p followed by non-digit' => ['pa pb pc'],
            'priority-like text' => ['priority 1 task'],
        ];
    }

    // ========================================
    // Edge Cases Tests
    // ========================================

    public function testParseWithWhitespaceOnly(): void
    {
        $result = $this->parser->parse('   ');

        $this->assertNull($result);
    }

    public function testParseWithNewlines(): void
    {
        $result = $this->parser->parse("task\np1\ndescription");

        $this->assertNotNull($result);
        $this->assertEquals(1, $result->priority);
        $this->assertTrue($result->valid);
    }

    public function testParseWithTabs(): void
    {
        $result = $this->parser->parse("task\tp2\tdescription");

        $this->assertNotNull($result);
        $this->assertEquals(2, $result->priority);
        $this->assertTrue($result->valid);
    }

    public function testParsePriorityAtStartOfString(): void
    {
        $result = $this->parser->parse('p0 urgent task');

        $this->assertNotNull($result);
        $this->assertEquals(0, $result->priority);
        $this->assertEquals(0, $result->startPosition);
        $this->assertEquals(2, $result->endPosition);
    }

    public function testParsePriorityAtEndOfString(): void
    {
        $result = $this->parser->parse('urgent task p0');

        $this->assertNotNull($result);
        $this->assertEquals(0, $result->priority);
        $this->assertEquals(12, $result->startPosition);
        $this->assertEquals(14, $result->endPosition);
    }

    public function testParseStandalonePriority(): void
    {
        $result = $this->parser->parse('p3');

        $this->assertNotNull($result);
        $this->assertEquals(3, $result->priority);
        $this->assertEquals(0, $result->startPosition);
        $this->assertEquals(2, $result->endPosition);
        $this->assertTrue($result->valid);
    }

    // ========================================
    // Result Array Serialization Tests
    // ========================================

    public function testParseResultToArray(): void
    {
        $result = $this->parser->parse('task p2 description');

        $this->assertNotNull($result);

        $array = $result->toArray();

        $this->assertArrayHasKey('priority', $array);
        $this->assertArrayHasKey('originalText', $array);
        $this->assertArrayHasKey('startPosition', $array);
        $this->assertArrayHasKey('endPosition', $array);
        $this->assertArrayHasKey('valid', $array);

        $this->assertEquals(2, $array['priority']);
        $this->assertEquals('p2', $array['originalText']);
        $this->assertEquals(5, $array['startPosition']);
        $this->assertEquals(7, $array['endPosition']);
        $this->assertTrue($array['valid']);
    }

    public function testParseResultFromArray(): void
    {
        $data = [
            'priority' => 1,
            'originalText' => 'p1',
            'startPosition' => 5,
            'endPosition' => 7,
            'valid' => true,
        ];

        $result = PriorityParseResult::fromArray($data);

        $this->assertEquals(1, $result->priority);
        $this->assertEquals('p1', $result->originalText);
        $this->assertEquals(5, $result->startPosition);
        $this->assertEquals(7, $result->endPosition);
        $this->assertTrue($result->valid);
    }

    public function testParseResultRoundTrip(): void
    {
        $original = $this->parser->parse('task p3 note');

        $this->assertNotNull($original);

        $array = $original->toArray();
        $restored = PriorityParseResult::fromArray($array);

        $this->assertEquals($original->priority, $restored->priority);
        $this->assertEquals($original->originalText, $restored->originalText);
        $this->assertEquals($original->startPosition, $restored->startPosition);
        $this->assertEquals($original->endPosition, $restored->endPosition);
        $this->assertEquals($original->valid, $restored->valid);
    }
}
