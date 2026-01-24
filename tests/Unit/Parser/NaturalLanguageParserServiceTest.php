<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Entity\Tag;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Service\Parser\DateParserService;
use App\Service\Parser\NaturalLanguageParserService;
use App\Service\Parser\PriorityParserService;
use App\Service\Parser\ProjectParserService;
use App\Service\Parser\TagParserService;
use App\Service\Parser\TimezoneHelper;
use App\Service\TagService;
use App\Tests\Unit\UnitTestCase;
use App\ValueObject\ParseHighlight;
use App\ValueObject\TaskParseResult;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\MockObject\MockObject;

class NaturalLanguageParserServiceTest extends UnitTestCase
{
    private ProjectRepository&MockObject $projectRepository;
    private TagRepository&MockObject $tagRepository;
    private TagService&MockObject $tagService;
    private TimezoneHelper&MockObject $timezoneHelper;
    private NaturalLanguageParserService $parser;
    private DateTimeImmutable $fixedNow;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixed "now" for consistent testing: 2026-01-23 (Friday) at midnight UTC
        $this->fixedNow = new DateTimeImmutable('2026-01-23 00:00:00', new DateTimeZone('UTC'));

        // Set up mocks
        $this->timezoneHelper = $this->createMock(TimezoneHelper::class);
        $this->timezoneHelper->method('getStartOfDay')->willReturn($this->fixedNow);
        $this->timezoneHelper->method('getNow')->willReturn($this->fixedNow);

        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->tagService = $this->createMock(TagService::class);

        // Create real parser services with mocked dependencies
        $dateParser = new DateParserService($this->timezoneHelper);
        $projectParser = new ProjectParserService($this->projectRepository);
        $tagParser = new TagParserService($this->tagService);
        $priorityParser = new PriorityParserService();

