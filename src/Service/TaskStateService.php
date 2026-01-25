<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Interface\TaskStateServiceInterface;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;

/**
 * Service for task state serialization and deserialization.
 *
 * Handles converting task entities to/from serializable array representations
 * for undo operations and state restoration.
 *
 * @internal Used by TaskService and TaskUndoService for undo operations
 */
final class TaskStateService implements TaskStateServiceInterface
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly TagRepository $tagRepository,
    ) {
    }

    /**
     * Serializes a task state for undo operations.
     *
     * @param Task $task The task to serialize
     * @return array<string, mixed> The serialized state
     */
    public function serializeTaskState(Task $task): array
    {
        $tagIds = [];
        foreach ($task->getTags() as $tag) {
            $tagIds[] = $tag->getId();
        }

        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'priority' => $task->getPriority(),
            'dueDate' => $task->getDueDate()?->format('Y-m-d'),
            'dueTime' => $task->getDueTime()?->format('H:i:s'),
            'position' => $task->getPosition(),
            'projectId' => $task->getProject()?->getId(),
            'tagIds' => $tagIds,
            'completedAt' => $task->getCompletedAt()?->format(\DateTimeInterface::RFC3339),
            'createdAt' => $task->getCreatedAt()->format(\DateTimeInterface::RFC3339),
            'isRecurring' => $task->isRecurring(),
            'recurrenceRule' => $task->getRecurrenceRule(),
            'recurrenceType' => $task->getRecurrenceType(),
            'recurrenceEndDate' => $task->getRecurrenceEndDate()?->format('Y-m-d'),
            'originalTaskId' => $task->getOriginalTask()?->getId(),
        ];
    }

    /**
     * Serializes only the status-related state for status change undo.
     *
     * @param Task $task The task
     * @return array<string, mixed> The status-related state
     */
    public function serializeStatusState(Task $task): array
    {
        return [
            'status' => $task->getStatus(),
            'completedAt' => $task->getCompletedAt()?->format(\DateTimeInterface::RFC3339),
        ];
    }

    /**
     * Restores a task from a serialized state.
     *
     * Creates a new Task entity with all properties set from the state.
     * Used when undoing delete operations.
     *
     * @param User $user The task owner
     * @param array<string, mixed> $state The serialized state
     * @return Task The restored task
     */
    public function restoreTaskFromState(User $user, array $state): Task
    {
        $task = new Task();
        $task->setOwner($user);

        $this->applyStateToTask($task, $state);

        // Restore timestamps
        if (isset($state['createdAt'])) {
            $task->setCreatedAt(new \DateTimeImmutable($state['createdAt']));
        }

        if (isset($state['completedAt']) && $state['completedAt'] !== null) {
            $task->setCompletedAt(new \DateTimeImmutable($state['completedAt']));
        }

        return $task;
    }

    /**
     * Applies a serialized state to an existing task.
     *
     * Uses Task::restoreFromState() for basic properties to avoid triggering
     * side effects like auto-setting completedAt. Handles related entities
     * (project, tags) separately with ownership validation.
     *
     * @param Task $task The task to update
     * @param array<string, mixed> $state The state to apply
     */
    public function applyStateToTask(Task $task, array $state): void
    {
        // Use the entity's restoreFromState method for basic properties
        // This handles status without triggering completedAt auto-set
        $task->restoreFromState($state);

        // Handle project with ownership validation
        if (array_key_exists('projectId', $state)) {
            if ($state['projectId'] !== null) {
                // Validate ownership - only restore if project belongs to same user
                $project = $this->projectRepository->findOneByOwnerAndId(
                    $task->getOwner(),
                    $state['projectId']
                );
                // Only set project if it exists AND belongs to the user
                // Silently skip if project was deleted or ownership changed
                $task->setProject($project);
            } else {
                $task->setProject(null);
            }
        }

        // Handle tags with ownership validation
        if (isset($state['tagIds'])) {
            // Clear existing tags
            foreach ($task->getTags()->toArray() as $tag) {
                $task->removeTag($tag);
            }

            // Add tags from state, validating ownership
            foreach ($state['tagIds'] as $tagId) {
                $tag = $this->tagRepository->findOneByOwnerAndId(
                    $task->getOwner(),
                    $tagId
                );
                // Only add tag if it exists AND belongs to the user
                if ($tag !== null) {
                    $task->addTag($tag);
                }
            }
        }
    }
}
