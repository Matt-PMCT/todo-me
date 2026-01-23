<?php

declare(strict_types=1);

namespace App\Tests\Fixtures;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixture for creating test tasks.
 *
 * Creates tasks with various statuses, priorities, and configurations
 * for comprehensive testing.
 *
 * Depends on UserFixture to get the user references.
 */
class TaskFixture extends Fixture implements DependentFixtureInterface
{
    public const TASK_PENDING_REFERENCE = 'task-pending';
    public const TASK_IN_PROGRESS_REFERENCE = 'task-in-progress';
    public const TASK_COMPLETED_REFERENCE = 'task-completed';
    public const TASK_HIGH_PRIORITY_REFERENCE = 'task-high-priority';
    public const TASK_LOW_PRIORITY_REFERENCE = 'task-low-priority';
    public const TASK_WITH_DUE_DATE_REFERENCE = 'task-with-due-date';
    public const TASK_OVERDUE_REFERENCE = 'task-overdue';
    public const PROJECT_REFERENCE = 'project-standard';
    public const TASK_IN_PROJECT_REFERENCE = 'task-in-project';

    public function load(ObjectManager $manager): void
    {
        /** @var User $user */
        $user = $this->getReference(UserFixture::USER_STANDARD_REFERENCE, User::class);

        // Create a project for task association tests
        $project = new Project();
        $project->setOwner($user);
        $project->setName('Test Project');
        $project->setDescription('A project for testing');
        $manager->persist($project);
        $this->addReference(self::PROJECT_REFERENCE, $project);

        // Task with pending status
        $pendingTask = $this->createTask(
            $user,
            'Pending Task',
            'A task that is pending',
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            null
        );
        $manager->persist($pendingTask);
        $this->addReference(self::TASK_PENDING_REFERENCE, $pendingTask);

        // Task with in_progress status
        $inProgressTask = $this->createTask(
            $user,
            'In Progress Task',
            'A task that is in progress',
            Task::STATUS_IN_PROGRESS,
            Task::PRIORITY_DEFAULT,
            null,
            null
        );
        $manager->persist($inProgressTask);
        $this->addReference(self::TASK_IN_PROGRESS_REFERENCE, $inProgressTask);

        // Task with completed status
        $completedTask = $this->createTask(
            $user,
            'Completed Task',
            'A task that is completed',
            Task::STATUS_COMPLETED,
            Task::PRIORITY_DEFAULT,
            null,
            null
        );
        $manager->persist($completedTask);
        $this->addReference(self::TASK_COMPLETED_REFERENCE, $completedTask);

        // Task with high priority
        $highPriorityTask = $this->createTask(
            $user,
            'High Priority Task',
            'A task with high priority',
            Task::STATUS_PENDING,
            Task::PRIORITY_MAX,
            null,
            null
        );
        $manager->persist($highPriorityTask);
        $this->addReference(self::TASK_HIGH_PRIORITY_REFERENCE, $highPriorityTask);

        // Task with low priority
        $lowPriorityTask = $this->createTask(
            $user,
            'Low Priority Task',
            'A task with low priority',
            Task::STATUS_PENDING,
            Task::PRIORITY_MIN,
            null,
            null
        );
        $manager->persist($lowPriorityTask);
        $this->addReference(self::TASK_LOW_PRIORITY_REFERENCE, $lowPriorityTask);

        // Task with future due date
        $futureDueDate = new \DateTimeImmutable('+7 days');
        $taskWithDueDate = $this->createTask(
            $user,
            'Task with Due Date',
            'A task with a future due date',
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            $futureDueDate
        );
        $manager->persist($taskWithDueDate);
        $this->addReference(self::TASK_WITH_DUE_DATE_REFERENCE, $taskWithDueDate);

        // Overdue task
        $pastDueDate = new \DateTimeImmutable('-3 days');
        $overdueTask = $this->createTask(
            $user,
            'Overdue Task',
            'A task that is overdue',
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            null,
            $pastDueDate
        );
        $manager->persist($overdueTask);
        $this->addReference(self::TASK_OVERDUE_REFERENCE, $overdueTask);

        // Task in a project
        $taskInProject = $this->createTask(
            $user,
            'Task in Project',
            'A task associated with a project',
            Task::STATUS_PENDING,
            Task::PRIORITY_DEFAULT,
            $project,
            null
        );
        $manager->persist($taskInProject);
        $this->addReference(self::TASK_IN_PROJECT_REFERENCE, $taskInProject);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixture::class,
        ];
    }

    private function createTask(
        User $owner,
        string $title,
        ?string $description,
        string $status,
        int $priority,
        ?Project $project,
        ?\DateTimeImmutable $dueDate
    ): Task {
        $task = new Task();
        $task->setOwner($owner);
        $task->setTitle($title);
        $task->setDescription($description);
        $task->setStatus($status);
        $task->setPriority($priority);
        $task->setProject($project);
        $task->setDueDate($dueDate);

        return $task;
    }
}
