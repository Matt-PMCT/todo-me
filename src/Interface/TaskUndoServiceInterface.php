<?php

declare(strict_types=1);

namespace App\Interface;

use App\Entity\Task;
use App\Entity\User;
use App\ValueObject\UndoToken;

/**
 * Interface for task undo operations.
 */
interface TaskUndoServiceInterface
{
    /**
     * Creates an undo token for a task update operation.
     *
     * @param Task $task The task being updated
     * @param array<string, mixed> $previousState The state before the update
     * @return string|null The undo token string, or null if creation failed
     */
    public function createUpdateUndoToken(Task $task, array $previousState): ?string;

    /**
     * Creates an undo token for a task delete operation.
     *
     * @param Task $task The task being deleted
     * @return UndoToken|null The undo token, or null if creation failed
     */
    public function createDeleteUndoToken(Task $task): ?UndoToken;

    /**
     * Creates an undo token for a task status change operation.
     *
     * @param Task $task The task whose status is being changed
     * @param array<string, mixed> $previousState The state before the change
     * @return string|null The undo token string, or null if creation failed
     */
    public function createStatusChangeUndoToken(Task $task, array $previousState): ?string;

    /**
     * Undoes a task delete operation.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Task The restored task
     */
    public function undoDelete(User $user, string $token): Task;

    /**
     * Undoes a task update operation.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Task The restored task
     */
    public function undoUpdate(User $user, string $token): Task;

    /**
     * Generic undo operation that determines the action type from the token.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Task The restored task
     */
    public function undo(User $user, string $token): Task;
}
