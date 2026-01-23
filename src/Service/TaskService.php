<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateTaskRequest;
use App\DTO\UpdateTaskRequest;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\UndoAction;
use App\Exception\EntityNotFoundException;
use App\Exception\ForbiddenException;
use App\Exception\ValidationException;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;
use App\ValueObject\UndoToken;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for task-related operations.
 */
final class TaskService
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly TagRepository $tagRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UndoService $undoService,
        private readonly ValidationHelper $validationHelper,
        private readonly OwnershipChecker $ownershipChecker,
    ) {
    }

    /**
     * Creates a new task.
     *
     * @param User $user The task owner
     * @param CreateTaskRequest $dto The task creation data
     * @return Task The created task
     * @throws ValidationException If validation fails
     * @throws EntityNotFoundException If project or tags are not found
     * @throws ForbiddenException If user doesn't own the project
     */
    public function create(User $user, CreateTaskRequest $dto): Task
    {
        // Validate the DTO
        $this->validationHelper->validate($dto);

        // Validate status and priority
        $this->validationHelper->validateTaskStatus($dto->status);
        $this->validationHelper->validateTaskPriority($dto->priority);

        // Create the task
        $task = new Task();
        $task->setOwner($user);
        $task->setTitle($dto->title);
        $task->setDescription($dto->description);
        $task->setStatus($dto->status);
        $task->setPriority($dto->priority);

        // Handle due date
        if ($dto->dueDate !== null) {
            $dueDate = $this->parseDueDate($dto->dueDate);
            $task->setDueDate($dueDate);
        }

        // Handle project association
        $project = null;
        if ($dto->projectId !== null) {
            $project = $this->projectRepository->find($dto->projectId);

            if ($project === null) {
                throw EntityNotFoundException::project($dto->projectId);
            }

            // Verify ownership
            $this->ownershipChecker->checkOwnership($project);
            $task->setProject($project);
        }

        // Set position to max + 1
        $maxPosition = $this->taskRepository->getMaxPosition($user, $project);
        $task->setPosition($maxPosition + 1);

        // Handle tags
        if ($dto->tagIds !== null && !empty($dto->tagIds)) {
            $this->attachTags($task, $user, $dto->tagIds);
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * Updates an existing task.
     *
     * @param Task $task The task to update
     * @param UpdateTaskRequest $dto The update data
     * @return array{task: Task, undoToken: string|null}
     * @throws ValidationException If validation fails
     * @throws EntityNotFoundException If project or tags are not found
     * @throws ForbiddenException If user doesn't own the project
     */
    public function update(Task $task, UpdateTaskRequest $dto): array
    {
        // Validate the DTO
        $this->validationHelper->validate($dto);

        // Store previous state for undo
        $previousState = $this->serializeTaskState($task);

        // Update title
        if ($dto->title !== null) {
            $task->setTitle($dto->title);
        }

        // Update description
        if ($dto->description !== null) {
            $task->setDescription($dto->description);
        } elseif ($dto->clearDescription) {
            $task->setDescription(null);
        }

        // Update status
        if ($dto->status !== null) {
            $this->validationHelper->validateTaskStatus($dto->status);
            $task->setStatus($dto->status);
        }

        // Update priority
        if ($dto->priority !== null) {
            $this->validationHelper->validateTaskPriority($dto->priority);
            $task->setPriority($dto->priority);
        }

        // Update due date
        if ($dto->dueDate !== null) {
            $dueDate = $this->parseDueDate($dto->dueDate);
            $task->setDueDate($dueDate);
        } elseif ($dto->clearDueDate) {
            $task->setDueDate(null);
        }

        // Update project
        if ($dto->projectId !== null) {
            $project = $this->projectRepository->find($dto->projectId);

            if ($project === null) {
                throw EntityNotFoundException::project($dto->projectId);
            }

            $this->ownershipChecker->checkOwnership($project);
            $task->setProject($project);
        } elseif ($dto->clearProject) {
            $task->setProject(null);
        }

        // Update tags
        if ($dto->tagIds !== null) {
            // Clear existing tags and attach new ones
            foreach ($task->getTags()->toArray() as $tag) {
                $task->removeTag($tag);
            }

            if (!empty($dto->tagIds)) {
                $this->attachTags($task, $task->getOwner(), $dto->tagIds);
            }
        }

        $this->entityManager->flush();

        // Create undo token
        $undoToken = $this->undoService->createUndoToken(
            userId: $task->getOwner()->getId(),
            action: UndoAction::UPDATE->value,
            entityType: 'task',
            entityId: $task->getId(),
            previousState: $previousState,
        );

        return [
            'task' => $task,
            'undoToken' => $undoToken?->token,
        ];
    }

    /**
     * Deletes a task and returns an undo token.
     *
     * @param Task $task The task to delete
     * @return UndoToken|null The undo token for restoring the task
     */
    public function delete(Task $task): ?UndoToken
    {
        // Store full state for undo
        $previousState = $this->serializeTaskState($task);

        // Create undo token before deleting
        $undoToken = $this->undoService->createUndoToken(
            userId: $task->getOwner()->getId(),
            action: UndoAction::DELETE->value,
            entityType: 'task',
            entityId: $task->getId(),
            previousState: $previousState,
        );

        // Remove the task
        $this->entityManager->remove($task);
        $this->entityManager->flush();

        return $undoToken;
    }

    /**
     * Changes the status of a task.
     *
     * @param Task $task The task to update
     * @param string $newStatus The new status
     * @return array{task: Task, undoToken: string|null}
     */
    public function changeStatus(Task $task, string $newStatus): array
    {
        // Validate status
        $this->validationHelper->validateTaskStatus($newStatus);

        // Store previous state for undo
        $previousState = [
            'status' => $task->getStatus(),
            'completedAt' => $task->getCompletedAt()?->format(\DateTimeInterface::RFC3339),
        ];

        // Update status
        $task->setStatus($newStatus);

        $this->entityManager->flush();

        // Create undo token
        $undoToken = $this->undoService->createUndoToken(
            userId: $task->getOwner()->getId(),
            action: UndoAction::STATUS_CHANGE->value,
            entityType: 'task',
            entityId: $task->getId(),
            previousState: $previousState,
        );

        return [
            'task' => $task,
            'undoToken' => $undoToken?->token,
        ];
    }

    /**
     * Reorders tasks.
     *
     * @param User $user The task owner
     * @param string[] $taskIds The task IDs in the desired order
     */
    public function reorder(User $user, array $taskIds): void
    {
        $this->taskRepository->reorderTasks($user, $taskIds);
    }

    /**
     * Undoes a task operation (generic handler for all undo types).
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Task The restored/updated task
     * @throws ValidationException If the token is invalid or expired
     * @throws EntityNotFoundException If the task no longer exists (for update operations)
     */
    public function undo(User $user, string $token): Task
    {
        $undoToken = $this->undoService->consumeUndoToken($user->getId(), $token);

        if ($undoToken === null) {
            throw ValidationException::forField('token', 'Invalid or expired undo token');
        }

        if ($undoToken->entityType !== 'task') {
            throw ValidationException::forField('token', 'Token is not for a task');
        }

        return match ($undoToken->action) {
            UndoAction::DELETE->value => $this->performUndoDelete($user, $undoToken),
            UndoAction::UPDATE->value, UndoAction::STATUS_CHANGE->value => $this->performUndoUpdate($undoToken),
            default => throw ValidationException::forField('token', 'Unknown undo action type'),
        };
    }

    /**
     * Undoes a delete operation.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Task The restored task
     * @throws ValidationException If the token is invalid or expired
     */
    public function undoDelete(User $user, string $token): Task
    {
        $undoToken = $this->undoService->consumeUndoToken($user->getId(), $token);

        if ($undoToken === null) {
            throw ValidationException::forField('token', 'Invalid or expired undo token');
        }

        if ($undoToken->action !== UndoAction::DELETE->value) {
            throw ValidationException::forField('token', 'Token is not for a delete operation');
        }

        if ($undoToken->entityType !== 'task') {
            throw ValidationException::forField('token', 'Token is not for a task');
        }

        return $this->performUndoDelete($user, $undoToken);
    }

    /**
     * Undoes an update operation.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Task The restored task
     * @throws ValidationException If the token is invalid or expired
     * @throws EntityNotFoundException If the task no longer exists
     */
    public function undoUpdate(User $user, string $token): Task
    {
        $undoToken = $this->undoService->consumeUndoToken($user->getId(), $token);

        if ($undoToken === null) {
            throw ValidationException::forField('token', 'Invalid or expired undo token');
        }

        if (!in_array($undoToken->action, [UndoAction::UPDATE->value, UndoAction::STATUS_CHANGE->value], true)) {
            throw ValidationException::forField('token', 'Token is not for an update operation');
        }

        if ($undoToken->entityType !== 'task') {
            throw ValidationException::forField('token', 'Token is not for a task');
        }

        return $this->performUndoUpdate($undoToken);
    }

    /**
     * Performs the actual delete undo operation.
     */
    private function performUndoDelete(User $user, UndoToken $undoToken): Task
    {
        // Restore the task from previous state
        $task = $this->restoreTaskFromState($user, $undoToken->previousState);

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
        $this->applyStateToTask($task, $undoToken->previousState);

        $this->entityManager->flush();

        return $task;
    }

    /**
     * Finds a task by ID and verifies ownership.
     *
     * @param string $id The task ID
     * @param User $user The expected owner
     * @return Task The task
     * @throws EntityNotFoundException If the task is not found
     * @throws ForbiddenException If the user doesn't own the task
     */
    public function findByIdOrFail(string $id, User $user): Task
    {
        $task = $this->taskRepository->find($id);

        if ($task === null) {
            throw EntityNotFoundException::task($id);
        }

        if (!$this->ownershipChecker->isOwner($task, $user)) {
            throw ForbiddenException::notOwner('Task');
        }

        return $task;
    }

    /**
     * Parses a due date string into a DateTimeImmutable.
     *
     * @param string $dateString ISO 8601 date string
     * @return \DateTimeImmutable
     * @throws ValidationException If the date format is invalid
     */
    private function parseDueDate(string $dateString): \DateTimeImmutable
    {
        try {
            // Try parsing as date only (Y-m-d)
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateString);

            if ($date !== false) {
                return $date;
            }

            // Try parsing as ISO 8601 with time
            $date = new \DateTimeImmutable($dateString);

            return $date;
        } catch (\Exception $e) {
            throw ValidationException::forField('dueDate', 'Invalid date format. Use ISO 8601 format (e.g., 2024-01-15)');
        }
    }

    /**
     * Attaches tags to a task.
     *
     * @param Task $task The task
     * @param User $user The user (for ownership verification)
     * @param string[] $tagIds The tag IDs
     * @throws EntityNotFoundException If a tag is not found
     * @throws ForbiddenException If user doesn't own a tag
     */
    private function attachTags(Task $task, User $user, array $tagIds): void
    {
        foreach ($tagIds as $tagId) {
            $tag = $this->tagRepository->find($tagId);

            if ($tag === null) {
                throw EntityNotFoundException::tag($tagId);
            }

            if (!$this->ownershipChecker->isOwner($tag, $user)) {
                throw ForbiddenException::notOwner('Tag');
            }

            $task->addTag($tag);
        }
    }

    /**
     * Serializes a task state for undo operations.
     *
     * @param Task $task The task to serialize
     * @return array<string, mixed>
     */
    private function serializeTaskState(Task $task): array
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
            'position' => $task->getPosition(),
            'projectId' => $task->getProject()?->getId(),
            'tagIds' => $tagIds,
            'completedAt' => $task->getCompletedAt()?->format(\DateTimeInterface::RFC3339),
            'createdAt' => $task->getCreatedAt()->format(\DateTimeInterface::RFC3339),
        ];
    }

    /**
     * Restores a task from a serialized state.
     *
     * @param User $user The task owner
     * @param array<string, mixed> $state The serialized state
     * @return Task The restored task
     */
    private function restoreTaskFromState(User $user, array $state): Task
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
     * @param Task $task The task to update
     * @param array<string, mixed> $state The state to apply
     */
    private function applyStateToTask(Task $task, array $state): void
    {
        if (isset($state['title'])) {
            $task->setTitle($state['title']);
        }

        if (array_key_exists('description', $state)) {
            $task->setDescription($state['description']);
        }

        if (isset($state['status'])) {
            // Use direct property access to avoid triggering completedAt logic
            // when restoring state, we'll handle completedAt separately
            $reflection = new \ReflectionClass($task);
            $property = $reflection->getProperty('status');
            $property->setValue($task, $state['status']);
        }

        if (isset($state['priority'])) {
            $task->setPriority($state['priority']);
        }

        if (array_key_exists('dueDate', $state)) {
            $task->setDueDate(
                $state['dueDate'] !== null
                    ? new \DateTimeImmutable($state['dueDate'])
                    : null
            );
        }

        if (isset($state['position'])) {
            $task->setPosition($state['position']);
        }

        // Handle project
        if (array_key_exists('projectId', $state)) {
            if ($state['projectId'] !== null) {
                $project = $this->projectRepository->find($state['projectId']);
                $task->setProject($project);
            } else {
                $task->setProject(null);
            }
        }

        // Handle tags
        if (isset($state['tagIds'])) {
            // Clear existing tags
            foreach ($task->getTags()->toArray() as $tag) {
                $task->removeTag($tag);
            }

            // Add tags from state
            foreach ($state['tagIds'] as $tagId) {
                $tag = $this->tagRepository->find($tagId);
                if ($tag !== null) {
                    $task->addTag($tag);
                }
            }
        }

        // Restore completedAt
        if (array_key_exists('completedAt', $state)) {
            $task->setCompletedAt(
                $state['completedAt'] !== null
                    ? new \DateTimeImmutable($state['completedAt'])
                    : null
            );
        }
    }
}
