<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\CreateProjectRequest;
use App\DTO\MoveProjectRequest;
use App\DTO\ProjectListResponse;
use App\DTO\ProjectResponse;
use App\DTO\ProjectSettingsRequest;
use App\DTO\ReorderProjectsRequest;
use App\DTO\UpdateProjectRequest;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Service\PaginationHelper;
use App\Service\ProjectService;
use App\Service\ResponseFormatter;
use App\Service\ValidationHelper;
use App\ValueObject\UndoToken;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for Project CRUD operations.
 *
 * All endpoints require authentication and operate on the authenticated user's projects.
 */
#[OA\Tag(name: 'Projects', description: 'Project management operations')]
#[Route('/api/v1/projects', name: 'api_projects_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly ProjectRepository $projectRepository,
        private readonly TaskRepository $taskRepository,
        private readonly PaginationHelper $paginationHelper,
        private readonly ResponseFormatter $responseFormatter,
        private readonly ValidationHelper $validationHelper,
    ) {
    }

    /**
     * Builds undo metadata array from an undo token.
     *
     * @param UndoToken|null $undoToken The undo token, or null if no token was created
     *
     * @return array<string, mixed> The undo metadata array, empty if no token
     */
    private function buildUndoMeta(?UndoToken $undoToken): array
    {
        if ($undoToken === null) {
            return [];
        }

        return [
            'undoToken' => $undoToken->token,
            'undoExpiresIn' => $undoToken->getRemainingSeconds(),
        ];
    }

    /**
     * List all projects for the authenticated user with pagination.
     *
     * Query Parameters:
     * - page: Page number (default: 1)
     * - limit: Items per page (default: 20, max: 100)
     * - include_archived: Include archived projects (default: false)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List projects',
        description: 'List all projects for the authenticated user',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
            new OA\Parameter(name: 'include_archived', in: 'query', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Project list'),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = $this->paginationHelper->normalizePage($request->query->getInt('page', 1));
        $limit = $this->paginationHelper->normalizeLimit($request->query->getInt('limit', 20));
        $includeArchived = $request->query->getBoolean('include_archived', false);

        $result = $this->projectRepository->findByOwnerPaginated(
            owner: $user,
            page: $page,
            limit: $limit,
            includeArchived: $includeArchived,
        );

        $projects = $result['projects'];
        $total = $result['total'];

        // Get task counts for all projects in a single query
        $taskCounts = $this->projectRepository->getTaskCountsForProjects($projects);

        // Build response items
        $items = [];
        foreach ($projects as $project) {
            $projectId = $project->getId() ?? '';
            $counts = $taskCounts[$projectId] ?? ['total' => 0, 'completed' => 0];

            $items[] = ProjectResponse::fromEntity(
                $project,
                $counts['total'],
                $counts['completed'],
            );
        }

        $listResponse = ProjectListResponse::create($items, $total, $page, $limit);

        return $this->responseFormatter->success($listResponse->toArray());
    }

    /**
     * Create a new project.
     *
     * Body:
     * - name: Project name (required, max 100 chars)
     * - description: Project description (optional, max 500 chars)
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create project',
        description: 'Create a new project',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 100),
                    new OA\Property(property: 'description', type: 'string', maxLength: 500),
                    new OA\Property(property: 'parentId', type: 'string', format: 'uuid'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Project created'),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = CreateProjectRequest::fromArray($data);

        $project = $this->projectService->create($user, $dto);

        $response = ProjectResponse::fromEntity($project, 0, 0);

        return $this->responseFormatter->created($response->toArray());
    }

    /**
     * Get a single project by ID with task counts.
     */
    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function show(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $project = $this->projectService->findByIdOrFail($id, $user);
        $taskCounts = $this->projectService->getTaskCounts($project);

        $response = ProjectResponse::fromEntity(
            $project,
            $taskCounts['total'],
            $taskCounts['completed'],
        );

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Update an existing project.
     *
     * Body (all fields optional):
     * - name: New project name (max 100 chars)
     * - description: New project description (max 500 chars)
     *
     * Returns the updated project with an undoToken for reverting changes.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $project = $this->projectService->findByIdOrFail($id, $user);

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = UpdateProjectRequest::fromArray($data);

        $result = $this->projectService->update($project, $dto);
        $taskCounts = $this->projectService->getTaskCounts($result['project']);

        $response = ProjectResponse::fromEntity(
            $result['project'],
            $taskCounts['total'],
            $taskCounts['completed'],
        );

        $meta = $this->buildUndoMeta($result['undoToken']);

        return $this->responseFormatter->success($response->toArray(), 200, $meta);
    }

    /**
     * Delete (archive) a project.
     *
     * By default, DELETE archives the project instead of hard deleting.
     * Tasks in the project remain intact but are hidden with the archived project.
     *
     * Returns the archived project with an undoToken.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function delete(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $project = $this->projectService->findByIdOrFail($id, $user);
        $result = $this->projectService->archive($project);
        $taskCounts = $this->projectService->getTaskCounts($result['project']);

        $response = ProjectResponse::fromEntity(
            $result['project'],
            $taskCounts['total'],
            $taskCounts['completed'],
        );

        $data = array_merge(
            ['message' => 'Project archived successfully'],
            ['project' => $response->toArray()]
        );
        $meta = $this->buildUndoMeta($result['undoToken']);

        return $this->responseFormatter->success($data, 200, $meta);
    }

    /**
     * Archive a project.
     *
     * Archived projects are hidden from the default project list but can still be
     * accessed by ID. Tasks in archived projects are not deleted.
     *
     * Query Parameters:
     * - cascade: Archive all descendant projects (default: false)
     * - promote_children: Move children to parent before archiving (default: false)
     *
     * Returns the archived project with an undoToken.
     */
    #[Route('/{id}/archive', name: 'archive', methods: ['PATCH'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function archive(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $cascade = $request->query->getBoolean('cascade', false);
        $promoteChildren = $request->query->getBoolean('promote_children', false);

        $project = $this->projectService->findByIdOrFail($id, $user);
        $result = $this->projectService->archiveWithOptions($project, $cascade, $promoteChildren);
        $taskCounts = $this->projectService->getTaskCounts($result['project']);

        $response = ProjectResponse::fromEntity(
            $result['project'],
            $taskCounts['total'],
            $taskCounts['completed'],
        );

        $meta = $this->buildUndoMeta($result['undoToken']);

        if (!empty($result['affectedProjects'])) {
            $meta['affectedProjects'] = $result['affectedProjects'];
        }

        return $this->responseFormatter->success($response->toArray(), 200, $meta);
    }

    /**
     * Unarchive a project.
     *
     * Query Parameters:
     * - cascade: Unarchive all descendant projects (default: false)
     *
     * Returns the unarchived project with an undoToken.
     */
    #[Route('/{id}/unarchive', name: 'unarchive', methods: ['PATCH'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function unarchive(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $cascade = $request->query->getBoolean('cascade', false);

        $project = $this->projectService->findByIdOrFail($id, $user);
        $result = $this->projectService->unarchiveWithOptions($project, $cascade);
        $taskCounts = $this->projectService->getTaskCounts($result['project']);

        $response = ProjectResponse::fromEntity(
            $result['project'],
            $taskCounts['total'],
            $taskCounts['completed'],
        );

        $meta = $this->buildUndoMeta($result['undoToken']);

        if (!empty($result['affectedProjects'])) {
            $meta['affectedProjects'] = $result['affectedProjects'];
        }

        return $this->responseFormatter->success($response->toArray(), 200, $meta);
    }

    /**
     * Undo the last operation on a project.
     *
     * Supports undoing:
     * - Update: Restores previous name/description
     * - Archive/Unarchive: Restores previous archived state
     * - Delete: Recreates the project (WARNING: tasks are NOT restored)
     *
     * The undo token is single-use and expires after 60 seconds.
     */
    #[Route('/undo/{token}', name: 'undo', methods: ['POST'])]
    public function undo(string $token): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $result = $this->projectService->undo($user, $token);

        $taskCounts = $this->projectService->getTaskCounts($result['project']);

        $response = ProjectResponse::fromEntity(
            $result['project'],
            $taskCounts['total'],
            $taskCounts['completed'],
        );

        $data = [
            'project' => $response->toArray(),
            'message' => $result['message'],
        ];

        $meta = [];
        if ($result['warning'] !== null) {
            $meta['warning'] = $result['warning'];
        }

        return $this->responseFormatter->success($data, 200, $meta);
    }

    /**
     * Get the project tree for the authenticated user.
     *
     * Query Parameters:
     * - include_archived: Include archived projects (default: false)
     * - include_task_counts: Include task counts (default: true)
     */
    #[Route('/tree', name: 'tree', methods: ['GET'])]
    public function tree(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $includeArchived = $request->query->getBoolean('include_archived', false);
        $includeTaskCounts = $request->query->getBoolean('include_task_counts', true);

        $tree = $this->projectService->getTree($user, $includeArchived, $includeTaskCounts);

        return $this->responseFormatter->success(['projects' => $tree]);
    }

    /**
     * Get tasks for a project, optionally including child projects' tasks.
     *
     * Query Parameters:
     * - include_children: Include tasks from child projects (default: uses project's showChildrenTasks setting)
     * - include_archived_projects: Include tasks from archived child projects (default: false)
     * - status: Filter by task status (optional)
     */
    #[Route('/{id}/tasks', name: 'project_tasks', methods: ['GET'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function projectTasks(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $project = $this->projectService->findByIdOrFail($id, $user);

        // Use project's showChildrenTasks setting as default
        $includeChildren = $request->query->has('include_children')
            ? $request->query->getBoolean('include_children')
            : $project->isShowChildrenTasks();
        $includeArchivedProjects = $request->query->getBoolean('include_archived_projects', false);
        $status = $request->query->get('status');

        $tasks = $this->taskRepository->findByProjectWithChildren(
            $project,
            $includeChildren,
            $includeArchivedProjects,
            $status
        );

        $taskData = array_map(fn ($task) => [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'priority' => $task->getPriority(),
            'dueDate' => $task->getDueDate()?->format(\DateTimeInterface::RFC3339),
            'projectId' => $task->getProject()?->getId(),
            'projectName' => $task->getProject()?->getName(),
            'createdAt' => $task->getCreatedAt()->format(\DateTimeInterface::RFC3339),
            'updatedAt' => $task->getUpdatedAt()->format(\DateTimeInterface::RFC3339),
        ], $tasks);

        return $this->responseFormatter->success([
            'tasks' => $taskData,
            'total' => count($tasks),
        ]);
    }

    /**
     * Update project settings.
     *
     * Body:
     * - showChildrenTasks: Whether to show children's tasks (boolean)
     */
    #[Route('/{id}/settings', name: 'settings', methods: ['PATCH'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function settings(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $project = $this->projectService->findByIdOrFail($id, $user);

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = ProjectSettingsRequest::fromArray($data);

        $project = $this->projectService->updateSettings($project, $dto);
        $taskCounts = $this->projectService->getTaskCounts($project);

        $response = ProjectResponse::fromEntity(
            $project,
            $taskCounts['total'],
            $taskCounts['completed'],
        );

        return $this->responseFormatter->success($response->toArray());
    }

    /**
     * Move a project to a new parent.
     *
     * Body:
     * - parentId: New parent project ID (null for root)
     * - position: New position within siblings (optional)
     */
    #[Route('/{id}/move', name: 'move', methods: ['POST'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function move(string $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $project = $this->projectService->findByIdOrFail($id, $user);

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = MoveProjectRequest::fromArray($data);

        $result = $this->projectService->move($project, $dto);
        $taskCounts = $this->projectService->getTaskCounts($result['project']);

        $response = ProjectResponse::fromEntity(
            $result['project'],
            $taskCounts['total'],
            $taskCounts['completed'],
        );

        $meta = $this->buildUndoMeta($result['undoToken']);

        return $this->responseFormatter->success($response->toArray(), 200, $meta);
    }

    /**
     * Reorder projects within a parent.
     *
     * Body:
     * - parentId: Parent project ID (null for root projects)
     * - projectIds: Array of project IDs in desired order
     */
    #[Route('/reorder', name: 'reorder', methods: ['POST'])]
    public function reorder(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = ReorderProjectsRequest::fromArray($data);

        $this->projectService->batchReorder($user, $dto->parentId, $dto->projectIds);

        return $this->responseFormatter->success(['message' => 'Projects reordered successfully']);
    }

    /**
     * List archived projects with pagination.
     *
     * Query Parameters:
     * - page: Page number (default: 1)
     * - limit: Items per page (default: 20, max: 100)
     */
    #[Route('/archived', name: 'archived_list', methods: ['GET'])]
    public function archivedList(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = $this->paginationHelper->normalizePage($request->query->getInt('page', 1));
        $limit = $this->paginationHelper->normalizeLimit($request->query->getInt('limit', 20));

        $result = $this->projectRepository->findArchivedByOwnerPaginated($user, $page, $limit);
        $projects = $result['projects'];
        $total = $result['total'];

        // Get task counts for all projects
        $taskCounts = $this->projectRepository->getTaskCountsForProjects($projects);

        // Build response items
        $items = [];
        foreach ($projects as $project) {
            $projectId = $project->getId() ?? '';
            $counts = $taskCounts[$projectId] ?? ['total' => 0, 'completed' => 0];

            $items[] = ProjectResponse::fromEntity(
                $project,
                $counts['total'],
                $counts['completed'],
            );
        }

        $listResponse = ProjectListResponse::create($items, $total, $page, $limit);

        return $this->responseFormatter->success($listResponse->toArray());
    }
}
