<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\DTO\CreateProjectRequest;
use App\DTO\CreateTaskRequest;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;
use App\Service\ProjectService;
use App\Service\TaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for web task list operations.
 */
#[IsGranted('ROLE_USER')]
class TaskListController extends AbstractController
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly TagRepository $tagRepository,
        private readonly TaskService $taskService,
        private readonly ProjectService $projectService,
    ) {
    }

    #[Route('/tasks', name: 'app_task_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get filter parameters - always provide all keys for template
        $status = $request->query->get('status');
        $priority = $request->query->get('priority');
        $projectId = $request->query->get('projectId');
        $isRecurring = $request->query->get('isRecurring');
        // Issue #39: Show/hide completed tasks toggle (defaults to hiding completed)
        $showCompleted = $request->query->getBoolean('showCompleted', false);

        $filters = [
            'status' => $status ?: null,
            'priority' => $priority ? (int) $priority : null,
            'projectId' => $projectId ?: null,
            'isRecurring' => $isRecurring !== null && $isRecurring !== '' ? (bool) (int) $isRecurring : null,
        ];

        // Get tasks with filters
        $queryBuilder = $this->taskRepository->createFilteredQueryBuilder($user, $filters);

        // Issue #39: Hide completed tasks by default unless explicitly shown or status filter is set
        if (!$showCompleted && $status !== 'completed') {
            $queryBuilder->andWhere('t.status != :completedStatus')
                ->setParameter('completedStatus', 'completed');
        }

        $tasks = $queryBuilder->getQuery()->getResult();

        // Get user's projects for filter dropdown
        $projects = $this->projectRepository->findByOwner($user);

        // Check if grouping by project is requested
        $groupByProject = $request->query->getBoolean('groupByProject', false);

        // Group tasks by project efficiently in PHP if requested
        $groupedTasks = [];
        if ($groupByProject) {
            foreach ($tasks as $task) {
                $project = $task->getProject();
                $projectKey = $project ? (string) $project->getId() : 'no_project';

                if (!isset($groupedTasks[$projectKey])) {
                    $groupedTasks[$projectKey] = [
                        'name' => $project ? $project->getName() : 'No Project',
                        'tasks' => [],
                    ];
                }

                $groupedTasks[$projectKey]['tasks'][] = $task;
            }
        }

        // Get sidebar data
        $sidebarProjects = $this->projectService->getTree($user);
        $tags = $this->tagRepository->findByOwner($user);

        return $this->render('task/list.html.twig', [
            'tasks' => $tasks,
            'projects' => $projects,
            'currentFilters' => $filters,
            'groupByProject' => $groupByProject,
            'groupedTasks' => $groupedTasks,
            'showCompleted' => $showCompleted,
            'taskSpacing' => $user->getTaskSpacing(),
            'apiToken' => $user->getApiToken(),
            'sidebar_projects' => $sidebarProjects,
            'sidebar_tags' => $tags,
            'selected_project_id' => $projectId,
        ]);
    }

    #[Route('/tasks', name: 'app_task_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Validate CSRF token
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('create_task', $csrfToken)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('app_task_list');
        }

        $title = trim((string) $request->request->get('title', ''));

        if (empty($title)) {
            $this->addFlash('error', 'Task title is required.');

            return $this->redirectToRoute('app_task_list');
        }

        try {
            $dto = new CreateTaskRequest(
                title: $title,
            );

            $this->taskService->create($user, $dto);

            $this->addFlash('success', 'Task created successfully.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to create task: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_task_list');
    }

    #[Route('/tasks/{id}/status', name: 'app_task_status', methods: ['POST'])]
    public function changeStatus(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Validate CSRF token
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('task_status_'.$id, $csrfToken)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('app_task_list');
        }

        $status = (string) $request->request->get('status', '');

        if (empty($status)) {
            $this->addFlash('error', 'Status is required.');

            return $this->redirectToRoute('app_task_list');
        }

        try {
            $task = $this->taskService->findByIdOrFail($id, $user);
            $this->taskService->changeStatus($task, $status);

            $this->addFlash('success', 'Task status updated.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to update task status: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_task_list');
    }

    #[Route('/tasks/{id}/delete', name: 'app_task_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Validate CSRF token
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('delete_task_'.$id, $csrfToken)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('app_task_list');
        }

        try {
            $task = $this->taskService->findByIdOrFail($id, $user);
            $taskTitle = $task->getTitle();
            $this->taskService->delete($task);

            $this->addFlash('success', sprintf('Task "%s" has been deleted.', $taskTitle));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to delete task: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_task_list');
    }

    #[Route('/projects/archived', name: 'app_projects_archived', methods: ['GET'])]
    public function archived(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get archived projects
        $archivedProjects = $this->projectService->getArchivedProjects($user);

        // Transform to array format for template
        $projects = array_map(function ($project) {
            return [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'color' => $project->getColor() ?? '#6366f1',
                'archivedAt' => $project->getArchivedAt()?->format('c'),
                'taskCount' => count($project->getTasks()),
                'path' => $this->buildProjectPath($project),
            ];
        }, $archivedProjects);

        return $this->render('project/archived.html.twig', [
            'projects' => $projects,
        ]);
    }

    #[Route('/projects/{id}/unarchive', name: 'app_project_unarchive', methods: ['POST'])]
    public function unarchiveProject(string $id, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Validate CSRF token
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('unarchive_project_'.$id, $csrfToken)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('app_projects_archived');
        }

        try {
            $project = $this->projectService->findByIdOrFail($id, $user);
            $projectName = $project->getName();
            $this->projectService->unarchiveWithOptions($project, false);

            $this->addFlash('success', sprintf('Project "%s" has been restored.', $projectName));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to restore project: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_projects_archived');
    }

    #[Route('/projects', name: 'app_project_create', methods: ['POST'])]
    public function createProject(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Validate CSRF token
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        if (!$this->isCsrfTokenValid('create_project', $csrfToken)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('app_task_list');
        }

        $name = trim((string) $request->request->get('name', ''));

        if (empty($name)) {
            $this->addFlash('error', 'Project name is required.');

            return $this->redirectToRoute('app_task_list');
        }

        try {
            $dto = CreateProjectRequest::fromArray([
                'name' => $name,
                'description' => $request->request->get('description') ?: null,
                'parentId' => $request->request->get('parentId') ?: null,
                'color' => $request->request->get('color') ?: null,
            ]);

            $this->projectService->create($user, $dto);

            $this->addFlash('success', sprintf('Project "%s" created successfully.', $name));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to create project: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_task_list');
    }

    /**
     * Build the path breadcrumb for a project.
     *
     * @return array<array{id: string, name: string}>
     */
    private function buildProjectPath(Project $project): array
    {
        $path = [];
        $current = $project;

        while ($current !== null) {
            array_unshift($path, [
                'id' => $current->getId(),
                'name' => $current->getName(),
            ]);
            $current = $current->getParent();
        }

        return $path;
    }
}