        $this->parser = new NaturalLanguageParserService(
            $dateParser,
            $projectParser,
            $tagParser,
            $priorityParser,
        );
    }

    // ========================================
    // Basic Parsing Tests
    // ========================================

    public function testParseEmptyInput(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('', $user);

        $this->assertInstanceOf(TaskParseResult::class, $result);
        $this->assertEquals('', $result->title);
        $this->assertNull($result->dueDate);
        $this->assertNull($result->project);
        $this->assertEmpty($result->tags);
        $this->assertNull($result->priority);
        $this->assertEmpty($result->highlights);
        $this->assertEmpty($result->warnings);
    }

    public function testParseOnlyTitle(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('Buy groceries', $user);

        $this->assertEquals('Buy groceries', $result->title);
        $this->assertFalse($result->hasMetadata());
    }

    public function testParseTitleWithWhitespace(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('  Buy groceries  ', $user);

        $this->assertEquals('Buy groceries', $result->title);
    }

    // ========================================
    // Date Parsing Tests
    // ========================================

    public function testParseWithDate(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('Buy groceries tomorrow', $user);

        $this->assertEquals('Buy groceries', $result->title);
        $this->assertEquals('2026-01-24', $result->dueDate->format('Y-m-d'));
        $this->assertNull($result->dueTime);
        $this->assertCount(1, $result->highlights);
        $this->assertEquals('date', $result->highlights[0]->type);
    }

    public function testParseWithDateTime(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('Meeting tomorrow at 2pm', $user);

        $this->assertEquals('Meeting', $result->title);
        $this->assertEquals('2026-01-24', $result->dueDate->format('Y-m-d'));
        $this->assertEquals('14:00', $result->dueTime);
    }

    public function testParseMultipleDatesUsesFirst(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('tomorrow or next week', $user);

        // First date is "tomorrow"
        $this->assertEquals('2026-01-24', $result->dueDate->format('Y-m-d'));
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('Multiple dates found', $result->warnings[0]);
    }

    public function testParseDateAtStartOfInput(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('today Buy groceries', $user);

        $this->assertEquals('Buy groceries', $result->title);
        $this->assertEquals('2026-01-23', $result->dueDate->format('Y-m-d'));
    }

    public function testParseDateInMiddle(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('Buy groceries today please', $user);

        $this->assertEquals('Buy groceries please', $result->title);
        $this->assertEquals('2026-01-23', $result->dueDate->format('Y-m-d'));
    }

    // ========================================
    // Project Parsing Tests
    // ========================================

    public function testParseWithProject(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('proj-1', $user, 'shopping');

        $this->projectRepository->method('findByNameInsensitive')
            ->with($user, 'shopping')
            ->willReturn($project);

        $result = $this->parser->parse('Buy groceries #shopping', $user);

        $this->assertEquals('Buy groceries', $result->title);
        $this->assertSame($project, $result->project);
        $this->assertCount(1, $result->highlights);
        $this->assertEquals('project', $result->highlights[0]->type);
        $this->assertTrue($result->highlights[0]->valid);
    }

    public function testParseWithProjectNotFound(): void
    {
        $user = $this->createUserWithId();

        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('Buy groceries #nonexistent', $user);

        $this->assertEquals('Buy groceries', $result->title);
        $this->assertNull($result->project);
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('Project not found', $result->warnings[0]);
        $this->assertFalse($result->highlights[0]->valid);
    }

    public function testParseMultipleProjectsUsesFirst(): void
    {
        $user = $this->createUserWithId();
        $project1 = $this->createProjectWithId('proj-1', $user, 'work');
        $project2 = $this->createProjectWithId('proj-2', $user, 'personal');

        $callCount = 0;
        $this->projectRepository->method('findByNameInsensitive')
            ->willReturnCallback(function ($u, $name) use ($project1, $project2, &$callCount) {
                $callCount++;
                if ($name === 'work') {
                    return $project1;
                }
                if ($name === 'personal') {
                    return $project2;
                }
                return null;
            });

        $result = $this->parser->parse('#work task #personal', $user);

        $this->assertEquals('work', $result->project->getName());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('Multiple projects found', $result->warnings[0]);
    }

    public function testParseWithNestedProject(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('proj-1', $user, 'meetings');

        $this->projectRepository->method('findByPathInsensitive')
            ->with($user, 'work/meetings')
            ->willReturn($project);

        $result = $this->parser->parse('Standup call #work/meetings', $user);

        $this->assertEquals('Standup call', $result->title);
        $this->assertSame($project, $result->project);
    }

    // ========================================
    // Tag Parsing Tests
    // ========================================

    public function testParseWithTags(): void
    {
        $user = $this->createUserWithId();
        $tag1 = $this->createTagWithId('tag-1', $user, 'urgent');
        $tag2 = $this->createTagWithId('tag-2', $user, 'shopping');

        $this->tagService->method('findOrCreate')
            ->willReturnCallback(function ($u, $name) use ($tag1, $tag2) {
                if (strtolower($name) === 'urgent') {
                    return ['tag' => $tag1, 'created' => false];
                }
                if (strtolower($name) === 'shopping') {
                    return ['tag' => $tag2, 'created' => true];
                }
                return ['tag' => null, 'created' => false];
            });

        $result = $this->parser->parse('Buy groceries @urgent @shopping', $user);

        $this->assertEquals('Buy groceries', $result->title);
        $this->assertCount(2, $result->tags);
        $this->assertEquals('urgent', $result->tags[0]->getName());
        $this->assertEquals('shopping', $result->tags[1]->getName());
    }

    public function testParseCollectsAllTags(): void
    {
        $user = $this->createUserWithId();
        $tags = [];
        for ($i = 1; $i <= 3; $i++) {
            $tags[$i] = $this->createTagWithId("tag-$i", $user, "tag$i");
        }

        $this->tagService->method('findOrCreate')
            ->willReturnCallback(function ($u, $name) use ($tags) {
                $num = (int) substr($name, 3);
                return ['tag' => $tags[$num] ?? null, 'created' => false];
            });

        $result = $this->parser->parse('Task @tag1 @tag2 @tag3', $user);

        $this->assertCount(3, $result->tags);
        // All three should have highlights
        $tagHighlights = array_filter($result->highlights, fn($h) => $h->type === 'tag');
        $this->assertCount(3, $tagHighlights);
    }

    // ========================================
    // Priority Parsing Tests
    // ========================================

    public function testParseWithPriority(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('Important task p1', $user);

        $this->assertEquals('Important task', $result->title);
        $this->assertEquals(1, $result->priority);
        $this->assertCount(1, $result->highlights);
        $this->assertEquals('priority', $result->highlights[0]->type);
        $this->assertTrue($result->highlights[0]->valid);
    }

    public function testParseWithInvalidPriority(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('Task p10', $user);

        $this->assertEquals('Task', $result->title);
        $this->assertNull($result->priority);
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('Invalid priority', $result->warnings[0]);
        $this->assertFalse($result->highlights[0]->valid);
    }

    public function testParseMultiplePrioritiesUsesFirst(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('Task p1 p2', $user);

        $this->assertEquals(1, $result->priority);
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('Multiple priorities found', $result->warnings[0]);
    }

    public function testParsePriorityP0(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('Critical bug p0', $user);

        $this->assertEquals(0, $result->priority);
    }

    public function testParsePriorityP4(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('Low priority task p4', $user);

        $this->assertEquals(4, $result->priority);
    }

    // ========================================
    // Combined Parsing Tests
    // ========================================

    public function testParseWithAllComponents(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('proj-1', $user, 'shopping');
        $tag = $this->createTagWithId('tag-1', $user, 'urgent');

        $this->projectRepository->method('findByNameInsensitive')
            ->willReturn($project);
        $this->tagService->method('findOrCreate')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $result = $this->parser->parse('Buy groceries tomorrow #shopping @urgent p1', $user);

        $this->assertEquals('Buy groceries', $result->title);
        $this->assertEquals('2026-01-24', $result->dueDate->format('Y-m-d'));
        $this->assertSame($project, $result->project);
        $this->assertCount(1, $result->tags);
        $this->assertEquals(1, $result->priority);
        $this->assertTrue($result->hasMetadata());
        $this->assertCount(4, $result->highlights);
    }

    public function testParseWithSomeComponents(): void
    {
        $user = $this->createUserWithId();
        $tag = $this->createTagWithId('tag-1', $user, 'urgent');

        $this->tagService->method('findOrCreate')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $result = $this->parser->parse('Task @urgent tomorrow', $user);

        $this->assertEquals('Task', $result->title);
        $this->assertEquals('2026-01-24', $result->dueDate->format('Y-m-d'));
        $this->assertNull($result->project);
        $this->assertCount(1, $result->tags);
        $this->assertNull($result->priority);
    }

    public function testParseIndependentParsing(): void
    {
        // Project not found but date and priority should still work
        $user = $this->createUserWithId();

        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('Task #nonexistent tomorrow p1', $user);

        // Date and priority should be parsed despite project not found
        $this->assertEquals('2026-01-24', $result->dueDate->format('Y-m-d'));
        $this->assertEquals(1, $result->priority);
        $this->assertNull($result->project);
        $this->assertTrue($result->hasWarnings());
    }

    // ========================================
    // Title Extraction Tests
    // ========================================

    public function testTitleExtractionRemovesMetadata(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('proj-1', $user, 'work');
        $tag = $this->createTagWithId('tag-1', $user, 'urgent');

        $this->projectRepository->method('findByNameInsensitive')
            ->willReturn($project);
        $this->tagService->method('findOrCreate')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $result = $this->parser->parse('#work Buy groceries @urgent', $user);

        $this->assertEquals('Buy groceries', $result->title);
    }

    public function testTitleExtractionCollapsesWhitespace(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('Buy  groceries   tomorrow', $user);

        $this->assertEquals('Buy groceries', $result->title);
    }

    public function testTitleExtractionWithOnlyMetadata(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('tomorrow p1', $user);

        $this->assertEquals('', $result->title);
    }

    public function testTitleExtractionPreservesMiddleText(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('p1 Buy groceries tomorrow', $user);

        $this->assertEquals('Buy groceries', $result->title);
    }

    // ========================================
    // Highlights Ordering Tests
    // ========================================

    public function testHighlightsAreSortedByPosition(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('proj-1', $user, 'project');
        $tag = $this->createTagWithId('tag-1', $user, 'tag');

        $this->projectRepository->method('findByNameInsensitive')
            ->willReturn($project);
        $this->tagService->method('findOrCreate')
            ->willReturn(['tag' => $tag, 'created' => false]);

        $result = $this->parser->parse('p1 Task @tag #project tomorrow', $user);

        $this->assertCount(4, $result->highlights);
        // Should be sorted by position
        $positions = array_map(fn($h) => $h->startPosition, $result->highlights);
        $sortedPositions = $positions;
        sort($sortedPositions);
        $this->assertEquals($sortedPositions, $positions);
    }

    // ========================================
    // Edge Cases
    // ========================================

    public function testParseWithSpecialCharactersInTitle(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('Buy milk & eggs: $5 tomorrow', $user);

        $this->assertEquals('Buy milk & eggs: $5', $result->title);
    }

    public function testParsePreservesQuotedText(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->parse('Read "The Great Gatsby" tomorrow', $user);

        $this->assertEquals('Read "The Great Gatsby"', $result->title);
    }

    // ========================================
    // Configure Tests
    // ========================================

    public function testConfigureReturnsSelf(): void
    {
        $user = $this->createUserWithId();

        $result = $this->parser->configure($user);

        $this->assertSame($this->parser, $result);
    }

    // ========================================
    // TaskParseResult Value Object Tests
    // ========================================

    public function testTaskParseResultHasMetadata(): void
    {
        $user = $this->createUserWithId();
        $project = $this->createProjectWithId('proj-1', $user, 'test');

        $resultWithDate = TaskParseResult::create(
            title: 'Test',
            dueDate: new DateTimeImmutable(),
        );
        $this->assertTrue($resultWithDate->hasMetadata());

        $resultWithProject = TaskParseResult::create(
            title: 'Test',
            project: $project,
        );
        $this->assertTrue($resultWithProject->hasMetadata());

        $resultWithTags = TaskParseResult::create(
            title: 'Test',
            tags: [$this->createTagWithId('tag-1', $user, 'test')],
        );
        $this->assertTrue($resultWithTags->hasMetadata());

        $resultWithPriority = TaskParseResult::create(
            title: 'Test',
            priority: 1,
        );
        $this->assertTrue($resultWithPriority->hasMetadata());

        $resultWithoutMetadata = TaskParseResult::create(title: 'Test');
        $this->assertFalse($resultWithoutMetadata->hasMetadata());
    }

    public function testTaskParseResultHasWarnings(): void
    {
        $resultWithWarnings = TaskParseResult::create(
            title: 'Test',
            warnings: ['Warning 1'],
        );
        $this->assertTrue($resultWithWarnings->hasWarnings());

        $resultWithoutWarnings = TaskParseResult::create(title: 'Test');
        $this->assertFalse($resultWithoutWarnings->hasWarnings());
    }

    public function testTaskParseResultToArray(): void
    {
        $user = $this->createUserWithId();
        $date = new DateTimeImmutable('2026-01-24');
        $project = $this->createProjectWithId('proj-1', $user, 'work');
        $tag = $this->createTagWithId('tag-1', $user, 'urgent');

        $result = TaskParseResult::create(
            title: 'Test Task',
            dueDate: $date,
            dueTime: '14:00',
            project: $project,
            tags: [$tag],
            priority: 1,
            warnings: ['Warning'],
        );

        $array = $result->toArray();

        $this->assertEquals('Test Task', $array['title']);
        $this->assertEquals('2026-01-24', $array['dueDate']);
        $this->assertEquals('14:00', $array['dueTime']);
        $this->assertEquals('proj-1', $array['project']['id']);
        $this->assertEquals('work', $array['project']['name']);
        $this->assertCount(1, $array['tags']);
        $this->assertEquals('tag-1', $array['tags'][0]['id']);
        $this->assertEquals('urgent', $array['tags'][0]['name']);
        $this->assertEquals(1, $array['priority']);
        $this->assertCount(1, $array['warnings']);
    }

    // ========================================
    // ParseHighlight Value Object Tests
    // ========================================

    public function testParseHighlightCreate(): void
    {
        $highlight = ParseHighlight::create(
            type: 'date',
            text: 'tomorrow',
            startPosition: 10,
            endPosition: 18,
            value: '2026-01-24',
            valid: true,
        );

        $this->assertEquals('date', $highlight->type);
        $this->assertEquals('tomorrow', $highlight->text);
        $this->assertEquals(10, $highlight->startPosition);
        $this->assertEquals(18, $highlight->endPosition);
        $this->assertEquals('2026-01-24', $highlight->value);
        $this->assertTrue($highlight->valid);
    }

    public function testParseHighlightGetLength(): void
    {
        $highlight = ParseHighlight::create(
            type: 'tag',
            text: '@urgent',
            startPosition: 5,
            endPosition: 12,
            value: 'urgent',
            valid: true,
        );

        $this->assertEquals(7, $highlight->getLength());
    }

    public function testParseHighlightToArray(): void
    {
        $highlight = ParseHighlight::create(
            type: 'priority',
            text: 'p1',
            startPosition: 0,
            endPosition: 2,
            value: 1,
            valid: true,
        );

        $array = $highlight->toArray();

        $this->assertEquals('priority', $array['type']);
        $this->assertEquals('p1', $array['text']);
        $this->assertEquals(0, $array['startPosition']);
        $this->assertEquals(2, $array['endPosition']);
        $this->assertEquals(1, $array['value']);
        $this->assertTrue($array['valid']);
    }

    public function testParseHighlightFromArray(): void
    {
        $data = [
            'type' => 'project',
            'text' => '#work',
            'startPosition' => 15,
            'endPosition' => 20,
            'value' => 'work',
            'valid' => false,
        ];

        $highlight = ParseHighlight::fromArray($data);

        $this->assertEquals('project', $highlight->type);
        $this->assertEquals('#work', $highlight->text);
        $this->assertEquals(15, $highlight->startPosition);
        $this->assertEquals(20, $highlight->endPosition);
        $this->assertEquals('work', $highlight->value);
        $this->assertFalse($highlight->valid);
    }

    public function testParseHighlightFromArrayThrowsOnMissingKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required key "type"');

        ParseHighlight::fromArray([
            'text' => 'test',
            'startPosition' => 0,
            'endPosition' => 4,
            'value' => 'test',
            'valid' => true,
        ]);
    }

    // ========================================
    // Helper Methods
    // ========================================

    private function createTagWithId(string $id, $user, string $name, string $color = '#000000'): Tag
    {
        $tag = new Tag();
        $tag->setOwner($user);
        $tag->setName($name);
        $tag->setColor($color);
        $this->setEntityId($tag, $id);

        return $tag;
    }
}
