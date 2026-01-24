<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Enum\UndoAction;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidStateException;
use App\Exception\InvalidUndoTokenException;
use App\Interface\OwnershipCheckerInterface;
use App\Interface\TaskStateServiceInterface;
use App\Interface\TaskUndoServiceInterface;
use App\Repository\TaskRepository;
use App\ValueObject\UndoToken;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for task undo operations.
 *
 * Handles undo token creation and consumption for task operations,
 * including delete, update, and status change operations.
 */
final class TaskUndoService implements TaskUndoServiceInterface
{
    private const ENTITY_TYPE = 'task';

    public function __construct(
        private readonly UndoService $undoService,
        private readonly TaskRepository $taskRepository,
        private readonly TaskStateServiceInterface $taskStateService,
        private readonly EntityManagerInterface $entityManager,
        private readonly OwnershipCheckerInterface $ownershipChecker,
    ) {
    }

    /**
     * Creates an undo token for a task update operation.
     *
     * @param Task $task The task being updated
     * @param array<string, mixed> $previousState The state before the update
     * @return string|null The undo token string, or null if creation failed
     * @throws InvalidStateException If task has no owner or ID
     */
    public function createUpdateUndoToken(Task $task, array $previousState): ?string
    {
        $ownerId = $task->getOwner()?->getId();
        $taskId = $task->getId();

        if ($ownerId === null) {
            throw InvalidStateException::missingOwner('Task');
        }
        if ($taskId === null) {
            throw InvalidStateException::missingRequiredId('Task');
        }

        $undoToken = $this->undoService->createUndoToken(
            userId: $ownerId,
            action: UndoAction::UPDATE->value,
            entityType: self::ENTITY_TYPE,
            entityId: $taskId,
            previousState: $previousState,
        );

        return $undoToken?->token;
    }

    /**
     * Creates an undo token for a task delete operation.
     *
     * @param Task $task The task being deleted
     * @return UndoToken|null The undo token, or null if creation failed
     * @throws InvalidStateException If task has no owner or ID
     */
    public function createDeleteUndoToken(Task $task): ?UndoToken
    {
        $ownerId = $task->getOwner()?->getId();
        $taskId = $task->getId();

        if ($ownerId === null) {
            throw InvalidStateException::missingOwner('Task');
        }
        if ($taskId === null) {
            throw InvalidStateException::missingRequiredId('Task');
        }

        // Store full state for undo
        $previousState = $this->taskStateService->serializeTaskState($task);

        return $this->undoService->createUndoToken(
            userId: $ownerId,
            action: UndoAction::DELETE->value,
            entityType: self::ENTITY_TYPE,
            entityId: $taskId,
            previousState: $previousState,
        );
    }

    /**
     * Creates an undo token for a task status change operation.
     *
     * @param Task $task The task with status being changed
     * @param array<string, mixed> $previousState The status state before the change
     * @return string|null The undo token string, or null if creation failed
     * @throws InvalidStateException If task has no owner or ID
     */
    public function createStatusChangeUndoToken(Task $task, array $previousState): ?string
    {
        $ownerId = $task->getOwner()?->getId();
        $taskId = $task->getId();

        if ($ownerId === null) {
            throw InvalidStateException::missingOwner('Task');
        }
        if ($taskId === null) {
            throw InvalidStateException::missingRequiredId('Task');
        }

        $undoToken = $this->undoService->createUndoToken(
            userId: $ownerId,
            action: UndoAction::STATUS_CHANGE->value,
            entityType: self::ENTITY_TYPE,
            entityId: $taskId,
            previousState: $previousState,
        );

        return $undoToken?->token;
    }

    /**
     * Undoes a task operation (generic handler for all undo types).
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Task The restored/updated task
     * @throws InvalidUndoTokenException If the token is invalid or expired
     * @throws EntityNotFoundException If the task no longer exists (for update operations)
     */
    public function undo(User $user, string $token): Task
    {
        $undoToken = $this->undoService->consumeUndoToken($user->getId(), $token);

        if ($undoToken === null) {
            throw InvalidUndoTokenException::expired();
        }

        if ($undoToken->entityType !== self::ENTITY_TYPE) {
            throw InvalidUndoTokenException::wrongEntityType(self::ENTITY_TYPE, $undoToken->entityType);
        }

        return match ($undoToken->action) {
            UndoAction::DELETE->value => $this->performUndoDelete($user, $undoToken),
            UndoAction::UPDATE->value, UndoAction::STATUS_CHANGE->value => $this->performUndoUpdate($undoToken),
            default => throw InvalidUndoTokenException::unknownAction($undoToken->action),
        };
    }

    /**
     * Undoes a delete operation.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Task The restored task
     * @throws InvalidUndoTokenException If the token is invalid or expired
     */
    public function undoDelete(User $user, string $token): Task
    {
        $undoToken = $this->undoService->consumeUndoToken($user->getId(), $token);

        if ($undoToken === null) {
            throw InvalidUndoTokenException::expired();
        }

        if ($undoToken->entityType !== self::ENTITY_TYPE) {
            throw InvalidUndoTokenException::wrongEntityType(self::ENTITY_TYPE, $undoToken->entityType);
        }

        if ($undoToken->action !== UndoAction::DELETE->value) {
            throw InvalidUndoTokenException::wrongActionType('delete');
        }

        return $this->performUndoDelete($user, $undoToken);
    }

    /**
     * Undoes an update operation.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Task The restored task
     * @throws InvalidUndoTokenException If the token is invalid or expired
     * @throws EntityNotFoundException If the task no longer exists
     */
    public function undoUpdate(User $user, string $token): Task
    {
        $undoToken = $this->undoService->consumeUndoToken($user->getId(), $token);

        if ($undoToken === null) {
            throw InvalidUndoTokenException::expired();
        }

        if ($undoToken->entityType !== self::ENTITY_TYPE) {
            throw InvalidUndoTokenException::wrongEntityType(self::ENTITY_TYPE, $undoToken->entityType);
        }

        if (!in_array($undoToken->action, [UndoAction::UPDATE->value, UndoAction::STATUS_CHANGE->value], true)) {
            throw InvalidUndoTokenException::wrongActionType('update');
        }

        return $this->performUndoUpdate($undoToken);
    }

    /**
     * Performs the actual delete undo operation.
     */
    private function performUndoDelete(User $user, UndoToken $undoToken): Task
    {
        // Restore the task from previous state
        $task = $this->taskStateService->restoreTaskFromState($user, $undoToken->previousState);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * Performs the actual update undo operation.
     */
    private function performUndoUpdate(UndoToken $undoToken): Task
    {
        // Find the task
        $task = $this->taskRepository->find($undoToken->entityId);

        if ($task === null) {
            throw EntityNotFoundException::task($undoToken->entityId);
        }

        // Verify ownership
        $this->ownershipChecker->checkOwnership($task);

        // Restore from previous state
        $this->taskStateService->applyStateToTask($task, $undoToken->previousState);

        $this->entityManager->flush();

        return $task;
    }
}
