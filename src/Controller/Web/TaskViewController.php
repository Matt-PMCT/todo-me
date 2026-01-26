<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;
use App\Service\OverdueService;
use App\Service\ProjectService;
use App\Service\TaskGroupingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class TaskViewController extends AbstractController
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly TagRepository $tagRepository,
        private readonly ProjectService $projectService,
        private readonly TaskGroupingService $taskGroupingService,
        private readonly OverdueService $overdueService,
    ) {
    }

    #[Route('/today', name: 'app_tasks_today', methods: ['GET'])]
    public function today(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get today tasks (due today + overdue)
        $tasks = $this->taskRepository->findTodayTasks($user);

        // Group tasks: overdue first, then today
        $grouped = $this->taskGroupingService->groupByTimePeriod($tasks, $user);

        // Get sidebar data
        $sidebarProjects = $this->projectService->getTree($user);
        $tags = $this->tagRepository->findByOwner($user);

        return $this->render('task/today.html.twig', [
            'tasks' => $tasks,
            'groupedTasks' => $grouped,
            'overdueService' => $this->overdueService,
            'taskSpacing' => $user->getTaskSpacing(),
            'sidebar_projects' => $sidebarProjects,
            'sidebar_tags' => $tags,
        ]);
    }

    #[Route('/upcoming', name: 'app_tasks_upcoming', methods: ['GET'])]
    public function upcoming(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get tasks due in next 14 days
        $tasks = $this->taskRepository->findUpcomingTasks($user, 14);

        // Group by time period
        $grouped = $this->taskGroupingService->groupByTimePeriod($tasks, $user);

        // Get sidebar data
        $sidebarProjects = $this->projectService->getTree($user);
        $tags = $this->tagRepository->findByOwner($user);

        return $this->render('task/upcoming.html.twig', [
            'tasks' => $tasks,
            'groupedTasks' => $grouped,
            'taskSpacing' => $user->getTaskSpacing(),
            'sidebar_projects' => $sidebarProjects,
            'sidebar_tags' => $tags,
        ]);
    }

    #[Route('/overdue', name: 'app_tasks_overdue', methods: ['GET'])]
    public function overdue(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get overdue tasks
        $tasks = $this->taskRepository->findOverdueByOwner($user);

        // Group by severity
        $grouped = $this->taskGroupingService->groupBySeverity($tasks);

        // Get sidebar data
        $sidebarProjects = $this->projectService->getTree($user);
        $tags = $this->tagRepository->findByOwner($user);

        return $this->render('task/overdue.html.twig', [
            'tasks' => $tasks,
            'groupedTasks' => $grouped,
            'overdueService' => $this->overdueService,
            'taskSpacing' => $user->getTaskSpacing(),
            'sidebar_projects' => $sidebarProjects,
            'sidebar_tags' => $tags,
        ]);
    }

    #[Route('/no-date', name: 'app_tasks_no_date', methods: ['GET'])]
    public function noDate(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get tasks without due date
        $tasks = $this->taskRepository->findTasksWithNoDueDate($user);

        // Get sidebar data
        $sidebarProjects = $this->projectService->getTree($user);
        $tags = $this->tagRepository->findByOwner($user);

        return $this->render('task/no-date.html.twig', [
            'tasks' => $tasks,
            'taskSpacing' => $user->getTaskSpacing(),
            'sidebar_projects' => $sidebarProjects,
            'sidebar_tags' => $tags,
        ]);
    }

    #[Route('/completed', name: 'app_tasks_completed', methods: ['GET'])]
    public function completed(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get recent completed tasks
        $tasks = $this->taskRepository->findCompletedTasksRecent($user, 100);

        // Get sidebar data
        $sidebarProjects = $this->projectService->getTree($user);
        $tags = $this->tagRepository->findByOwner($user);

        return $this->render('task/completed.html.twig', [
            'tasks' => $tasks,
            'taskSpacing' => $user->getTaskSpacing(),
            'sidebar_projects' => $sidebarProjects,
            'sidebar_tags' => $tags,
        ]);
    }
}
