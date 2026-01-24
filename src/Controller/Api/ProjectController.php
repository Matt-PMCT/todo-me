<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\CreateProjectRequest;
use App\DTO\ProjectListResponse;
use App\DTO\ProjectResponse;
use App\DTO\UpdateProjectRequest;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Service\ProjectService;
use App\Service\ResponseFormatter;
use App\Service\ValidationHelper;
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
#[Route('/api/v1/projects', name: 'api_projects_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectService $projectService,
        private readonly ProjectRepository $projectRepository,
        private readonly ResponseFormatter $responseFormatter,
        private readonly ValidationHelper $validationHelper,
    ) {
    }

    /**
     * List all projects for the authenticated user with pagination.
     *
     * Query Parameters:
     * - page: Page number (default: 1)
     * - limit: Items per page (default: 20, max: 100)
     * - includeArchived: Include archived projects (default: false)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(100, max(1, $request->query->getInt('limit', 20)));
        $includeArchived = $request->query->getBoolean('includeArchived', false);

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

        $meta = [];
        if ($result['undoToken'] !== null) {
            $meta['undoToken'] = $result['undoToken']->token;
            $meta['undoExpiresIn'] = $result['undoToken']->getRemainingSeconds();
        }

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

        $meta = [];
        if ($result['undoToken'] !== null) {
            $meta['undoToken'] = $result['undoToken']->token;
            $meta['undoExpiresIn'] = $result['undoToken']->getRemainingSeconds();
        }

        return $this->responseFormatter->success($response->toArray(), 200, $meta);
    }

    /**
     * Archive a project.
     *
     * Archived projects are hidden from the default project list but can still be
     * accessed by ID. Tasks in archived projects are not deleted.
     *
     * Returns the archived project with an undoToken.
     */
    #[Route('/{id}/archive', name: 'archive', methods: ['PATCH'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function archive(string $id): JsonResponse
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

        $meta = [];
        if ($result['undoToken'] !== null) {
            $meta['undoToken'] = $result['undoToken']->token;
            $meta['undoExpiresIn'] = $result['undoToken']->getRemainingSeconds();
        }

        return $this->responseFormatter->success($response->toArray(), 200, $meta);
    }

    /**
     * Unarchive a project.
     *
     * Returns the unarchived project with an undoToken.
     */
    #[Route('/{id}/unarchive', name: 'unarchive', methods: ['PATCH'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    public function unarchive(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $project = $this->projectService->findByIdOrFail($id, $user);
        $result = $this->projectService->unarchive($project);
        $taskCounts = $this->projectService->getTaskCounts($result['project']);

        $response = ProjectResponse::fromEntity(
            $result['project'],
            $taskCounts['total'],
            $taskCounts['completed'],
        );

        $meta = [];
        if ($result['undoToken'] !== null) {
            $meta['undoToken'] = $result['undoToken']->token;
            $meta['undoExpiresIn'] = $result['undoToken']->getRemainingSeconds();
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

        if ($result['warning'] !== null) {
            $data['warning'] = $result['warning'];
        }

        return $this->responseFormatter->success($data);
    }
}
