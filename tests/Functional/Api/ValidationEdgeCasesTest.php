<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Task;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for validation edge cases across API endpoints.
 *
 * Tests boundary values, malformed input, and field-specific validation.
 */
class ValidationEdgeCasesTest extends ApiTestCase
{
    // ========================================
    // Task Title Validation
    // ========================================

    public function testCreateTaskWithMaxLengthTitle(): void
    {
        $user = $this->createUser('test@example.com');
        $maxTitle = str_repeat('a', 500);

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            ['title' => $maxTitle]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);
    }

    public function testCreateTaskWithTooLongTitle(): void
    {
        $user = $this->createUser('test@example.com');
        $tooLongTitle = str_repeat('a', 501);

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            ['title' => $tooLongTitle]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');

        $error = $this->getResponseError($response);
        $this->assertArrayHasKey('title', $error['details']['fields'] ?? []);
    }

    public function testCreateTaskWithWhitespaceOnlyTitle(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            ['title' => '   ']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    // ========================================
    // Task Description Validation
    // ========================================

    public function testCreateTaskWithMaxLengthDescription(): void
    {
        $user = $this->createUser('test@example.com');
        $maxDescription = str_repeat('a', 2000);

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Valid Title',
                'description' => $maxDescription,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);
    }

    public function testCreateTaskWithTooLongDescription(): void
    {
        $user = $this->createUser('test@example.com');
        $tooLongDescription = str_repeat('a', 2001);

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Valid Title',
                'description' => $tooLongDescription,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    // ========================================
    // Task Status Validation
    // ========================================

    public function testCreateTaskWithInvalidStatus(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Valid Title',
                'status' => 'invalid_status',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    /**
     * @dataProvider validStatusesProvider
     */
    public function testCreateTaskWithValidStatus(string $status): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Valid Title',
                'status' => $status,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);
    }

    public static function validStatusesProvider(): array
    {
        return [
            'pending' => [Task::STATUS_PENDING],
            'in_progress' => [Task::STATUS_IN_PROGRESS],
            'completed' => [Task::STATUS_COMPLETED],
        ];
    }

    // ========================================
    // Task Priority Validation
    // ========================================

    public function testCreateTaskWithPriorityBelowMin(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Valid Title',
                'priority' => -1,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    public function testCreateTaskWithPriorityAboveMax(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Valid Title',
                'priority' => 5,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    /**
     * @dataProvider validPrioritiesProvider
     */
    public function testCreateTaskWithValidPriority(int $priority): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Valid Title',
                'priority' => $priority,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);
    }

    public static function validPrioritiesProvider(): array
    {
        return [
            'min priority' => [0],
            'mid priority' => [2],
            'max priority' => [4],
        ];
    }

    // ========================================
    // Task Due Date Validation
    // ========================================

    public function testCreateTaskWithInvalidDateFormat(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Valid Title',
                'dueDate' => 'not-a-date',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    /**
     * @dataProvider validDateFormatsProvider
     */
    public function testCreateTaskWithValidDateFormat(string $date): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Valid Title',
                'dueDate' => $date,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);
    }

    public static function validDateFormatsProvider(): array
    {
        return [
            'ISO date' => ['2024-12-31'],
            'ISO datetime' => ['2024-12-31T23:59:59'],
        ];
    }

    // ========================================
    // Project Name Validation
    // ========================================

    public function testCreateProjectWithMaxLengthName(): void
    {
        $user = $this->createUser('test@example.com');
        $maxName = str_repeat('a', 100);

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects',
            ['name' => $maxName]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);
    }

    public function testCreateProjectWithTooLongName(): void
    {
        $user = $this->createUser('test@example.com');
        $tooLongName = str_repeat('a', 101);

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects',
            ['name' => $tooLongName]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    public function testCreateProjectWithEmptyName(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects',
            ['name' => '']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    // ========================================
    // Project Description Validation
    // ========================================

    public function testCreateProjectWithMaxLengthDescription(): void
    {
        $user = $this->createUser('test@example.com');
        $maxDescription = str_repeat('a', 500);

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects',
            [
                'name' => 'Valid Name',
                'description' => $maxDescription,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);
    }

    public function testCreateProjectWithTooLongDescription(): void
    {
        $user = $this->createUser('test@example.com');
        $tooLongDescription = str_repeat('a', 501);

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects',
            [
                'name' => 'Valid Name',
                'description' => $tooLongDescription,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    // ========================================
    // UUID Validation
    // ========================================

    public function testCreateTaskWithInvalidProjectUuid(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Valid Title',
                'projectId' => 'not-a-uuid',
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    public function testCreateTaskWithInvalidTagUuid(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Valid Title',
                'tagIds' => ['not-a-uuid'],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    public function testCreateTaskWithMixedValidInvalidTagUuids(): void
    {
        $user = $this->createUser('test@example.com');
        $tag = $this->createTag($user, 'Valid Tag');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            [
                'title' => 'Valid Title',
                'tagIds' => [$tag->getId(), 'not-a-uuid'],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    // ========================================
    // Registration Validation
    // ========================================

    public function testRegisterWithTooLongEmail(): void
    {
        $tooLongEmail = str_repeat('a', 170) . '@example.com';

        $response = $this->apiRequest('POST', '/api/v1/auth/register', [
            'email' => $tooLongEmail,
            'password' => 'SecurePassword123',
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    public function testRegisterWithMinimumPasswordLength(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/register', [
            'email' => 'minpass@example.com',
            'password' => '12345678', // Exactly 8 characters
        ]);

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);
    }

    public function testRegisterWithPasswordBelowMinimum(): void
    {
        $response = $this->apiRequest('POST', '/api/v1/auth/register', [
            'email' => 'shortpass@example.com',
            'password' => '1234567', // 7 characters
        ]);

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    // ========================================
    // Special Character Handling
    // ========================================

    public function testCreateTaskWithUnicodeTitle(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            ['title' => 'Task with emoji 🎉 and unicode: 日本語']
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);
        $data = $this->getResponseData($response);
        $this->assertStringContainsString('🎉', $data['title']);
    }

    public function testCreateProjectWithHtmlInName(): void
    {
        $user = $this->createUser('test@example.com');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/projects',
            ['name' => '<script>alert("xss")</script>']
        );

        // Should be created (validation doesn't strip HTML)
        // XSS protection happens at output, not input
        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);
    }
}
