<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\BatchOperationRequest;
use App\DTO\BatchOperationResult;
use App\DTO\BatchOperationsRequest;
use App\DTO\BatchResult;
use App\DTO\CreateTaskRequest;
use App\DTO\UpdateTaskRequest;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\UndoAction;
use App\Exception\EntityNotFoundException;
use App\Exception\ForbiddenException;
use App\Exception\ValidationException;
use App\Interface\TaskStateServiceInterface;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for batch task operations.
 *
 * Supports both partial success mode (default) and atomic mode.
 * Operations are executed sequentially in array order.
 */
final class BatchOperationService
{
    private const BATCH_ENTITY_TYPE = 'batch';

    public function __construct(
        private readonly TaskService $taskService,
        private readonly TaskRepository $taskRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TaskStateServiceInterface $taskStateService,
        private readonly UndoService $undoService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Executes a batch of operations.
     *
     * @param User                   $user    The user executing the batch
     * @param BatchOperationsRequest $request The batch request
     *
     * @return BatchResult The results of all operations
     */
    public function execute(User $user, BatchOperationsRequest $request): BatchResult
    {
        if ($request->atomic) {
            return $this->executeAtomic($user, $request);
        }

        return $this->executePartial($user, $request);
    }

    /**
     * Executes operations with partial success (each operation independent).
     */
    private function executePartial(User $user, BatchOperationsRequest $request): BatchResult
    {
        $results = [];
        $previousStates = [];

        foreach ($request->operations as $index => $operation) {
            $result = $this->executeSingleOperation($user, $operation, $index, $previousStates);
            $results[] = $result;
        }

        // Create batch undo token if any operations succeeded
        $undoToken = null;
        if (!empty($previousStates)) {
            $undoToken = $this->createBatchUndoToken($user, $previousStates);
        }

        return BatchResult::fromResults($results, $undoToken);
    }

    /**
     * Executes operations atomically (rollback on any failure).
     */
    private function executeAtomic(User $user, BatchOperationsRequest $request): BatchResult
    {
        $this->entityManager->beginTransaction();

        try {
            $results = [];
            $previousStates = [];

            foreach ($request->operations as $index => $operation) {
                $result = $this->executeSingleOperation($user, $operation, $index, $previousStates);
                $results[] = $result;

                // In atomic mode, fail fast on first error
                if (!$result->success) {
                    $this->entityManager->rollback();

                    return BatchResult::fromResults($results);
                }
            }

            $this->entityManager->commit();

            // Create batch undo token
            $undoToken = null;
            if (!empty($previousStates)) {
                $undoToken = $this->createBatchUndoToken($user, $previousStates);
            }

            return BatchResult::fromResults($results, $undoToken);
        } catch (\Throwable $e) {
            $this->entityManager->rollback();

            throw $e;
        }
    }

    /**
     * Executes a single operation and captures state for undo.
     *
     * @param User                             $user           The user
     * @param BatchOperationRequest            $operation      The operation
     * @param int                              $index          The operation index
     * @param array<int, array<string, mixed>> $previousStates Captured states for undo (modified by reference)
     *
     * @return BatchOperationResult The operation result
     */
    private function executeSingleOperation(
        User $user,
        BatchOperationRequest $operation,
        int $index,
        array &$previousStates,
    ): BatchOperationResult {
        try {
            // Validate operation requirements
            $errors = $operation->validateRequirements();
            if (!empty($errors)) {
                return BatchOperationResult::failure(
                    $index,
                    $operation->action,
                    'Operation validation failed: '.implode(', ', $errors),
                    'VALIDATION_ERROR'
                );
            }

            $taskId = match ($operation->action) {
                BatchOperationRequest::ACTION_CREATE => $this->executeCreate($user, $operation, $index, $previousStates),
                BatchOperationRequest::ACTION_UPDATE => $this->executeUpdate($user, $operation, $index, $previousStates),
                BatchOperationRequest::ACTION_DELETE => $this->executeDelete($user, $operation, $index, $previousStates),
                BatchOperationRequest::ACTION_COMPLETE => $this->executeComplete($user, $operation, $index, $previousStates),
                BatchOperationRequest::ACTION_RESCHEDULE => $this->executeReschedule($user, $operation, $index, $previousStates),
                default => throw new \InvalidArgumentException('Unknown action: '.$operation->action),
            };

            return BatchOperationResult::success($index, $operation->action, $taskId);
        } catch (EntityNotFoundException $e) {
            return BatchOperationResult::failure(
                $index,
                $operation->action,
                $e->getMessage(),
                'NOT_FOUND',
                $operation->taskId
            );
        } catch (ForbiddenException $e) {
            return BatchOperationResult::failure(
                $index,
                $operation->action,
                $e->getMessage(),
                'FORBIDDEN',
                $operation->taskId
            );
        } catch (ValidationException $e) {
            return BatchOperationResult::failure(
                $index,
                $operation->action,
                'Validation failed: '.json_encode($e->getErrors()),
                'VALIDATION_ERROR',
                $operation->taskId
            );
        } catch (\Throwable $e) {
            $this->logger->error('Batch operation failed', [
                'index' => $index,
                'action' => $operation->action,
                'taskId' => $operation->taskId,
                'error' => $e->getMessage(),
            ]);

            return BatchOperationResult::failure(
                $index,
                $operation->action,
                $e->getMessage(),
                'INTERNAL_ERROR',
                $operation->taskId
            );
        }
    }

    /**
     * Executes a create operation.
     */
    private function executeCreate(
        User $user,
        BatchOperationRequest $operation,
        int $index,
        array &$previousStates,
    ): string {
        $dto = CreateTaskRequest::fromArray($operation->data);
        $task = $this->taskService->create($user, $dto);

        // Store state for undo (delete the created task)
        $previousStates[$index] = [
            'action' => BatchOperationRequest::ACTION_CREATE,
            'taskId' => $task->getId(),
        ];

        return $task->getId();
    }

    /**
     * Executes an update operation.
     */
    private function executeUpdate(
        User $user,
        BatchOperationRequest $operation,
        int $index,
        array &$previousStates,
    ): string {
        $task = $this->taskService->findByIdOrFail($operation->taskId, $user);

        // Store previous state for undo
        $previousStates[$index] = [
            'action' => BatchOperationRequest::ACTION_UPDATE,
            'taskId' => $task->getId(),
            'state' => $this->taskStateService->serializeTaskState($task),
        ];

        $dto = UpdateTaskRequest::fromArray($operation->data);
        $result = $this->taskService->update($task, $dto);

        return $result['task']->getId();
    }

    /**
     * Executes a delete operation.
     */
    private function executeDelete(
        User $user,
        BatchOperationRequest $operation,
        int $index,
        array &$previousStates,
    ): string {
        $task = $this->taskService->findByIdOrFail($operation->taskId, $user);
        $taskId = $task->getId();

        // Store full state for undo (restore deleted task)
        $previousStates[$index] = [
            'action' => BatchOperationRequest::ACTION_DELETE,
            'taskId' => $taskId,
            'state' => $this->taskStateService->serializeTaskState($task),
        ];

        $this->taskService->delete($task);

        return $taskId;
    }

    /**
     * Executes a complete operation.
     */
    private function executeComplete(
        User $user,
        BatchOperationRequest $operation,
        int $index,
        array &$previousStates,
    ): string {
        $task = $this->taskService->findByIdOrFail($operation->taskId, $user);

        // Store previous state for undo
        $previousStates[$index] = [
            'action' => BatchOperationRequest::ACTION_COMPLETE,
            'taskId' => $task->getId(),
            'state' => $this->taskStateService->serializeStatusState($task),
        ];

        $result = $this->taskService->changeStatus($task, Task::STATUS_COMPLETED);

        return $result->task->getId();
    }

    /**
     * Executes a reschedule operation.
     */
    private function executeReschedule(
        User $user,
        BatchOperationRequest $operation,
        int $index,
        array &$previousStates,
    ): string {
        $task = $this->taskService->findByIdOrFail($operation->taskId, $user);

        // Store previous state for undo
        $previousStates[$index] = [
            'action' => BatchOperationRequest::ACTION_RESCHEDULE,
            'taskId' => $task->getId(),
            'state' => $this->taskStateService->serializeTaskState($task),
        ];

        $dueDate = $operation->data['due_date'] ?? null;
        if ($dueDate === null) {
            throw ValidationException::forField('due_date', 'due_date is required for reschedule');
        }

        $result = $this->taskService->reschedule($task, $dueDate, $user);

        return $result['task']->getId();
    }

    /**
     * Creates a batch undo token.
     *
     * @param User                             $user           The user
     * @param array<int, array<string, mixed>> $previousStates The states to restore on undo
     *
     * @return string|null The undo token
     */
    private function createBatchUndoToken(User $user, array $previousStates): ?string
    {
        $undoToken = $this->undoService->createUndoToken(
            userId: $user->getId(),
            action: UndoAction::BATCH->value,
            entityType: self::BATCH_ENTITY_TYPE,
            entityId: 'batch-'.bin2hex(random_bytes(8)),
            previousState: ['operations' => $previousStates],
        );

        return $undoToken?->token;
    }

    /**
     * Undoes a batch operation.
     *
     * @param User   $user  The user
     * @param string $token The undo token
     *
     * @return array<string, mixed> The undo results
     */
    public function undoBatch(User $user, string $token): array
    {
        $undoToken = $this->undoService->consumeUndoToken($user->getId(), $token);

        if ($undoToken === null) {
            throw ValidationException::forField('token', 'Invalid or expired undo token');
        }

        if ($undoToken->entityType !== self::BATCH_ENTITY_TYPE) {
            throw ValidationException::forField('token', 'Token is not for a batch operation');
        }

        $operations = $undoToken->previousState['operations'] ?? [];
        $results = [];

        // Process in reverse order to undo properly
        foreach (array_reverse($operations, true) as $index => $opState) {
            try {
                $this->undoSingleOperation($user, $opState);
                $results[$index] = ['success' => true];
            } catch (\Throwable $e) {
                $results[$index] = ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return [
            'undone' => true,
            'results' => $results,
        ];
    }

    /**
     * Undoes a single operation from the batch.
     *
     * @param User                 $user    The user
     * @param array<string, mixed> $opState The operation state
     */
    private function undoSingleOperation(User $user, array $opState): void
    {
        $action = $opState['action'];
        $taskId = $opState['taskId'];

        match ($action) {
            BatchOperationRequest::ACTION_CREATE => $this->undoCreate($taskId),
            BatchOperationRequest::ACTION_DELETE => $this->undoDelete($user, $opState['state']),
            BatchOperationRequest::ACTION_UPDATE,
            BatchOperationRequest::ACTION_COMPLETE,
            BatchOperationRequest::ACTION_RESCHEDULE => $this->undoUpdate($taskId, $opState['state']),
            default => throw new \InvalidArgumentException('Unknown action to undo: '.$action),
        };
    }

    /**
     * Undoes a create by deleting the task.
     */
    private function undoCreate(string $taskId): void
    {
        $task = $this->taskRepository->find($taskId);
        if ($task !== null) {
            $this->entityManager->remove($task);
            $this->entityManager->flush();
        }
    }

    /**
     * Undoes a delete by restoring the task.
     *
     * @param User                 $user  The user
     * @param array<string, mixed> $state The task state
     */
    private function undoDelete(User $user, array $state): void
    {
        $task = $this->taskStateService->restoreTaskFromState($user, $state);
        $this->entityManager->persist($task);
        $this->entityManager->flush();
    }

    /**
     * Undoes an update by restoring previous state.
     *
     * @param string               $taskId The task ID
     * @param array<string, mixed> $state  The previous state
     */
    private function undoUpdate(string $taskId, array $state): void
    {
        $task = $this->taskRepository->find($taskId);
        if ($task === null) {
            throw EntityNotFoundException::task($taskId);
        }

        $this->taskStateService->applyStateToTask($task, $state);
        $this->entityManager->flush();
    }
}
