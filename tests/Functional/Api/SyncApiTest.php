<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the Sync API endpoints.
 */
class SyncApiTest extends ApiTestCase
{
    public function testPollRequiresAuthentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/sync/poll?lastVersion=0');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testPollRequiresLastVersion(): void
    {
        $user = $this->createUser('sync-test@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/sync/poll'
        );

        $this->assertResponseStatusCode(Response::HTTP_BAD_REQUEST, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testPollReturnsEmptyForFreshUser(): void
    {
        $user = $this->createUser('sync-fresh@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/sync/poll?lastVersion=0'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('changes', $data);
    }

    public function testPollReturnsChangesAfterTaskCreation(): void
    {
        $user = $this->createUser('sync-changes@example.com', 'Password123');

        // Get initial version
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/sync/poll?lastVersion=0'
        );
        $data = $this->getResponseData($response);
        $initialVersion = $data['version'];

        // Create a task (this should increment the sync version)
        $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            ['title' => 'Sync test task']
        );

        // Poll for changes
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/sync/poll?lastVersion='.$initialVersion
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);

        $this->assertGreaterThan($initialVersion, $data['version']);
        $this->assertNotEmpty($data['changes']);

        $lastChange = end($data['changes']);
        $this->assertSame('task', $lastChange['entityType']);
        $this->assertSame('created', $lastChange['action']);
    }

    public function testPollFiltersOutOriginTabChanges(): void
    {
        $user = $this->createUser('sync-tab@example.com', 'Password123');

        $tabId = 'test-tab-'.bin2hex(random_bytes(8));

        // Create a task with a specific tab ID
        $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            ['title' => 'Tab filtered task'],
            ['X-Tab-Id' => $tabId]
        );

        // Poll with the same tab ID - should exclude our change
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/sync/poll?lastVersion=0',
            null,
            ['X-Tab-Id' => $tabId]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);
        $data = $this->getResponseData($response);

        // Changes from our own tab should be filtered out
        $ownTabChanges = array_filter(
            $data['changes'],
            fn (array $c) => ($c['originTabId'] ?? null) === $tabId
        );
        $this->assertEmpty($ownTabChanges);
    }

    public function testPollReturnsNoChangesWhenUpToDate(): void
    {
        $user = $this->createUser('sync-uptodate@example.com', 'Password123');

        // Create a task
        $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/tasks',
            ['title' => 'Some task']
        );

        // Get current version
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/sync/poll?lastVersion=0'
        );
        $data = $this->getResponseData($response);
        $currentVersion = $data['version'];

        // Poll again with the current version
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/sync/poll?lastVersion='.$currentVersion
        );

        $data = $this->getResponseData($response);
        $this->assertEmpty($data['changes']);
        $this->assertSame($currentVersion, $data['version']);
    }

    public function testPollClampsNegativeLastVersion(): void
    {
        $user = $this->createUser('sync-neg@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/sync/poll?lastVersion=-5'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('changes', $data);
    }

    public function testPollTruncatesLongTabId(): void
    {
        $user = $this->createUser('sync-longtab@example.com', 'Password123');

        $longTabId = str_repeat('a', 200);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/sync/poll?lastVersion=0',
            null,
            ['X-Tab-Id' => $longTabId]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('version', $data);
    }
}
