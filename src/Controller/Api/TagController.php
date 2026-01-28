<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\CreateTagRequest;
use App\DTO\TagListResponse;
use App\DTO\TagResponse;
use App\DTO\UpdateTagRequest;
use App\Entity\User;
use App\Repository\TagRepository;
use App\Service\PaginationHelper;
use App\Service\ResponseFormatter;
use App\Service\TagService;
use App\Service\ValidationHelper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for Tag CRUD operations.
 *
 * All endpoints require authentication and operate on the authenticated user's tags.
 */
#[OA\Tag(name: 'Tags', description: 'Tag management operations')]
#[Route('/api/v1/tags', name: 'api_tags_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TagController extends AbstractController
{
    public function __construct(
        private readonly TagService $tagService,
        private readonly TagRepository $tagRepository,
        private readonly PaginationHelper $paginationHelper,
        private readonly ResponseFormatter $responseFormatter,
        private readonly ValidationHelper $validationHelper,
    ) {
    }

    /**
     * List all tags for the authenticated user with pagination.
     *
     * Query Parameters:
     * - page: Page number (default: 1)
     * - limit: Items per page (default: 20, max: 100)
     * - search: Search by name (optional)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List tags',
        description: 'List all tags for the authenticated user',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Tag list'),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = $this->paginationHelper->normalizePage($request->query->getInt('page', 1));
        $limit = $this->paginationHelper->normalizeLimit($request->query->getInt('limit', 20));
        $search = $request->query->getString('search', '');

        $result = $this->tagRepository->findByOwnerPaginated($user, $page, $limit, $search);
        $tags = $result['tags'];
        $total = $result['total'];

        // Get task counts for all tags
        $taskCounts = $this->tagRepository->getTaskCountsForTags($tags);

        // Build response items
        $items = [];
        foreach ($tags as $tag) {
            $tagId = $tag->getId() ?? '';
            $count = $taskCounts[$tagId] ?? 0;
            $items[] = TagResponse::fromEntity($tag, $count);
        }

        $listResponse = TagListResponse::create($items, $total, $page, $limit);

        return $this->responseFormatter->success($listResponse->toArray());
    }

    /**
     * Create a new tag.
     *
     * Body:
     * - name: Tag name (required, max 100 chars)
     * - color: Tag color (optional, hex format)
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create tag',
        description: 'Create a new tag',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 100),
                    new OA\Property(property: 'color', type: 'string', pattern: '^#[0-9A-Fa-f]{6}$'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Tag created'),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = CreateTagRequest::fromArray($data);

        $tag = $this->tagService->create($user, $dto);

        $response = TagResponse::fromEntity($tag, 0);

        return $this->responseFormatter->created($response->toArray());
    }

    /**
     * Get a single tag by ID with task count.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    #[OA\Get(
        summary: 'Get tag',
        description: 'Get a single tag by ID',
        responses: [
            new OA\Response(response: 200, description: 'Tag details'),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 404, description: 'Tag not found'),
        ]
    )]
    public function show(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $tag = $this->tagService->findByIdOrFail($id, $user);
        $taskCounts = $this->tagRepository->getTaskCountsForTags([$tag]);
        $count = $taskCounts[$tag->getId()] ?? 0;

        $response = TagResponse::fromEntity($tag, $count);

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Update an existing tag.
     *
     * Body (all fields optional):
     * - name: New tag name (max 100 chars)
     * - color: New tag color (hex format)
     */
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    #[OA\Put(
        summary: 'Update tag',
        description: 'Update an existing tag',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 100),
                    new OA\Property(property: 'color', type: 'string', pattern: '^#[0-9A-Fa-f]{6}$'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Tag updated'),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 404, description: 'Tag not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $tag = $this->tagService->findByIdOrFail($id, $user);

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = UpdateTagRequest::fromArray($data);

        $tag = $this->tagService->update($tag, $dto);

        $taskCounts = $this->tagRepository->getTaskCountsForTags([$tag]);
        $count = $taskCounts[$tag->getId()] ?? 0;

        $response = TagResponse::fromEntity($tag, $count);

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Delete a tag.
     *
     * The tag will be removed from all associated tasks.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    #[OA\Delete(
        summary: 'Delete tag',
        description: 'Delete a tag. It will be removed from all associated tasks.',
        responses: [
            new OA\Response(response: 200, description: 'Tag deleted'),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 404, description: 'Tag not found'),
        ]
    )]
    public function delete(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $tag = $this->tagService->findByIdOrFail($id, $user);
        $tagName = $tag->getName();

        $this->tagService->delete($tag);

        return $this->responseFormatter->success([
            'message' => sprintf('Tag "%s" deleted successfully', $tagName),
        ]);
    }
}
