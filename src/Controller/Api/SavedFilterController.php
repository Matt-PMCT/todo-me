<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\CreateSavedFilterRequest;
use App\DTO\SavedFilterResponse;
use App\DTO\UpdateSavedFilterRequest;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\SavedFilterRepository;
use App\Service\ResponseFormatter;
use App\Service\SavedFilterService;
use App\Service\ValidationHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/saved-filters', name: 'api_saved_filters_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SavedFilterController extends AbstractController
{
    public function __construct(
        private readonly SavedFilterService $savedFilterService,
        private readonly SavedFilterRepository $savedFilterRepository,
        private readonly ResponseFormatter $responseFormatter,
        private readonly ValidationHelper $validationHelper,
    ) {
    }

    /**
     * List all saved filters for the current user.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $filters = $this->savedFilterRepository->findByOwner($user);

        $items = array_map(
            fn ($filter) => SavedFilterResponse::fromEntity($filter)->toArray(),
            $filters
        );

        return $this->responseFormatter->success(['items' => $items]);
    }

    /**
     * Create a new saved filter.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = CreateSavedFilterRequest::fromArray($data);

        $filter = $this->savedFilterService->create($user, $dto);
        $response = SavedFilterResponse::fromEntity($filter);

        return $this->responseFormatter->created($response->toArray());
    }

    /**
     * Get a single saved filter.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function show(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $filter = $this->savedFilterService->findByIdOrFail($id, $user);
        $response = SavedFilterResponse::fromEntity($filter);

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Update a saved filter.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $filter = $this->savedFilterService->findByIdOrFail($id, $user);

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = UpdateSavedFilterRequest::fromArray($data);

        $updatedFilter = $this->savedFilterService->update($filter, $dto);
        $response = SavedFilterResponse::fromEntity($updatedFilter);

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Delete a saved filter.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function delete(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $filter = $this->savedFilterService->findByIdOrFail($id, $user);
        $this->savedFilterService->delete($filter);

        return $this->responseFormatter->success(['message' => 'Saved filter deleted successfully']);
    }

    /**
     * Set a saved filter as the default.
     */
    #[Route('/{id}/default', name: 'set_default', methods: ['POST'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function setDefault(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $filter = $this->savedFilterService->findByIdOrFail($id, $user);
        $updatedFilter = $this->savedFilterService->setAsDefault($filter);
        $response = SavedFilterResponse::fromEntity($updatedFilter);

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Reorder saved filters.
     */
    #[Route('/reorder', name: 'reorder', methods: ['PATCH'])]
    public function reorder(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);

        if (!isset($data['filterIds']) || !is_array($data['filterIds'])) {
            throw ValidationException::forField('filterIds', 'Filter IDs array is required');
        }

        // Validate all IDs are valid UUIDs
        foreach ($data['filterIds'] as $filterId) {
            if (!$this->validationHelper->validateUuid($filterId)) {
                throw ValidationException::forField('filterIds', sprintf('Invalid UUID: %s', $filterId));
            }
        }

        $this->savedFilterService->reorder($user, $data['filterIds']);

        return $this->responseFormatter->noContent();
    }
}
