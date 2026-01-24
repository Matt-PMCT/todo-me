<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\SearchRequest;
use App\Entity\User;
use App\Service\ResponseFormatter;
use App\Service\SearchService;
use App\Service\ValidationHelper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for global search operations.
 */
#[OA\Tag(name: 'Search', description: 'Global search across tasks, projects, and tags')]
#[Route('/api/v1', name: 'api_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SearchController extends AbstractController
{
    public function __construct(
        private readonly SearchService $searchService,
        private readonly ResponseFormatter $responseFormatter,
        private readonly ValidationHelper $validationHelper,
    ) {
    }

    /**
     * Global search across tasks, projects, and tags.
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    #[OA\Get(
        summary: 'Global search',
        description: 'Search across tasks, projects, and tags. Tasks use full-text search, projects and tags use ILIKE.',
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: true, description: 'Search query', schema: new OA\Schema(type: 'string', minLength: 1, maxLength: 200)),
            new OA\Parameter(name: 'type', in: 'query', description: 'Filter by entity type', schema: new OA\Schema(type: 'string', enum: ['all', 'tasks', 'projects', 'tags'], default: 'all')),
            new OA\Parameter(name: 'page', in: 'query', description: 'Page number', schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', description: 'Items per page', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Search results',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiResponse'),
                        new OA\Schema(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/SearchResult')]),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function search(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $searchRequest = SearchRequest::fromArray($request->query->all());

        // Validate the request
        $this->validationHelper->validate($searchRequest);

        $response = $this->searchService->search($user, $searchRequest);

        return $this->responseFormatter->success($response->toArray());
    }
}
