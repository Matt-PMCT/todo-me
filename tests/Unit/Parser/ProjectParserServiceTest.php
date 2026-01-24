<?php

declare(strict_types=1);

namespace App\Tests\Unit\Parser;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Service\Parser\ProjectParserService;
use App\Tests\Unit\UnitTestCase;
use App\ValueObject\ProjectParseResult;
use PHPUnit\Framework\MockObject\MockObject;

class ProjectParserServiceTest extends UnitTestCase
{
    private ProjectRepository&MockObject $projectRepository;
    private ProjectParserService $parser;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->parser = new ProjectParserService($this->projectRepository);
        $this->user = $this->createUserWithId();
    }

    // ========================================
    // Basic Project Parsing Tests
    // ========================================

    public function testParseSimpleProjectHashtag(): void
    {
        $project = $this->createProjectWithId('project-123', $this->user, 'work');

        $this->projectRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'work')
            ->willReturn($project);

        $result = $this->parser->parse('Create task #work', $this->user);

        $this->assertInstanceOf(ProjectParseResult::class, $result);
        $this->assertTrue($result->found);
        $this->assertSame($project, $result->project);
        $this->assertEquals('work', $result->matchedName);
    }

    public function testParseProjectWithNumbers(): void
    {
        $project = $this->createProjectWithId('project-123', $this->user, 'project2024');

        $this->projectRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'project2024')
            ->willReturn($project);

        $result = $this->parser->parse('Task for #project2024', $this->user);

        $this->assertTrue($result->found);
        $this->assertEquals('project2024', $result->matchedName);
    }

    public function testParseProjectWithUnderscore(): void
    {
        $project = $this->createProjectWithId('project-123', $this->user, 'my_project');

        $this->projectRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'my_project')
            ->willReturn($project);

        $result = $this->parser->parse('#my_project task', $this->user);

        $this->assertTrue($result->found);
        $this->assertEquals('my_project', $result->matchedName);
    }

    public function testParseProjectWithHyphen(): void
    {
        $project = $this->createProjectWithId('project-123', $this->user, 'my-project');

        $this->projectRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'my-project')
            ->willReturn($project);

        $result = $this->parser->parse('#my-project is great', $this->user);

        $this->assertTrue($result->found);
        $this->assertEquals('my-project', $result->matchedName);
    }

    public function testParsePersonalProject(): void
    {
        $project = $this->createProjectWithId('project-123', $this->user, 'personal');

        $this->projectRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'personal')
            ->willReturn($project);

        $result = $this->parser->parse('Buy groceries #personal', $this->user);

        $this->assertTrue($result->found);
        $this->assertEquals('personal', $result->matchedName);
    }

    // ========================================
    // Nested Project Parsing Tests
    // ========================================

    public function testParseNestedProjectTwoLevels(): void
    {
        $project = $this->createProjectWithId('project-123', $this->user, 'meetings');

        $this->projectRepository->expects($this->once())
            ->method('findByPathInsensitive')
            ->with($this->user, 'work/meetings')
            ->willReturn($project);

        $result = $this->parser->parse('Attend standup #work/meetings', $this->user);

        $this->assertTrue($result->found);
        $this->assertSame($project, $result->project);
        $this->assertEquals('work/meetings', $result->matchedName);
    }

    public function testParseNestedProjectThreeLevels(): void
    {
        $project = $this->createProjectWithId('project-123', $this->user, 'q1');

        $this->projectRepository->expects($this->once())
            ->method('findByPathInsensitive')
            ->with($this->user, 'work/reports/q1')
            ->willReturn($project);

        $result = $this->parser->parse('Prepare #work/reports/q1 summary', $this->user);

        $this->assertTrue($result->found);
        $this->assertEquals('work/reports/q1', $result->matchedName);
    }

    public function testParseNestedProjectWithMixedCharacters(): void
    {
        $project = $this->createProjectWithId('project-123', $this->user, 'team-sync');

        $this->projectRepository->expects($this->once())
            ->method('findByPathInsensitive')
            ->with($this->user, 'company_2024/team-sync')
            ->willReturn($project);

        $result = $this->parser->parse('#company_2024/team-sync meeting', $this->user);

        $this->assertTrue($result->found);
        $this->assertEquals('company_2024/team-sync', $result->matchedName);
    }

    // ========================================
    // Case Insensitivity Tests
    // ========================================

    public function testParseCaseInsensitiveUppercase(): void
    {
        $project = $this->createProjectWithId('project-123', $this->user, 'work');

        $this->projectRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'Work')
            ->willReturn($project);

        $result = $this->parser->parse('Task #Work here', $this->user);

        $this->assertTrue($result->found);
        $this->assertEquals('Work', $result->matchedName);
    }

    public function testParseCaseInsensitiveMixedCase(): void
    {
        $project = $this->createProjectWithId('project-123', $this->user, 'personal');

        $this->projectRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'PeRsOnAl')
            ->willReturn($project);

        $result = $this->parser->parse('#PeRsOnAl stuff', $this->user);

        $this->assertTrue($result->found);
    }

    public function testParseCaseInsensitiveNestedPath(): void
    {
        $project = $this->createProjectWithId('project-123', $this->user, 'meetings');

        $this->projectRepository->expects($this->once())
            ->method('findByPathInsensitive')
            ->with($this->user, 'WORK/Meetings')
            ->willReturn($project);

        $result = $this->parser->parse('#WORK/Meetings scheduled', $this->user);

        $this->assertTrue($result->found);
        $this->assertEquals('WORK/Meetings', $result->matchedName);
    }

    // ========================================
    // Project Not Found Tests
    // ========================================

    public function testParseProjectNotFound(): void
    {
        $this->projectRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'nonexistent')
            ->willReturn(null);

        $result = $this->parser->parse('Task for #nonexistent project', $this->user);

        $this->assertInstanceOf(ProjectParseResult::class, $result);
        $this->assertFalse($result->found);
        $this->assertNull($result->project);
        $this->assertEquals('nonexistent', $result->matchedName);
    }

    public function testParseNestedProjectNotFound(): void
    {
        $this->projectRepository->expects($this->once())
            ->method('findByPathInsensitive')
            ->with($this->user, 'work/missing/deep')
            ->willReturn(null);

        $result = $this->parser->parse('#work/missing/deep task', $this->user);

        $this->assertFalse($result->found);
        $this->assertNull($result->project);
        $this->assertEquals('work/missing/deep', $result->matchedName);
    }

    // ========================================
    // Position Tracking Tests
    // ========================================

    public function testParseTrackPositionAtStart(): void
    {
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('#project at start', $this->user);

        $this->assertEquals(0, $result->startPosition);
        $this->assertEquals(8, $result->endPosition); // #project = 8 chars
    }

    public function testParseTrackPositionInMiddle(): void
    {
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('Task for #work to do', $this->user);

        $this->assertEquals(9, $result->startPosition);
        $this->assertEquals(14, $result->endPosition); // #work = 5 chars
    }

    public function testParseTrackPositionAtEnd(): void
    {
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('Complete task #personal', $this->user);

        $this->assertEquals(14, $result->startPosition);
        $this->assertEquals(23, $result->endPosition); // #personal = 9 chars
    }

    public function testParseTrackPositionNestedProject(): void
    {
        $this->projectRepository->method('findByPathInsensitive')->willReturn(null);

        $result = $this->parser->parse('Meeting #work/meetings now', $this->user);

        $this->assertEquals(8, $result->startPosition);
        $this->assertEquals(22, $result->endPosition); // #work/meetings = 14 chars
    }

    // ========================================
    // Multiple Hashtags Tests (First Only)
    // ========================================

    public function testParseReturnsFirstHashtagOnly(): void
    {
        $project = $this->createProjectWithId('project-123', $this->user, 'first');

        $this->projectRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'first')
            ->willReturn($project);

        $result = $this->parser->parse('#first and #second and #third', $this->user);

        $this->assertTrue($result->found);
        $this->assertEquals('first', $result->matchedName);
        $this->assertEquals(0, $result->startPosition);
    }

    public function testParseMultipleHashtagsReturnsFirstPosition(): void
    {
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('Do #task1 then #task2 finally #task3', $this->user);

        $this->assertEquals('task1', $result->matchedName);
        $this->assertEquals(3, $result->startPosition);
        $this->assertEquals(9, $result->endPosition);
    }

    // ========================================
    // Adjacent Text Handling Tests
    // ========================================

    public function testParseHashtagFollowedByPunctuation(): void
    {
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('Check #project, then continue', $this->user);

        $this->assertEquals('project', $result->matchedName);
    }

    public function testParseHashtagFollowedByPeriod(): void
    {
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('Work on #project.', $this->user);

        $this->assertEquals('project', $result->matchedName);
    }

    public function testParseHashtagInParentheses(): void
    {
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('Task (see #project) for details', $this->user);

        $this->assertEquals('project', $result->matchedName);
        $this->assertEquals(10, $result->startPosition);
    }

    public function testParseHashtagWithTrailingColon(): void
    {
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('#project: do this task', $this->user);

        $this->assertEquals('project', $result->matchedName);
    }

    // ========================================
    // Edge Cases Tests
    // ========================================

    public function testParseEmptyInputReturnsNull(): void
    {
        $result = $this->parser->parse('', $this->user);

        $this->assertNull($result);
    }

    public function testParseNoHashtagReturnsNull(): void
    {
        $result = $this->parser->parse('Just a regular task without hashtag', $this->user);

        $this->assertNull($result);
    }

    public function testParseLoneHashSymbolReturnsNull(): void
    {
        $result = $this->parser->parse('This has a # but no project name', $this->user);

        $this->assertNull($result);
    }

    public function testParseHashFollowedBySpaceReturnsNull(): void
    {
        $result = $this->parser->parse('Value is # 123', $this->user);

        $this->assertNull($result);
    }

    public function testParseHashFollowedByInvalidCharsReturnsNull(): void
    {
        $result = $this->parser->parse('Test #@invalid project', $this->user);

        $this->assertNull($result);
    }

    public function testParseOnlyHashtagInput(): void
    {
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('#onlyproject', $this->user);

        $this->assertNotNull($result);
        $this->assertEquals('onlyproject', $result->matchedName);
        $this->assertEquals(0, $result->startPosition);
        $this->assertEquals(12, $result->endPosition);
    }

    public function testParsePreservesOriginalText(): void
    {
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $input = 'Complete task #work by Friday';
        $result = $this->parser->parse($input, $this->user);

        $this->assertEquals($input, $result->originalText);
    }

    public function testParseHashtagWithSpecialUnicodeCharactersNotMatched(): void
    {
        // Unicode characters should not be matched
        $result = $this->parser->parse('#projet\xC3\xA9 task', $this->user);

        // The regex will match 'projet' only (stops at the special char)
        $this->assertNotNull($result);
        $this->assertEquals('projet', $result->matchedName);
    }

    // ========================================
    // Parse Result Methods Tests
    // ========================================

    public function testGetMatchLengthCalculatesCorrectly(): void
    {
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('Do #workproject task', $this->user);

        $this->assertEquals(12, $result->getMatchLength()); // #workproject = 12 chars
    }

    public function testGetMatchedTextReturnsCorrectSubstring(): void
    {
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('Check #myproject status', $this->user);

        $this->assertEquals('#myproject', $result->getMatchedText());
    }

    // ========================================
    // Value Object toArray/fromArray Tests
    // ========================================

    public function testToArrayWithProjectIncludesProjectData(): void
    {
        $parentProject = $this->createProjectWithId('parent-123', $this->user, 'work');
        $project = $this->createProjectWithId('project-123', $this->user, 'meetings');
        $project->setParent($parentProject);

        $this->projectRepository->method('findByPathInsensitive')->willReturn($project);

        $result = $this->parser->parse('#work/meetings task', $this->user);
        $array = $result->toArray();

        $this->assertEquals('project-123', $array['projectId']);
        $this->assertEquals('meetings', $array['projectName']);
        $this->assertEquals('work/meetings', $array['projectFullPath']);
        $this->assertTrue($array['found']);
    }

    public function testToArrayWithoutProjectHasNullValues(): void
    {
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('#nonexistent task', $this->user);
        $array = $result->toArray();

        $this->assertNull($array['projectId']);
        $this->assertNull($array['projectName']);
        $this->assertNull($array['projectFullPath']);
        $this->assertFalse($array['found']);
    }

    public function testToArrayIncludesPositionData(): void
    {
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('Task #project here', $this->user);
        $array = $result->toArray();

        $this->assertEquals(5, $array['startPosition']);
        $this->assertEquals(13, $array['endPosition']);
        $this->assertEquals('project', $array['matchedName']);
        $this->assertEquals('Task #project here', $array['originalText']);
    }

    public function testFromArrayCreatesResult(): void
    {
        $data = [
            'originalText' => 'Test #project input',
            'startPosition' => 5,
            'endPosition' => 13,
            'matchedName' => 'project',
            'found' => false,
        ];

        $result = ProjectParseResult::fromArray($data);

        $this->assertNull($result->project);
        $this->assertEquals('Test #project input', $result->originalText);
        $this->assertEquals(5, $result->startPosition);
        $this->assertEquals(13, $result->endPosition);
        $this->assertEquals('project', $result->matchedName);
        $this->assertFalse($result->found);
    }

    public function testFromArrayThrowsOnMissingRequiredKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required key "startPosition"');

        ProjectParseResult::fromArray([
            'originalText' => 'test',
            'endPosition' => 5,
            'matchedName' => 'test',
            'found' => false,
        ]);
    }

    // ========================================
    // Integration-style Tests
    // ========================================

    public function testParseComplexInputWithMultipleElements(): void
    {
        $project = $this->createProjectWithId('project-123', $this->user, 'client-work');

        $this->projectRepository->expects($this->once())
            ->method('findByNameInsensitive')
            ->with($this->user, 'client-work')
            ->willReturn($project);

        $input = 'Review PR for #client-work @john due:tomorrow !high';
        $result = $this->parser->parse($input, $this->user);

        $this->assertTrue($result->found);
        $this->assertEquals('client-work', $result->matchedName);
        $this->assertEquals(14, $result->startPosition);
        $this->assertEquals(26, $result->endPosition);
    }

    public function testParseDoesNotMatchHashInUrl(): void
    {
        // URLs with hash fragments should match the first alphanumeric after #
        $this->projectRepository->method('findByNameInsensitive')->willReturn(null);

        $result = $this->parser->parse('Check https://example.com/page#section for info', $this->user);

        // It will match #section as a project reference
        $this->assertNotNull($result);
        $this->assertEquals('section', $result->matchedName);
    }

    public function testParseDeepNestedProject(): void
    {
        $project = $this->createProjectWithId('project-123', $this->user, 'task1');

        $this->projectRepository->expects($this->once())
            ->method('findByPathInsensitive')
            ->with($this->user, 'level1/level2/level3/level4/task1')
            ->willReturn($project);

        $result = $this->parser->parse('#level1/level2/level3/level4/task1 is nested', $this->user);

        $this->assertTrue($result->found);
        $this->assertEquals('level1/level2/level3/level4/task1', $result->matchedName);
    }
}
