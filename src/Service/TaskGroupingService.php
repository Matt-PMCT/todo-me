<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;

/**
 * Service for grouping tasks for display in views.
 */
final class TaskGroupingService
{
    // Group constants for time periods
    public const GROUP_OVERDUE = 'overdue';
    public const GROUP_TODAY = 'today';
    public const GROUP_TOMORROW = 'tomorrow';
    public const GROUP_THIS_WEEK = 'this_week';
    public const GROUP_NEXT_WEEK = 'next_week';
    public const GROUP_LATER = 'later';
    public const GROUP_NO_DATE = 'no_date';

    private const GROUP_LABELS = [
        self::GROUP_OVERDUE => 'Overdue',
        self::GROUP_TODAY => 'Today',
        self::GROUP_TOMORROW => 'Tomorrow',
        self::GROUP_THIS_WEEK => 'This Week',
        self::GROUP_NEXT_WEEK => 'Next Week',
        self::GROUP_LATER => 'Later',
        self::GROUP_NO_DATE => 'No Date',
    ];

    private const SEVERITY_CONFIG = [
        Task::OVERDUE_SEVERITY_LOW => [
            'label' => '1-2 days overdue',
            'colorClass' => 'text-yellow-600',
        ],
        Task::OVERDUE_SEVERITY_MEDIUM => [
            'label' => '3-7 days overdue',
            'colorClass' => 'text-orange-600',
        ],
        Task::OVERDUE_SEVERITY_HIGH => [
            'label' => 'More than 7 days overdue',
            'colorClass' => 'text-red-600',
        ],
    ];

    /**
     * Groups tasks by time period relative to today.
     *
     * @param Task[] $tasks
     * @return array<string, array{label: string, tasks: Task[]}>
     */
    public function groupByTimePeriod(array $tasks, User $user): array
    {
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        [$thisWeekStart, $thisWeekEnd] = $this->getWeekBoundaries($today, $user->getStartOfWeek());
        [$nextWeekStart, $nextWeekEnd] = $this->getWeekBoundaries($thisWeekEnd->modify('+1 day'), $user->getStartOfWeek());

        // Initialize empty groups
        $groups = [];
        foreach (self::GROUP_LABELS as $key => $label) {
            $groups[$key] = [];
        }

        // Sort tasks into groups
        foreach ($tasks as $task) {
            $dueDate = $task->getDueDate();

            if ($dueDate === null) {
                $groups[self::GROUP_NO_DATE][] = $task;
                continue;
            }

            // Normalize to date only for comparison
            $dueDateOnly = \DateTimeImmutable::createFromFormat('Y-m-d', $dueDate->format('Y-m-d'));

            if ($dueDateOnly < $today) {
                $groups[self::GROUP_OVERDUE][] = $task;
            } elseif ($dueDateOnly->format('Y-m-d') === $today->format('Y-m-d')) {
                $groups[self::GROUP_TODAY][] = $task;
            } elseif ($dueDateOnly->format('Y-m-d') === $tomorrow->format('Y-m-d')) {
                $groups[self::GROUP_TOMORROW][] = $task;
            } elseif ($dueDateOnly >= $thisWeekStart && $dueDateOnly <= $thisWeekEnd) {
                $groups[self::GROUP_THIS_WEEK][] = $task;
            } elseif ($dueDateOnly >= $nextWeekStart && $dueDateOnly <= $nextWeekEnd) {
                $groups[self::GROUP_NEXT_WEEK][] = $task;
            } else {
                $groups[self::GROUP_LATER][] = $task;
            }
        }

        // Return only non-empty groups with labels
        $result = [];
        foreach ($groups as $key => $groupTasks) {
            if (!empty($groupTasks)) {
                $result[$key] = [
                    'label' => self::GROUP_LABELS[$key],
                    'tasks' => $groupTasks,
                ];
            }
        }

        return $result;
    }

    /**
     * Groups tasks by project.
     *
     * @param Task[] $tasks
     * @return array<string, array{projectId: string|null, projectName: string, tasks: Task[]}>
     */
    public function groupByProject(array $tasks): array
    {
        $groups = [];

        foreach ($tasks as $task) {
            $project = $task->getProject();
            $key = $project !== null ? $project->getId() : 'no_project';
            $projectName = $project !== null ? $project->getName() : 'No Project';

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'projectId' => $project?->getId(),
                    'projectName' => $projectName,
                    'tasks' => [],
                ];
            }

            $groups[$key]['tasks'][] = $task;
        }

        return $groups;
    }

    /**
     * Groups overdue tasks by severity (low, medium, high).
     *
     * @param Task[] $tasks
     * @return array<string, array{label: string, colorClass: string, tasks: Task[]}>
     */
    public function groupBySeverity(array $tasks): array
    {
        $groups = [];

        foreach ($tasks as $task) {
            $severity = $task->getOverdueSeverity();

            if ($severity === null) {
                continue;
            }

            if (!isset($groups[$severity])) {
                $config = self::SEVERITY_CONFIG[$severity];
                $groups[$severity] = [
                    'label' => $config['label'],
                    'colorClass' => $config['colorClass'],
                    'tasks' => [],
                ];
            }

            $groups[$severity]['tasks'][] = $task;
        }

        return $groups;
    }

    /**
     * Calculates the start and end of the week containing the given date.
     *
     * @param \DateTimeImmutable $date The date to get week boundaries for
     * @param int $startOfWeek The day the week starts on (0=Sunday, 1=Monday)
     * @return array{\DateTimeImmutable, \DateTimeImmutable} Start and end of the week
     */
    private function getWeekBoundaries(\DateTimeImmutable $date, int $startOfWeek): array
    {
        // PHP's 'w' format: 0=Sunday, 1=Monday, etc.
        $currentDayOfWeek = (int) $date->format('w');

        // Calculate days to subtract to get to start of week
        $daysToSubtract = ($currentDayOfWeek - $startOfWeek + 7) % 7;

        $weekStart = $date->modify("-{$daysToSubtract} days");
        $weekEnd = $weekStart->modify('+6 days');

        return [$weekStart, $weekEnd];
    }
}
