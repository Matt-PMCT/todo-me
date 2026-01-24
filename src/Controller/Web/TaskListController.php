<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\DTO\CreateTaskRequest;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
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
        private readonly TaskService $taskService,
    ) {
    }

    #[Route('/tasks', name: 'app_task_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get filter parameters
        $filters = [];
        if ($status = $request->query->get('status')) {
            $filters['status'] = $status;
        }
        if ($priority = $request->query->get('priority')) {
            $filters['priority'] = (int) $priority;
        }
        if ($projectId = $request->query->get('projectId')) {
            $filters['projectId'] = $projectId;
        }

        // Get tasks with filters
        $queryBuilder = $this->taskRepository->createFilteredQueryBuilder($user, $filters);
        $tasks = $queryBuilder->getQuery()->getResult();

        // Get user's projects for filter dropdown
        $projects = $this->projectRepository->findByOwner($user);

        // Check if grouping by project is requested
        $groupByProject = $request->query->getBoolean('groupByProject', false);

        return $this->render('task/list.html.twig', [
            'tasks' => $tasks,
            'projects' => $projects,
            'currentFilters' => $filters,
            'groupByProject' => $groupByProject,
            'apiToken' => $user->getApiToken(),
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
            $this->addFlash('error', 'Failed to create task: ' . $e->getMessage());
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
        if (!$this->isCsrfTokenValid('task_status_' . $id, $csrfToken)) {
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
            $this->addFlash('error', 'Failed to update task status: ' . $e->getMessage());
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
        if (!$this->isCsrfTokenValid('delete_task_' . $id, $csrfToken)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('app_task_list');
        }

        try {
            $task = $this->taskService->findByIdOrFail($id, $user);
            $taskTitle = $task->getTitle();
            $this->taskService->delete($task);

            $this->addFlash('success', sprintf('Task "%s" has been deleted.', $taskTitle));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to delete task: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_task_list');
    }
}
