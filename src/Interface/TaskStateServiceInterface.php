<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Task;
use App\Entity\User;

/**
 * Interface for task state operations used in undo functionality.
 */
interface TaskStateServiceInterface
{
    /**
     * Serializes a task state for undo operations.
     *
     * @param Task $task The task to serialize
     * @return array<string, mixed> The serialized state
     */
    public function serializeTaskState(Task $task): array;

    /**
     * Serializes only the status-related state for undo operations.
     *
     * @param Task $task The task to serialize
     * @return array<string, mixed> The serialized status state
     */
    public function serializeStatusState(Task $task): array;

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
    public function restoreTaskFromState(User $user, array $state): Task;

    /**
     * Applies a serialized state to an existing task.
     *
     * @param Task $task The task to update
     * @param array<string, mixed> $state The state to apply
     */
    public function applyStateToTask(Task $task, array $state): void;
}
