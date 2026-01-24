<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Project;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for the Autocomplete API endpoints.
 *
 * Tests:
 * - Project autocomplete search
 * - Tag autocomplete search
 * - Authentication requirements
 * - Query parameter handling
 * - Multi-tenant data isolation
 */
class AutocompleteApiTest extends ApiTestCase
{
    // ========================================
    // Project Autocomplete Tests
    // ========================================

    public function testProjectAutocompleteEmpty(): void
    {
        $user = $this->createUser('autocomplete-empty@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/projects?q=work'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('query', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertEmpty($data['items']);
        $this->assertEquals('work', $data['query']);
        $this->assertEquals(0, $data['count']);
    }

    public function testProjectAutocompleteFindsMatches(): void
    {
        $user = $this->createUser('autocomplete-match@example.com', 'Password123');

        $this->createProject($user, 'Work');
        $this->createProject($user, 'Work Projects');
        $this->createProject($user, 'Personal');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/projects?q=work'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['items']);
        $this->assertEquals(2, $data['count']);

        // Verify structure
        $this->assertArrayHasKey('id', $data['items'][0]);
        $this->assertArrayHasKey('name', $data['items'][0]);
        $this->assertArrayHasKey('fullPath', $data['items'][0]);
        $this->assertArrayHasKey('color', $data['items'][0]);
        $this->assertArrayHasKey('parent', $data['items'][0]);
    }

    public function testProjectAutocompleteEmptyQuery(): void
    {
        $user = $this->createUser('autocomplete-all@example.com', 'Password123');

        $this->createProject($user, 'Alpha');
        $this->createProject($user, 'Beta');
        $this->createProject($user, 'Gamma');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/projects?q='
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should return projects (ordered by name)
        $this->assertCount(3, $data['items']);
    }

    public function testProjectAutocompleteCaseInsensitive(): void
    {
        $user = $this->createUser('autocomplete-case@example.com', 'Password123');

        $this->createProject($user, 'WorkProjects');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/projects?q=WORKP'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('WorkProjects', $data['items'][0]['name']);
    }

    public function testProjectAutocompleteWithLimit(): void
    {
        $user = $this->createUser('autocomplete-limit@example.com', 'Password123');

        // Create 15 projects starting with 'P'
        for ($i = 1; $i <= 15; $i++) {
            $this->createProject($user, sprintf('Project%02d', $i));
        }

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/projects?q=Project&limit=5'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(5, $data['items']);
    }

    public function testProjectAutocompleteLimitMax(): void
    {
        $user = $this->createUser('autocomplete-maxlimit@example.com', 'Password123');

        // Try to request more than max (50)
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/projects?q=test&limit=100'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        // Should not error, just cap at max
    }

    public function testProjectAutocompleteExcludesArchived(): void
    {
        $user = $this->createUser('autocomplete-archived@example.com', 'Password123');

        $this->createProject($user, 'Project Active');
        $this->createProject($user, 'Project Archived', null, true);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/projects?q=project'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should only return active project (prefix search matches "Project Active")
        $this->assertCount(1, $data['items']);
        $this->assertEquals('Project Active', $data['items'][0]['name']);
    }

    public function testProjectAutocompleteWithNestedProjects(): void
    {
        $user = $this->createUser('autocomplete-nested@example.com', 'Password123');

        $parent = $this->createProject($user, 'Work');
        $child = new Project();
        $child->setOwner($user);
        $child->setName('Meetings');
        $child->setParent($parent);
        $this->entityManager->persist($child);
        $this->entityManager->flush();

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/projects?q=meet'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('Meetings', $data['items'][0]['name']);
        $this->assertEquals('Work/Meetings', $data['items'][0]['fullPath']);
        $this->assertNotNull($data['items'][0]['parent']);
        $this->assertEquals('Work', $data['items'][0]['parent']['name']);
    }

    public function testProjectAutocompleteOnlyOwned(): void
    {
        $user1 = $this->createUser('user1-projects@example.com', 'Password123');
        $user2 = $this->createUser('user2-projects@example.com', 'Password123');

        $this->createProject($user1, 'Shared Name Project');
        $this->createProject($user2, 'Shared Name Project');

        $response = $this->authenticatedApiRequest(
            $user1,
            'GET',
            '/api/v1/autocomplete/projects?q=shared'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should only return user1's project (prefix search matches "Shared Name Project")
        $this->assertCount(1, $data['items']);
        $this->assertEquals('Shared Name Project', $data['items'][0]['name']);
    }

    public function testProjectAutocompleteUnauthenticated(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/autocomplete/projects?q=work');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Tag Autocomplete Tests
    // ========================================

    public function testTagAutocompleteEmpty(): void
    {
        $user = $this->createUser('tag-autocomplete-empty@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/tags?q=urgent'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('items', $data);
        $this->assertEmpty($data['items']);
        $this->assertEquals('urgent', $data['query']);
        $this->assertEquals(0, $data['count']);
    }

    public function testTagAutocompleteFindsMatches(): void
    {
        $user = $this->createUser('tag-autocomplete-match@example.com', 'Password123');

        $this->createTag($user, 'urgent', '#FF0000');
        $this->createTag($user, 'urgently-needed', '#FF5500');
        $this->createTag($user, 'important', '#0000FF');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/tags?q=urge'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['items']);
        $this->assertEquals(2, $data['count']);

        // Verify structure
        $this->assertArrayHasKey('id', $data['items'][0]);
        $this->assertArrayHasKey('name', $data['items'][0]);
        $this->assertArrayHasKey('color', $data['items'][0]);
    }

    public function testTagAutocompleteEmptyQuery(): void
    {
        $user = $this->createUser('tag-autocomplete-all@example.com', 'Password123');

        $this->createTag($user, 'bug');
        $this->createTag($user, 'feature');
        $this->createTag($user, 'enhancement');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/tags?q='
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should return all tags
        $this->assertCount(3, $data['items']);
    }

    public function testTagAutocompleteCaseInsensitive(): void
    {
        $user = $this->createUser('tag-autocomplete-case@example.com', 'Password123');

        $this->createTag($user, 'Urgent');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/tags?q=URGE'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('Urgent', $data['items'][0]['name']);
    }

    public function testTagAutocompleteWithLimit(): void
    {
        $user = $this->createUser('tag-autocomplete-limit@example.com', 'Password123');

        // Create 15 tags starting with 'T'
        for ($i = 1; $i <= 15; $i++) {
            $this->createTag($user, sprintf('Tag%02d', $i));
        }

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/tags?q=Tag&limit=5'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(5, $data['items']);
    }

    public function testTagAutocompleteLimitMax(): void
    {
        $user = $this->createUser('tag-autocomplete-maxlimit@example.com', 'Password123');

        // Try to request more than max (50)
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/tags?q=test&limit=100'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        // Should not error, just cap at max
    }

    public function testTagAutocompleteOnlyOwned(): void
    {
        $user1 = $this->createUser('user1-tags@example.com', 'Password123');
        $user2 = $this->createUser('user2-tags@example.com', 'Password123');

        $this->createTag($user1, 'user1-tag');
        $this->createTag($user2, 'user2-tag');

        $response = $this->authenticatedApiRequest(
            $user1,
            'GET',
            '/api/v1/autocomplete/tags?q=user'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('user1-tag', $data['items'][0]['name']);
    }

    public function testTagAutocompleteUnauthenticated(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/autocomplete/tags?q=urgent');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // Response Structure Tests
    // ========================================

    public function testProjectAutocompleteResponseStructure(): void
    {
        $user = $this->createUser('structure-project@example.com', 'Password123');
        $project = $this->createProject($user, 'Test Project');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/projects?q=test'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $fullResponse = $this->assertSuccessResponse($response);

        // Verify full API response structure
        $this->assertArrayHasKey('success', $fullResponse);
        $this->assertArrayHasKey('data', $fullResponse);
        $this->assertArrayHasKey('error', $fullResponse);
        $this->assertArrayHasKey('meta', $fullResponse);
        $this->assertTrue($fullResponse['success']);
        $this->assertNull($fullResponse['error']);

        // Verify data structure
        $data = $fullResponse['data'];
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('query', $data);
        $this->assertArrayHasKey('count', $data);

        // Verify item structure
        $item = $data['items'][0];
        $this->assertEquals($project->getId(), $item['id']);
        $this->assertEquals('Test Project', $item['name']);
        $this->assertEquals('Test Project', $item['fullPath']);
        $this->assertIsString($item['color']);
        $this->assertNull($item['parent']);
    }

    public function testTagAutocompleteResponseStructure(): void
    {
        $user = $this->createUser('structure-tag@example.com', 'Password123');
        $tag = $this->createTag($user, 'test-tag', '#123456');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/tags?q=test'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $fullResponse = $this->assertSuccessResponse($response);

        // Verify item structure
        $item = $fullResponse['data']['items'][0];
        $this->assertEquals($tag->getId(), $item['id']);
        $this->assertEquals('test-tag', $item['name']);
        $this->assertEquals('#123456', $item['color']);
    }

    public function testInvalidLimitDefaultsTo10(): void
    {
        $user = $this->createUser('invalid-limit@example.com', 'Password123');

        // Create 15 projects
        for ($i = 1; $i <= 15; $i++) {
            $this->createProject($user, "Project$i");
        }

        // Send invalid limit
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/autocomplete/projects?q=Project&limit=-5'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Should default to 10
        $this->assertCount(10, $data['items']);
    }
}
