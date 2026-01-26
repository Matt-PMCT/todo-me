<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Functional tests for the Task render HTML API endpoint.
 *
 * Tests:
 * - Render task returns HTML
 * - Render non-existent task returns 404
 * - Render task belonging to another user returns 403
 * - Render task requires authentication
 */
class TaskRenderApiTest extends ApiTestCase
{
    public function testRenderTaskReturnsHtml(): void
    {
        $user = $this->createUser('render-task@example.com', 'Password123');
        $task = $this->createTask($user, 'Test Task to Render', 'A description');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/'.$task->getId().'/render'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('html', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertEquals($task->getId(), $data['id']);
        $this->assertStringContainsString('Test Task to Render', $data['html']);
        $this->assertStringContainsString('<div', $data['html']);
    }

    public function testRenderTaskNotFoundReturns404(): void
    {
        $user = $this->createUser('render-notfound@example.com', 'Password123');

        $nonExistentId = Uuid::v4()->toRfc4122();

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/tasks/'.$nonExistentId.'/render'
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    public function testRenderTaskBelongingToOtherUserReturnsForbidden(): void
    {
        $owner = $this->createUser('render-owner@example.com', 'Password123');
        $otherUser = $this->createUser('render-other@example.com', 'Password123');

        $task = $this->createTask($owner, 'Owner Task');

        $response = $this->authenticatedApiRequest(
            $otherUser,
            'GET',
            '/api/v1/tasks/'.$task->getId().'/render'
        );

        $this->assertResponseStatusCode(Response::HTTP_FORBIDDEN, $response);
    }

    public function testRenderTaskRequiresAuthentication(): void
    {
        $user = $this->createUser('render-auth@example.com', 'Password123');
        $task = $this->createTask($user, 'Auth Test Task');

        $response = $this->apiRequest(
            'GET',
            '/api/v1/tasks/'.$task->getId().'/render'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }
}
