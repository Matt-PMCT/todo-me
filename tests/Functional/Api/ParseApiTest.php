<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the Parse API endpoint.
 *
 * Tests:
 * - Parse with all components (date, project, tags, priority)
 * - Parse with only date
 * - Parse with only project
 * - Parse with only tags
 * - Parse with only priority
 * - Parse with no metadata (just title)
 * - Parse with empty input
 * - Parse with invalid project (project not found warning)
 * - Parse with invalid priority (valid: false in highlight)
 * - Response structure validation
 * - Unauthenticated request (401)
 * - Multi-tenant isolation (can't see other user's projects)
 */
class ParseApiTest extends ApiTestCase
{
    // ========================================
    // Successful Parsing Tests
    // ========================================

    public function testParseWithAllComponents(): void
    {
        $user = $this->createUser('parse-all@example.com', 'Password123');
        $project = $this->createProject($user, 'work');
        $tag = $this->createTag($user, 'urgent', '#FF0000');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 'Review proposal #work @urgent tomorrow p1']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Check title (with metadata removed)
        $this->assertEquals('Review proposal', $data['title']);

        // Check due date
        $this->assertNotNull($data['due_date']);
        $this->assertFalse($data['has_time']);

        // Check project
        $this->assertNotNull($data['project']);
        $this->assertEquals($project->getId(), $data['project']['id']);
        $this->assertEquals('work', $data['project']['name']);
        $this->assertEquals('work', $data['project']['fullPath']);
        $this->assertArrayHasKey('color', $data['project']);

        // Check tags
        $this->assertCount(1, $data['tags']);
        $this->assertEquals($tag->getId(), $data['tags'][0]['id']);
        $this->assertEquals('urgent', $data['tags'][0]['name']);
        $this->assertEquals('#FF0000', $data['tags'][0]['color']);

        // Check priority
        $this->assertEquals(1, $data['priority']);

        // Check highlights
        $this->assertCount(4, $data['highlights']);

