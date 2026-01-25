<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\ActivityLog;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;

/**
 * Interface for activity logging service.
 */
interface ActivityLogServiceInterface
{
    /**
     * Log an activity.
     *
     * @param User $owner The user who performed the action
     * @param string $action The action performed
     * @param string $entityType The type of entity (task, project)
     * @param string|null $entityId The entity ID (nullable for deleted entities)
     * @param string $entityTitle The entity title for display
     * @param array $changes The changes made (for updates)
     * @return ActivityLog
     */
    public function log(
        User $owner,
        string $action,
        string $entityType,
        ?string $entityId,
        string $entityTitle,
        array $changes = []
    ): ActivityLog;

    /**
     * Get activity logs for a user with pagination.
     *
     * @param User $user The user
     * @param int $page Page number (1-indexed)
     * @param int $limit Items per page
     * @return array{items: array, pagination: array}
     */
    public function getActivity(User $user, int $page = 1, int $limit = 20): array;

    /**
     * Log task creation.
     */
    public function logTaskCreated(Task $task): ActivityLog;

    /**
     * Log task update with changes.
     *
     * @param Task $task The updated task
     * @param array $changes The changes made
     */
    public function logTaskUpdated(Task $task, array $changes): ActivityLog;

    /**
     * Log task completion.
     */
    public function logTaskCompleted(Task $task): ActivityLog;

    /**
     * Log task deletion.
     *
     * @param User $owner The task owner
     * @param string $taskId The task ID
     * @param string $taskTitle The task title
     */
    public function logTaskDeleted(User $owner, string $taskId, string $taskTitle): ActivityLog;

    /**
     * Log project creation.
     */
    public function logProjectCreated(Project $project): ActivityLog;

    /**
     * Log project update with changes.
     *
     * @param Project $project The updated project
     * @param array $changes The changes made
     */
    public function logProjectUpdated(Project $project, array $changes): ActivityLog;

    /**
     * Log project deletion.
     *
     * @param User $owner The project owner
     * @param string $projectId The project ID
     * @param string $projectName The project name
     */
    public function logProjectDeleted(User $owner, string $projectId, string $projectName): ActivityLog;
}
