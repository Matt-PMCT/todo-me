<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\SavedFilter;
use App\Tests\Functional\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for SavedFilter API endpoints.
 *
 * Tests:
 * - GET /api/v1/saved-filters - List saved filters
 * - POST /api/v1/saved-filters - Create saved filter
 * - GET /api/v1/saved-filters/{id} - Get single saved filter
 * - PATCH /api/v1/saved-filters/{id} - Update saved filter
 * - DELETE /api/v1/saved-filters/{id} - Delete saved filter
 * - POST /api/v1/saved-filters/{id}/default - Set default filter
 * - PATCH /api/v1/saved-filters/reorder - Reorder filters
 * - Authentication requirements
 * - User isolation
 */
class SavedFilterApiTest extends ApiTestCase
{
    // ========================================
    // List Saved Filters Tests
    // ========================================

    public function testListSavedFiltersEmpty(): void
    {
        $user = $this->createUser('list-empty@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/saved-filters'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('items', $data);
        $this->assertEmpty($data['items']);
    }

    public function testListSavedFiltersReturnsUserFilters(): void
    {
        $user = $this->createUser('list-filters@example.com', 'Password123');

        $filter1 = $this->createSavedFilter($user, 'High Priority Tasks', ['priority_min' => 4]);
        $filter2 = $this->createSavedFilter($user, 'Pending Tasks', ['statuses' => ['pending']]);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/saved-filters'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('items', $data);
        $this->assertCount(2, $data['items']);

        $names = array_column($data['items'], 'name');
        $this->assertContains('High Priority Tasks', $names);
        $this->assertContains('Pending Tasks', $names);
    }

    // ========================================
    // Create Saved Filter Tests
    // ========================================

    public function testCreateSavedFilterSuccess(): void
    {
        $user = $this->createUser('create-filter@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/saved-filters',
            [
                'name' => 'My Custom Filter',
                'criteria' => [
                    'statuses' => ['pending', 'in_progress'],
                    'priority_min' => 3,
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertArrayHasKey('id', $data);
        $this->assertEquals('My Custom Filter', $data['name']);
        $this->assertArrayHasKey('criteria', $data);
        $this->assertEquals(['pending', 'in_progress'], $data['criteria']['statuses']);
        $this->assertEquals(3, $data['criteria']['priority_min']);
        $this->assertFalse($data['isDefault']);
    }

    public function testCreateSavedFilterWithDefault(): void
    {
        $user = $this->createUser('create-default@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/saved-filters',
            [
                'name' => 'Default Filter',
                'criteria' => ['statuses' => ['pending']],
                'isDefault' => true,
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['isDefault']);
    }

    public function testCreateSavedFilterValidationNameRequired(): void
    {
        $user = $this->createUser('create-no-name@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/saved-filters',
            [
                'criteria' => ['statuses' => ['pending']],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testCreateSavedFilterValidationNameTooLong(): void
    {
        $user = $this->createUser('create-long-name@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/saved-filters',
            [
                'name' => str_repeat('a', 101), // Max is 100
                'criteria' => ['statuses' => ['pending']],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
        $this->assertErrorCode($response, 'VALIDATION_ERROR');
    }

    public function testCreateSavedFilterEmptyCriteriaAllowed(): void
    {
        $user = $this->createUser('create-empty-criteria@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/saved-filters',
            [
                'name' => 'Empty Criteria Filter',
                'criteria' => [],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_CREATED, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Empty Criteria Filter', $data['name']);
        $this->assertEmpty($data['criteria']);
    }

    // ========================================
    // Get Single Saved Filter Tests
    // ========================================

    public function testGetSavedFilterSuccess(): void
    {
        $user = $this->createUser('get-filter@example.com', 'Password123');

        $filter = $this->createSavedFilter($user, 'Test Filter', ['priority_min' => 3]);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/saved-filters/' . $filter->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals($filter->getId(), $data['id']);
        $this->assertEquals('Test Filter', $data['name']);
        $this->assertEquals(['priority_min' => 3], $data['criteria']);
    }

    public function testGetSavedFilterNotFound(): void
    {
        $user = $this->createUser('get-notfound@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/saved-filters/00000000-0000-0000-0000-000000000000'
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
        $this->assertErrorCode($response, 'RESOURCE_NOT_FOUND');
    }

    public function testGetSavedFilterForbiddenForOtherUser(): void
    {
        $user1 = $this->createUser('user1-get@example.com', 'Password123');
        $user2 = $this->createUser('user2-get@example.com', 'Password123');

        $filter = $this->createSavedFilter($user2, 'User 2 Filter', ['statuses' => ['pending']]);

        $response = $this->authenticatedApiRequest(
            $user1,
            'GET',
            '/api/v1/saved-filters/' . $filter->getId()
        );

        // Should return 404 or 403 depending on implementation
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_NOT_FOUND,
            Response::HTTP_FORBIDDEN,
        ]);
    }

    // ========================================
    // Update Saved Filter Tests
    // ========================================

    public function testUpdateSavedFilterSuccess(): void
    {
        $user = $this->createUser('update-filter@example.com', 'Password123');

        $filter = $this->createSavedFilter($user, 'Original Name', ['statuses' => ['pending']]);

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/saved-filters/' . $filter->getId(),
            [
                'name' => 'Updated Name',
                'criteria' => ['statuses' => ['completed']],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Updated Name', $data['name']);
        $this->assertEquals(['statuses' => ['completed']], $data['criteria']);
    }

    public function testUpdateSavedFilterPartialUpdate(): void
    {
        $user = $this->createUser('update-partial@example.com', 'Password123');

        $filter = $this->createSavedFilter($user, 'Original Name', ['statuses' => ['pending']]);

        // Only update name
        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/saved-filters/' . $filter->getId(),
            ['name' => 'Only Name Updated']
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertEquals('Only Name Updated', $data['name']);
        // Criteria should remain unchanged
        $this->assertEquals(['statuses' => ['pending']], $data['criteria']);
    }

    public function testUpdateSavedFilterNotFound(): void
    {
        $user = $this->createUser('update-notfound@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/saved-filters/00000000-0000-0000-0000-000000000000',
            ['name' => 'Updated Name']
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    public function testUpdateSavedFilterForbiddenForOtherUser(): void
    {
        $user1 = $this->createUser('user1-update@example.com', 'Password123');
        $user2 = $this->createUser('user2-update@example.com', 'Password123');

        $filter = $this->createSavedFilter($user2, 'User 2 Filter', ['statuses' => ['pending']]);

        $response = $this->authenticatedApiRequest(
            $user1,
            'PATCH',
            '/api/v1/saved-filters/' . $filter->getId(),
            ['name' => 'Trying to Update']
        );

        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_NOT_FOUND,
            Response::HTTP_FORBIDDEN,
        ]);
    }

    // ========================================
    // Delete Saved Filter Tests
    // ========================================

    public function testDeleteSavedFilterSuccess(): void
    {
        $user = $this->createUser('delete-filter@example.com', 'Password123');

        $filter = $this->createSavedFilter($user, 'Filter to Delete', ['statuses' => ['pending']]);
        $filterId = $filter->getId();

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/saved-filters/' . $filterId
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);
        $this->assertArrayHasKey('message', $data);

        // Verify filter is deleted
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/saved-filters/' . $filterId
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    public function testDeleteSavedFilterNotFound(): void
    {
        $user = $this->createUser('delete-notfound@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'DELETE',
            '/api/v1/saved-filters/00000000-0000-0000-0000-000000000000'
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    public function testDeleteSavedFilterForbiddenForOtherUser(): void
    {
        $user1 = $this->createUser('user1-delete@example.com', 'Password123');
        $user2 = $this->createUser('user2-delete@example.com', 'Password123');

        $filter = $this->createSavedFilter($user2, 'User 2 Filter', ['statuses' => ['pending']]);

        $response = $this->authenticatedApiRequest(
            $user1,
            'DELETE',
            '/api/v1/saved-filters/' . $filter->getId()
        );

        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_NOT_FOUND,
            Response::HTTP_FORBIDDEN,
        ]);
    }

    // ========================================
    // Set Default Filter Tests
    // ========================================

    public function testSetDefaultFilterSuccess(): void
    {
        $user = $this->createUser('set-default@example.com', 'Password123');

        $filter1 = $this->createSavedFilter($user, 'Filter 1', ['statuses' => ['pending']], true);
        $filter2 = $this->createSavedFilter($user, 'Filter 2', ['statuses' => ['completed']]);

        // Initially filter1 is default
        $this->assertTrue($filter1->isDefault());
        $this->assertFalse($filter2->isDefault());

        // Set filter2 as default
        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/saved-filters/' . $filter2->getId() . '/default'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertTrue($data['isDefault']);

        // Verify filter1 is no longer default
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/saved-filters/' . $filter1->getId()
        );

        $data = $this->getResponseData($response);
        $this->assertFalse($data['isDefault']);
    }

    public function testSetDefaultFilterNotFound(): void
    {
        $user = $this->createUser('set-default-notfound@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'POST',
            '/api/v1/saved-filters/00000000-0000-0000-0000-000000000000/default'
        );

        $this->assertResponseStatusCode(Response::HTTP_NOT_FOUND, $response);
    }

    // ========================================
    // Reorder Filters Tests
    // ========================================

    public function testReorderFiltersSuccess(): void
    {
        $user = $this->createUser('reorder-filters@example.com', 'Password123');

        $filter1 = $this->createSavedFilter($user, 'Filter 1', ['statuses' => ['pending']]);
        $filter2 = $this->createSavedFilter($user, 'Filter 2', ['statuses' => ['in_progress']]);
        $filter3 = $this->createSavedFilter($user, 'Filter 3', ['statuses' => ['completed']]);

        // Reorder: 3, 1, 2
        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/saved-filters/reorder',
            [
                'filterIds' => [
                    $filter3->getId(),
                    $filter1->getId(),
                    $filter2->getId(),
                ],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_NO_CONTENT, $response);

        // Verify the new order by listing filters
        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/saved-filters'
        );

        $data = $this->getResponseData($response);

        // Filters should be returned in the new order
        $this->assertEquals('Filter 3', $data['items'][0]['name']);
        $this->assertEquals('Filter 1', $data['items'][1]['name']);
        $this->assertEquals('Filter 2', $data['items'][2]['name']);
    }

    public function testReorderFiltersMissingFilterIds(): void
    {
        $user = $this->createUser('reorder-missing@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/saved-filters/reorder',
            []
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    public function testReorderFiltersInvalidUuid(): void
    {
        $user = $this->createUser('reorder-invalid@example.com', 'Password123');

        $response = $this->authenticatedApiRequest(
            $user,
            'PATCH',
            '/api/v1/saved-filters/reorder',
            ['filterIds' => ['not-a-uuid', 'also-not-a-uuid']]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY, $response);
    }

    // ========================================
    // Authentication Tests
    // ========================================

    public function testListSavedFiltersRequiresAuthentication(): void
    {
        $response = $this->apiRequest('GET', '/api/v1/saved-filters');

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testCreateSavedFilterRequiresAuthentication(): void
    {
        $response = $this->apiRequest(
            'POST',
            '/api/v1/saved-filters',
            [
                'name' => 'Test Filter',
                'criteria' => [],
            ]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testGetSavedFilterRequiresAuthentication(): void
    {
        $response = $this->apiRequest(
            'GET',
            '/api/v1/saved-filters/00000000-0000-0000-0000-000000000000'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testUpdateSavedFilterRequiresAuthentication(): void
    {
        $response = $this->apiRequest(
            'PATCH',
            '/api/v1/saved-filters/00000000-0000-0000-0000-000000000000',
            ['name' => 'Updated']
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testDeleteSavedFilterRequiresAuthentication(): void
    {
        $response = $this->apiRequest(
            'DELETE',
            '/api/v1/saved-filters/00000000-0000-0000-0000-000000000000'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testSetDefaultFilterRequiresAuthentication(): void
    {
        $response = $this->apiRequest(
            'POST',
            '/api/v1/saved-filters/00000000-0000-0000-0000-000000000000/default'
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    public function testReorderFiltersRequiresAuthentication(): void
    {
        $response = $this->apiRequest(
            'PATCH',
            '/api/v1/saved-filters/reorder',
            ['filterIds' => []]
        );

        $this->assertResponseStatusCode(Response::HTTP_UNAUTHORIZED, $response);
    }

    // ========================================
    // User Isolation Tests
    // ========================================

    public function testListSavedFiltersRespectsUserIsolation(): void
    {
        $user1 = $this->createUser('user1-list@example.com', 'Password123');
        $user2 = $this->createUser('user2-list@example.com', 'Password123');

        $this->createSavedFilter($user1, 'User 1 Filter', ['statuses' => ['pending']]);
        $this->createSavedFilter($user2, 'User 2 Filter', ['statuses' => ['completed']]);

        $response = $this->authenticatedApiRequest(
            $user1,
            'GET',
            '/api/v1/saved-filters'
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['items']);
        $this->assertEquals('User 1 Filter', $data['items'][0]['name']);
    }

    // ========================================
    // Response Structure Tests
    // ========================================

    public function testSavedFilterResponseStructure(): void
    {
        $user = $this->createUser('structure@example.com', 'Password123');

        $filter = $this->createSavedFilter($user, 'Complete Filter', [
            'statuses' => ['pending', 'in_progress'],
            'priority_min' => 2,
            'priority_max' => 4,
        ], true);

        $response = $this->authenticatedApiRequest(
            $user,
            'GET',
            '/api/v1/saved-filters/' . $filter->getId()
        );

        $this->assertResponseStatusCode(Response::HTTP_OK, $response);

        $data = $this->getResponseData($response);

        // Verify all expected fields are present
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('criteria', $data);
        $this->assertArrayHasKey('isDefault', $data);
        $this->assertArrayHasKey('position', $data);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertArrayHasKey('updatedAt', $data);
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Creates a saved filter for testing.
     */
    protected function createSavedFilter(
        \App\Entity\User $owner,
        string $name,
        array $criteria = [],
        bool $isDefault = false
    ): SavedFilter {
        $filter = new SavedFilter();
        $filter->setOwner($owner);
        $filter->setName($name);
        $filter->setCriteria($criteria);
        $filter->setIsDefault($isDefault);

        $this->entityManager->persist($filter);
        $this->entityManager->flush();

        return $filter;
    }
}
