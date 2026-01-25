<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\CreateTaskRequest;
use App\DTO\NaturalLanguageTaskRequest;
use App\DTO\TaskFilterRequest;
use App\DTO\TaskListResponse;
use App\DTO\TaskResponse;
use App\DTO\TaskSortRequest;
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
     * - status/statuses: Filter by status (pending, in_progress, completed) - comma-separated or array
     * - priority_min/priority_max: Filter by priority range (1-5)
     * - project_ids: Filter by project UUIDs (comma-separated or array)
     * - include_child_projects: Include tasks from child projects (default: false)
     * - tag_ids: Filter by tag UUIDs (comma-separated or array)
     * - tag_mode: Tag matching mode - AND or OR (default: OR)
     * - search: Search in title and description
     * - due_before: Filter tasks due before date (ISO 8601)
     * - due_after: Filter tasks due after date (ISO 8601)
     * - has_no_due_date: Filter tasks with/without due date
     * - include_completed: Include completed tasks (default: true)
     * - exclude_subtasks: Exclude subtasks from results (default: true)
     * - sort/sort_by: Sort field (due_date, priority, created_at, updated_at, title, position)
     * - direction/order: Sort direction (ASC, DESC)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Extract pagination parameters
        $page = (int) $request->query->get('page', '1');
        $limit = (int) $request->query->get('limit', '20');

        // Check if we should exclude subtasks (default: true)
        $excludeSubtasks = $request->query->getBoolean('exclude_subtasks', true);

        // Build filter and sort request DTOs
        $filterRequest = TaskFilterRequest::fromRequest($request);
        $sortRequest = TaskSortRequest::fromRequest($request);

        // Validate the filter DTO
        $this->validationHelper->validate($filterRequest);

        // Build query with advanced filtering
        if ($excludeSubtasks) {
            $queryBuilder = $this->taskRepository->createTopLevelFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        } else {
            $queryBuilder = $this->taskRepository->createAdvancedFilteredQueryBuilder($user, $filterRequest, $sortRequest);
        }
        $result = $this->paginationHelper->paginate($queryBuilder, $page, $limit);
        $meta = $this->paginationHelper->calculateMeta($result['total'], $page, $limit);

        // Build response
        $response = TaskListResponse::fromTasks($result['items'], $meta);

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Get tasks for Today view (due today + overdue).
     *
     * Query parameters:
     * - page: Page number (default: 1)
     * - limit: Items per page (default: 20, max: 100)
     * - sort/sort_by: Sort field (due_date, priority, created_at, updated_at, completed_at, title, position)
     * - direction/order: Sort direction (ASC, DESC)
     */
    #[Route('/today', name: 'today', methods: ['GET'])]
    public function today(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = (int) $request->query->get('page', '1');
        $limit = (int) $request->query->get('limit', '20');
        $sortRequest = TaskSortRequest::fromRequest($request);

        $qb = $this->taskRepository->createTodayTasksQueryBuilder($user, $sortRequest);
        $result = $this->paginationHelper->paginate($qb, $page, $limit);
        $meta = $this->paginationHelper->calculateMeta($result['total'], $page, $limit);

        return $this->responseFormatter->success(
            TaskListResponse::fromTasks($result['items'], $meta)->toArray()
        );
    }

    /**
     * Get upcoming tasks (due within next N days).
     *
     * Query parameters:
     * - days: Days in the future to include (default: 7, max: 90)
     * - page: Page number (default: 1)
     * - limit: Items per page (default: 20, max: 100)
     * - sort/sort_by: Sort field (due_date, priority, created_at, updated_at, completed_at, title, position)
     * - direction/order: Sort direction (ASC, DESC)
     */
    #[Route('/upcoming', name: 'upcoming', methods: ['GET'])]
    public function upcoming(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $days = (int) $request->query->get('days', '7');
        if ($days < 1 || $days > 90) {
            $days = 7;
        }

        $page = (int) $request->query->get('page', '1');
        $limit = (int) $request->query->get('limit', '20');
        $sortRequest = TaskSortRequest::fromRequest($request);

        $qb = $this->taskRepository->createUpcomingTasksQueryBuilder($user, $days, $sortRequest);
        $result = $this->paginationHelper->paginate($qb, $page, $limit);
        $meta = $this->paginationHelper->calculateMeta($result['total'], $page, $limit);

        return $this->responseFormatter->success(
            TaskListResponse::fromTasks($result['items'], $meta)->toArray()
        );
    }

    /**
     * Get overdue tasks.
     *
     * Query parameters:
     * - page: Page number (default: 1)
     * - limit: Items per page (default: 20, max: 100)
     * - sort/sort_by: Sort field (due_date, priority, created_at, updated_at, completed_at, title, position)
     * - direction/order: Sort direction (ASC, DESC)
     */
    #[Route('/overdue', name: 'overdue', methods: ['GET'])]
    public function overdue(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = (int) $request->query->get('page', '1');
        $limit = (int) $request->query->get('limit', '20');
        $sortRequest = TaskSortRequest::fromRequest($request);

        $qb = $this->taskRepository->createOverdueQueryBuilder($user, $sortRequest);
        $result = $this->paginationHelper->paginate($qb, $page, $limit);
        $meta = $this->paginationHelper->calculateMeta($result['total'], $page, $limit);

        return $this->responseFormatter->success(
            TaskListResponse::fromTasks($result['items'], $meta)->toArray()
        );
    }

    /**
     * Get tasks without a due date.
     *
     * Query parameters:
     * - page: Page number (default: 1)
     * - limit: Items per page (default: 20, max: 100)
     * - sort/sort_by: Sort field (due_date, priority, created_at, updated_at, completed_at, title, position)
     * - direction/order: Sort direction (ASC, DESC)
     */
    #[Route('/no-date', name: 'no_date', methods: ['GET'])]
    public function noDate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = (int) $request->query->get('page', '1');
        $limit = (int) $request->query->get('limit', '20');
        $sortRequest = TaskSortRequest::fromRequest($request);

        $qb = $this->taskRepository->createNoDueDateQueryBuilder($user, $sortRequest);
        $result = $this->paginationHelper->paginate($qb, $page, $limit);
        $meta = $this->paginationHelper->calculateMeta($result['total'], $page, $limit);

        return $this->responseFormatter->success(
            TaskListResponse::fromTasks($result['items'], $meta)->toArray()
        );
    }

    /**
     * Get recently completed tasks.
     *
     * Query parameters:
     * - page: Page number (default: 1)
     * - limit: Items per page (default: 20, max: 100)
     * - sort/sort_by: Sort field (due_date, priority, created_at, updated_at, completed_at, title, position)
     * - direction/order: Sort direction (ASC, DESC)
     */
    #[Route('/completed', name: 'completed', methods: ['GET'])]
    public function completed(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = (int) $request->query->get('page', '1');
        $limit = (int) $request->query->get('limit', '20');
        $sortRequest = TaskSortRequest::fromRequest($request);

        $qb = $this->taskRepository->createCompletedTasksQueryBuilder($user, $sortRequest);
        $result = $this->paginationHelper->paginate($qb, $page, $limit);
        $meta = $this->paginationHelper->calculateMeta($result['total'], $page, $limit);

        return $this->responseFormatter->success(
            TaskListResponse::fromTasks($result['items'], $meta)->toArray()
        );
    }

    /**
     * Create a new task.
     *
     * Standard mode: POST /api/v1/tasks with {title, description, status, priority, dueDate, projectId, tagIds}
     * Natural language mode: POST /api/v1/tasks?parse_natural_language=true with {input_text}
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);

        // Check if natural language parsing is requested
        if ($request->query->getBoolean('parse_natural_language')) {
            try {
                $dto = NaturalLanguageTaskRequest::fromArray($data);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::forField('input_text', $e->getMessage());
            }
            $result = $this->taskService->createFromNaturalLanguage($user, $dto);

            return $this->responseFormatter->created($result->toArray());
        }

        // Standard creation mode
        $dto = CreateTaskRequest::fromArray($data);

        $task = $this->taskService->create($user, $dto);

        $response = TaskResponse::fromTask($task);

        return $this->responseFormatter->created($response->toArray());
    }

    /**
     * Get a single task by ID.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function show(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $task = $this->taskService->findByIdOrFail($id, $user);
        $subtaskCounts = $this->taskRepository->getSubtaskCounts($task);
        $response = TaskResponse::fromTask($task, null, $subtaskCounts);

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Update a task.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $task = $this->taskService->findByIdOrFail($id, $user);

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = UpdateTaskRequest::fromArray($data);

        $result = $this->taskService->update($task, $dto);

        $response = TaskResponse::fromTask($result['task'], $result['undoToken']);

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Delete a task.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
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
     * For recurring tasks that are completed, returns the next instance.
     */
    #[Route('/{id}/status', name: 'change_status', methods: ['PATCH'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function changeStatus(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $task = $this->taskService->findByIdOrFail($id, $user);

        $data = $this->validationHelper->decodeJsonBody($request);

        if (!isset($data['status'])) {
            throw ValidationException::forField('status', 'Status is required');
        }

        $result = $this->taskService->changeStatus($task, $data['status']);

        return $this->responseFormatter->success($result->toArray());
    }

    /**
     * Complete a recurring task permanently (stop recurrence).
     */
    #[Route('/{id}/complete-forever', name: 'complete_forever', methods: ['POST'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function completeForever(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $task = $this->taskService->findByIdOrFail($id, $user);

        $result = $this->taskService->completeForever($task);

        return $this->responseFormatter->success($result->toArray());
    }

    /**
     * Get recurring task history (all instances in the chain).
     */
    #[Route('/{id}/recurring-history', name: 'recurring_history', methods: ['GET'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function recurringHistory(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $task = $this->taskService->findByIdOrFail($id, $user);

        // Determine the original task ID for the chain
        $originalTaskId = $task->getOriginalTask()?->getId() ?? $task->getId();

        $tasks = $this->taskRepository->findRecurringChain($user, $originalTaskId);

        $taskResponses = array_map(
            fn($t) => TaskResponse::fromTask($t)->toArray(),
            $tasks
        );

        return $this->responseFormatter->success([
            'tasks' => $taskResponses,
            'totalCount' => count($tasks),
            'completedCount' => count(array_filter($tasks, fn($t) => $t->isCompleted())),
        ]);
    }

    /**
     * Reschedule a task using natural language or ISO date.
     *
     * PATCH /api/v1/tasks/{id}/reschedule
     * Body: { "due_date": "next Monday" } or { "due_date": "2026-01-27" }
     */
    #[Route('/{id}/reschedule', name: 'reschedule', methods: ['PATCH'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function reschedule(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $task = $this->taskService->findByIdOrFail($id, $user);

        $data = $this->validationHelper->decodeJsonBody($request);

        if (!isset($data['due_date'])) {
            throw ValidationException::forField('due_date', 'due_date is required');
        }

        $result = $this->taskService->reschedule($task, $data['due_date'], $user);

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

        $data = $this->validationHelper->decodeJsonBody($request);

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
     * Get subtasks of a task.
     */
    #[Route('/{id}/subtasks', name: 'subtasks_list', methods: ['GET'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function listSubtasks(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $parentTask = $this->taskService->findByIdOrFail($id, $user);

        $subtasks = $this->taskRepository->findSubtasksByParent($parentTask);

        $taskResponses = array_map(
            fn($task) => TaskResponse::fromTask($task)->toArray(),
            $subtasks
        );

        return $this->responseFormatter->success([
            'tasks' => $taskResponses,
            'total' => count($subtasks),
            'completedCount' => count(array_filter($subtasks, fn($t) => $t->isCompleted())),
        ]);
    }

    /**
     * Create a subtask for a task.
     */
    #[Route('/{id}/subtasks', name: 'subtasks_create', methods: ['POST'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function createSubtask(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Verify parent task exists and user owns it
        $parentTask = $this->taskService->findByIdOrFail($id, $user);

        // Prevent deep nesting
        if ($parentTask->getParentTask() !== null) {
            throw ValidationException::forField('parentTaskId', 'Cannot create subtasks of subtasks (max 1 level of nesting)');
        }

        $data = $this->validationHelper->decodeJsonBody($request);

        // Override parentTaskId with the URL parameter
        $data['parentTaskId'] = $id;

        $dto = CreateTaskRequest::fromArray($data);

        $task = $this->taskService->create($user, $dto);

        $response = TaskResponse::fromTask($task);

        return $this->responseFormatter->created($response->toArray());
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
