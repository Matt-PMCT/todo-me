<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Interface\ActivityLogServiceInterface;
use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for logging user activity on tasks and projects.
 */
final class ActivityLogService implements ActivityLogServiceInterface
{
    public function __construct(
        private readonly ActivityLogRepository $activityLogRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Log an activity.
     *
     * @param User        $owner       The user who performed the action
     * @param string      $action      The action performed
     * @param string      $entityType  The type of entity (task, project)
     * @param string|null $entityId    The entity ID (nullable for deleted entities)
     * @param string      $entityTitle The entity title for display
     * @param array       $changes     The changes made (for updates)
     *
     * @return ActivityLog
     */
    /**
     * Maximum length for entity title in the database.
     */
    private const MAX_ENTITY_TITLE_LENGTH = 255;

    public function log(
        User $owner,
        string $action,
        string $entityType,
        ?string $entityId,
        string $entityTitle,
        array $changes = []
    ): ActivityLog {
        $log = new ActivityLog();
        $log->setOwner($owner);
        $log->setAction($action);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setEntityTitle($this->truncateTitle($entityTitle));
        $log->setChanges($changes);

        $this->entityManager->persist($log);

        return $log;
    }

    /**
     * Truncate a title to fit in the database column.
     */
    private function truncateTitle(string $title): string
    {
        if (mb_strlen($title) <= self::MAX_ENTITY_TITLE_LENGTH) {
            return $title;
        }

        return mb_substr($title, 0, self::MAX_ENTITY_TITLE_LENGTH - 3).'...';
    }

    /**
     * Get activity logs for a user with pagination.
     *
     * @param User $user  The user
     * @param int  $page  Page number (1-indexed)
     * @param int  $limit Items per page
     *
     * @return array{items: array, pagination: array}
     */
    public function getActivity(User $user, int $page = 1, int $limit = 20): array
    {
        $items = $this->activityLogRepository->findByOwnerPaginated($user, $page, $limit);
        $total = $this->activityLogRepository->countByOwner($user);
        $pages = $limit > 0 ? (int) ceil($total / $limit) : 0;

        return [
            'items' => array_map(fn (ActivityLog $log) => $this->formatActivityLog($log), $items),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => $pages,
            ],
        ];
    }

    /**
     * Log task creation.
     */
    public function logTaskCreated(Task $task): ActivityLog
    {
        return $this->log(
            $task->getOwner(),
            ActivityLog::ACTION_TASK_CREATED,
            ActivityLog::ENTITY_TYPE_TASK,
            $task->getId(),
            $task->getTitle()
        );
    }

    /**
     * Log task update with changes.
     *
     * @param Task  $task    The updated task
     * @param array $changes The changes made
     */
    public function logTaskUpdated(Task $task, array $changes): ActivityLog
    {
        return $this->log(
            $task->getOwner(),
            ActivityLog::ACTION_TASK_UPDATED,
            ActivityLog::ENTITY_TYPE_TASK,
            $task->getId(),
            $task->getTitle(),
            $changes
        );
    }

    /**
     * Log task completion.
     */
    public function logTaskCompleted(Task $task): ActivityLog
    {
        return $this->log(
            $task->getOwner(),
            ActivityLog::ACTION_TASK_COMPLETED,
            ActivityLog::ENTITY_TYPE_TASK,
            $task->getId(),
            $task->getTitle()
        );
    }

    /**
     * Log task deletion.
     *
     * @param User   $owner     The task owner
     * @param string $taskId    The task ID
     * @param string $taskTitle The task title
     */
    public function logTaskDeleted(User $owner, string $taskId, string $taskTitle): ActivityLog
    {
        return $this->log(
            $owner,
            ActivityLog::ACTION_TASK_DELETED,
            ActivityLog::ENTITY_TYPE_TASK,
            $taskId,
            $taskTitle
        );
    }

    /**
     * Log project creation.
     */
    public function logProjectCreated(Project $project): ActivityLog
    {
        return $this->log(
            $project->getOwner(),
            ActivityLog::ACTION_PROJECT_CREATED,
            ActivityLog::ENTITY_TYPE_PROJECT,
            $project->getId(),
            $project->getName()
        );
    }

    /**
     * Log project update with changes.
     *
     * @param Project $project The updated project
     * @param array   $changes The changes made
     */
    public function logProjectUpdated(Project $project, array $changes): ActivityLog
    {
        return $this->log(
            $project->getOwner(),
            ActivityLog::ACTION_PROJECT_UPDATED,
            ActivityLog::ENTITY_TYPE_PROJECT,
            $project->getId(),
            $project->getName(),
            $changes
        );
    }

    /**
     * Log project deletion.
     *
     * @param User   $owner       The project owner
     * @param string $projectId   The project ID
     * @param string $projectName The project name
     */
    public function logProjectDeleted(User $owner, string $projectId, string $projectName): ActivityLog
    {
        return $this->log(
            $owner,
            ActivityLog::ACTION_PROJECT_DELETED,
            ActivityLog::ENTITY_TYPE_PROJECT,
            $projectId,
            $projectName
        );
    }

    /**
     * Format an activity log for API response.
     */
    private function formatActivityLog(ActivityLog $log): array
    {
        return [
            'id' => $log->getId(),
            'action' => $log->getAction(),
            'entityType' => $log->getEntityType(),
            'entityId' => $log->getEntityId(),
            'entityTitle' => $log->getEntityTitle(),
            'changes' => $log->getChanges(),
            'createdAt' => $log->getCreatedAt()->format(\DateTimeInterface::RFC3339),
        ];
    }
}