        // Check no warnings
        $this->assertEmpty($data['warnings']);
    }

    public function testParseWithOnlyDate(): void
    {
        $user = $this->createUser('parse-date@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 'Buy groceries tomorrow']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Buy groceries', $data['title']);
        $this->assertNotNull($data['due_date']);
        $this->assertNull($data['due_time']);
        $this->assertFalse($data['has_time']);
        $this->assertNull($data['project']);
        $this->assertEmpty($data['tags']);
        $this->assertNull($data['priority']);
        $this->assertCount(1, $data['highlights']);
        $this->assertEquals('date', $data['highlights'][0]['type']);
    }

    public function testParseWithDateTime(): void
    {
        $user = $this->createUser('parse-datetime@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 'Meeting tomorrow at 2pm']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Meeting', $data['title']);
        $this->assertNotNull($data['due_date']);
        $this->assertEquals('14:00', $data['due_time']);
        $this->assertTrue($data['has_time']);
    }

    public function testParseWithOnlyProject(): void
    {
        $user = $this->createUser('parse-project@example.com', 'Password123');
        $project = $this->createProject($user, 'personal');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 'Clean room #personal']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Clean room', $data['title']);
        $this->assertNull($data['due_date']);
        $this->assertNotNull($data['project']);
        $this->assertEquals($project->getId(), $data['project']['id']);
        $this->assertEquals('personal', $data['project']['name']);
        $this->assertEmpty($data['tags']);
        $this->assertNull($data['priority']);
        $this->assertCount(1, $data['highlights']);
        $this->assertEquals('project', $data['highlights'][0]['type']);
    }

    public function testParseWithOnlyTags(): void
    {
        $user = $this->createUser('parse-tags@example.com', 'Password123');
        $tag1 = $this->createTag($user, 'important', '#FF0000');
        $tag2 = $this->createTag($user, 'quick', '#00FF00');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 'Call mom @important @quick']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Call mom', $data['title']);
        $this->assertNull($data['due_date']);
        $this->assertNull($data['project']);
        $this->assertCount(2, $data['tags']);
        $this->assertNull($data['priority']);
        $this->assertCount(2, $data['highlights']);
    }

    public function testParseWithOnlyPriority(): void
    {
        $user = $this->createUser('parse-priority@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 'Urgent task p0']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Urgent task', $data['title']);
        $this->assertNull($data['due_date']);
        $this->assertNull($data['project']);
        $this->assertEmpty($data['tags']);
        $this->assertEquals(0, $data['priority']);
        $this->assertCount(1, $data['highlights']);
        $this->assertEquals('priority', $data['highlights'][0]['type']);
        $this->assertTrue($data['highlights'][0]['valid']);
    }

    public function testParseWithNoMetadata(): void
    {
        $user = $this->createUser('parse-nometa@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 'Just a simple task']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Just a simple task', $data['title']);
        $this->assertNull($data['due_date']);
        $this->assertNull($data['due_time']);
        $this->assertFalse($data['has_time']);
        $this->assertNull($data['project']);
        $this->assertEmpty($data['tags']);
        $this->assertNull($data['priority']);
        $this->assertEmpty($data['highlights']);
        $this->assertEmpty($data['warnings']);
    }

    public function testParseWithEmptyInput(): void
    {
        $user = $this->createUser('parse-empty@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => '']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('', $data['title']);
        $this->assertNull($data['due_date']);
        $this->assertNull($data['project']);
        $this->assertEmpty($data['tags']);
        $this->assertNull($data['priority']);
        $this->assertEmpty($data['highlights']);
    }

    // ========================================
    // Warning and Validation Tests
    // ========================================

    public function testParseWithInvalidProject(): void
    {
        $user = $this->createUser('parse-badproject@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 'Task #nonexistent']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Task', $data['title']);
        $this->assertNull($data['project']);

        // Should have warning about project not found
        $this->assertNotEmpty($data['warnings']);
        $this->assertStringContainsString('Project not found', $data['warnings'][0]);

        // Should have highlight with valid: false
        $projectHighlight = array_filter($data['highlights'], fn($h) => $h['type'] === 'project');
        $this->assertCount(1, $projectHighlight);
        $this->assertFalse(array_values($projectHighlight)[0]['valid']);
    }

    public function testParseWithInvalidPriority(): void
    {
        $user = $this->createUser('parse-badpriority@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 'Task p10']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Task', $data['title']);
        $this->assertNull($data['priority']);

        // Should have warning about invalid priority
        $this->assertNotEmpty($data['warnings']);
        $this->assertStringContainsString('Invalid priority', $data['warnings'][0]);

        // Should have highlight with valid: false
        $priorityHighlight = array_filter($data['highlights'], fn($h) => $h['type'] === 'priority');
        $this->assertCount(1, $priorityHighlight);
        $this->assertFalse(array_values($priorityHighlight)[0]['valid']);
    }

    public function testParseWithMultipleDatesWarning(): void
    {
        $user = $this->createUser('parse-multidates@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 'Meet tomorrow or next week']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should use first date
        $this->assertNotNull($data['due_date']);

        // Should have warning about multiple dates
        $this->assertNotEmpty($data['warnings']);
        $dateWarnings = array_filter($data['warnings'], fn($w) => str_contains($w, 'Multiple dates'));
        $this->assertNotEmpty($dateWarnings);
    }

    // ========================================
    // Response Structure Tests
    // ========================================

    public function testResponseStructure(): void
    {
        $user = $this->createUser('parse-structure@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 'Test task']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $this->assertSuccessResponse($response);

        $data = $this->getResponseData($response);

        // Verify all expected fields are present
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('due_date', $data);
        $this->assertArrayHasKey('due_time', $data);
        $this->assertArrayHasKey('has_time', $data);
        $this->assertArrayHasKey('project', $data);
        $this->assertArrayHasKey('tags', $data);
        $this->assertArrayHasKey('priority', $data);
        $this->assertArrayHasKey('highlights', $data);
        $this->assertArrayHasKey('warnings', $data);
    }

    public function testHighlightStructure(): void
    {
        $user = $this->createUser('parse-highlight@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 'Task p1']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['highlights']);

        $highlight = $data['highlights'][0];
        $this->assertArrayHasKey('type', $highlight);
        $this->assertArrayHasKey('text', $highlight);
        $this->assertArrayHasKey('start', $highlight);
        $this->assertArrayHasKey('end', $highlight);
        $this->assertArrayHasKey('value', $highlight);
        $this->assertArrayHasKey('valid', $highlight);

        $this->assertEquals('priority', $highlight['type']);
        $this->assertEquals('p1', $highlight['text']);
        $this->assertTrue($highlight['valid']);
    }

    // ========================================
    // Authentication Tests
    // ========================================

    public function testUnauthenticatedRequest(): void
    {
        $response = $this->apiRequest(
            'POST',
            '/api/v1/parse',
            ['input' => 'Test task']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Multi-tenant Isolation Tests
    // ========================================

    public function testCannotSeeOtherUsersProjects(): void
    {
        $user1 = $this->createUser('user1-parse@example.com', 'Password123');
        $user2 = $this->createUser('user2-parse@example.com', 'Password123');

        // Create project for user2
        $this->createProject($user2, 'secret');

        // User1 tries to reference user2's project
        $response = $this->authenticatedApiRequest(
            $user1,
            'POST',
            '/api/v1/parse',
            ['input' => 'Task #secret']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Project should not be found
        $this->assertNull($data['project']);

        // Should have warning about project not found
        $this->assertNotEmpty($data['warnings']);
        $this->assertStringContainsString('Project not found', $data['warnings'][0]);
    }

    public function testCanSeeOwnProjects(): void
    {
        $user = $this->createUser('user-own-proj@example.com', 'Password123');
        $project = $this->createProject($user, 'myproject');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 'Task #myproject']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Project should be found
        $this->assertNotNull($data['project']);
        $this->assertEquals($project->getId(), $data['project']['id']);
        $this->assertEquals('myproject', $data['project']['name']);
    }

    // ========================================
    // Input Validation Tests
    // ========================================

    public function testMissingInputField(): void
    {
        $user = $this->createUser('parse-noinput@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            []
        );

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
    }

    public function testInvalidInputType(): void
    {
        $user = $this->createUser('parse-badinput@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 123]
        );

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
    }

    // ========================================
    // Edge Cases
    // ========================================

    public function testParseWithWhitespaceOnlyInput(): void
    {
        $user = $this->createUser('parse-whitespace@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => '   ']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('', $data['title']);
    }

    public function testParseWithNestedProject(): void
    {
        $user = $this->createUser('parse-nested@example.com', 'Password123');
        $parent = $this->createProject($user, 'work');
        $child = $this->createProject($user, 'meetings');
        $child->setParent($parent);
        $this->entityManager->flush();

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/parse',
            ['input' => 'Standup #work/meetings']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertNotNull($data['project']);
        $this->assertEquals($child->getId(), $data['project']['id']);
        $this->assertEquals('meetings', $data['project']['name']);
        $this->assertEquals('work/meetings', $data['project']['fullPath']);
    }

    public function testParseAllValidPriorities(): void
    {
        $user = $this->createUser('parse-priorities@example.com', 'Password123');

        // Test all valid priorities (p0 through p4)
        for ($priority = 0; $priority <= 4; $priority++) {
            $response = $this->authenticatedApiRequest(
                $user,
                'POST',
                '/api/v1/parse',
                ['input' => "Task p$priority"]
            );

            $this->assertResponseStatusCode(Response::HTTP_OK, $response);

            $data = $this->getResponseData($response);

            $this->assertEquals($priority, $data['priority'], "Failed for priority p$priority");
            $this->assertTrue($data['highlights'][0]['valid'], "Highlight should be valid for p$priority");
            $this->assertEmpty($data['warnings'], "No warnings expected for valid priority p$priority");
        }
    }
}
