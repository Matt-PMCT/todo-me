<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\CreateTaskRequest;
use App\DTO\TaskListResponse;
use App\DTO\TaskResponse;
use App\DTO\UpdateTaskRequest;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Repository\TaskRepository;
use App\Service\PaginationHelper;
use App\Service\ResponseFormatter;
use App\Service\TaskService;
use App\Service\ValidationHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for task CRUD operations.
 */
#[Route('/api/v1/tasks', name: 'api_tasks_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TaskController extends AbstractController
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly TaskRepository $taskRepository,
        private readonly PaginationHelper $paginationHelper,
        private readonly ResponseFormatter $responseFormatter,
        private readonly ValidationHelper $validationHelper,
    ) {
    }

    /**
     * List tasks with pagination and filters.
     *
     * Query parameters:
     * - page: Page number (default: 1)
     * - limit: Items per page (default: 20, max: 100)
     * - status: Filter by status (pending, in_progress, completed)
     * - priority: Filter by priority (1-5)
     * - projectId: Filter by project UUID
     * - search: Search in title and description
     * - dueBefore: Filter tasks due before date (ISO 8601)
     * - dueAfter: Filter tasks due after date (ISO 8601)
     * - tagIds: Filter by tag UUIDs (comma-separated)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Extract pagination parameters
        $page = (int) $request->query->get('page', '1');
        $limit = (int) $request->query->get('limit', '20');

        // Extract filters
        $filters = [];

        if ($request->query->has('status')) {
            $filters['status'] = $request->query->get('status');
        }

        if ($request->query->has('priority')) {
            $filters['priority'] = (int) $request->query->get('priority');
        }

        if ($request->query->has('projectId')) {
            $filters['projectId'] = $request->query->get('projectId');
        }

        if ($request->query->has('search')) {
            $filters['search'] = $request->query->get('search');
        }

        if ($request->query->has('dueBefore')) {
            $filters['dueBefore'] = $request->query->get('dueBefore');
        }

        if ($request->query->has('dueAfter')) {
            $filters['dueAfter'] = $request->query->get('dueAfter');
        }

        if ($request->query->has('tagIds')) {
            $tagIdsParam = $request->query->get('tagIds');
            $filters['tagIds'] = array_filter(explode(',', $tagIdsParam));
        }

        // Build query and paginate
        $queryBuilder = $this->taskRepository->findByOwnerPaginatedQueryBuilder($user, $filters);
        $result = $this->paginationHelper->paginate($queryBuilder, $page, $limit);
        $meta = $this->paginationHelper->calculateMeta($result['total'], $page, $limit);

        // Build response
        $response = TaskListResponse::fromTasks($result['items'], $meta);

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Create a new task.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];
        $dto = CreateTaskRequest::fromArray($data);

        $task = $this->taskService->create($user, $dto);

        $response = TaskResponse::fromTask($task);

        return $this->responseFormatter->created($response->toArray());
    }

    /**
     * Get a single task by ID.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $task = $this->taskService->findByIdOrFail($id, $user);
        $response = TaskResponse::fromTask($task);

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Update a task.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $task = $this->taskService->findByIdOrFail($id, $user);

        $data = json_decode($request->getContent(), true) ?? [];
        $dto = UpdateTaskRequest::fromArray($data);

        $result = $this->taskService->update($task, $dto);

        $response = TaskResponse::fromTask($result['task'], $result['undoToken']);

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Delete a task.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $task = $this->taskService->findByIdOrFail($id, $user);

        $undoToken = $this->taskService->delete($task);

        $data = [
            'message' => 'Task deleted successfully',
        ];

        if ($undoToken !== null) {
            $data['undoToken'] = $undoToken->token;
        }

        return $this->responseFormatter->success($data);
    }

    /**
     * Change task status.
     */
    #[Route('/{id}/status', name: 'change_status', methods: ['PATCH'])]
    public function changeStatus(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $task = $this->taskService->findByIdOrFail($id, $user);

        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['status'])) {
            throw ValidationException::forField('status', 'Status is required');
        }

        $result = $this->taskService->changeStatus($task, $data['status']);

        $response = TaskResponse::fromTask($result['task'], $result['undoToken']);

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Reorder tasks.
     */
    #[Route('/reorder', name: 'reorder', methods: ['PATCH'])]
    public function reorder(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['taskIds']) || !is_array($data['taskIds'])) {
            throw ValidationException::forField('taskIds', 'Task IDs array is required');
        }

        // Validate that all IDs are valid UUIDs
        foreach ($data['taskIds'] as $taskId) {
            if (!$this->validationHelper->validateUuid($taskId)) {
                throw ValidationException::forField('taskIds', sprintf('Invalid UUID: %s', $taskId));
            }
        }

        $this->taskService->reorder($user, $data['taskIds']);

        return $this->responseFormatter->noContent();
    }

    /**
     * Undo a task operation.
     */
    #[Route('/undo/{token}', name: 'undo', methods: ['POST'])]
    public function undo(string $token): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $task = $this->taskService->undo($user, $token);
        $response = TaskResponse::fromTask($task);

        return $this->responseFormatter->success($response->toArray());
    }
}
