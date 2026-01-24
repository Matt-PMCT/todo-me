<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateTaskRequest;
use App\DTO\NaturalLanguageTaskRequest;
use App\DTO\TaskCreationResult;
use App\DTO\UpdateTaskRequest;
use App\Entity\Task;
use App\Entity\User;
use App\Exception\EntityNotFoundException;
use App\Exception\ForbiddenException;
use App\Exception\ValidationException;
use App\Interface\OwnershipCheckerInterface;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;
use App\Service\Parser\NaturalLanguageParserService;
use App\ValueObject\UndoToken;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for task-related operations.
 *
 * Handles core CRUD operations for tasks. Undo operations are delegated to
 * TaskUndoService, and state serialization is handled by TaskStateService.
 */
final class TaskService
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly TagRepository $tagRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidationHelper $validationHelper,
        private readonly OwnershipCheckerInterface $ownershipChecker,
        private readonly NaturalLanguageParserService $naturalLanguageParser,
        private readonly TaskStateService $taskStateService,
        private readonly TaskUndoService $taskUndoService,
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
     * Creates a task from natural language input.
     *
     * @param User $user The task owner
     * @param NaturalLanguageTaskRequest $dto The natural language input
     * @return TaskCreationResult The created task with parse result
     * @throws ValidationException If the title is empty after parsing
     */
    public function createFromNaturalLanguage(User $user, NaturalLanguageTaskRequest $dto): TaskCreationResult
    {
        // Parse input using NaturalLanguageParserService
        $parseResult = $this->naturalLanguageParser
            ->configure($user)
            ->parse($dto->inputText, $user);

        // Validate that we have a title
        if (trim($parseResult->title) === '') {
            throw ValidationException::forField('input_text', 'Task title cannot be empty');
        }

        // Create task using parsed data
        $task = new Task();
        $task->setOwner($user);
        $task->setTitle($parseResult->title);

        if ($parseResult->dueDate !== null) {
            $task->setDueDate($parseResult->dueDate);
        }

        if ($parseResult->dueTime !== null) {
            $task->setDueTime(new \DateTimeImmutable($parseResult->dueTime));
        }

        // Handle project (only if found and owned by user)
        $project = null;
        if ($parseResult->project !== null) {
            if ($this->ownershipChecker->isOwner($parseResult->project, $user)) {
                $project = $parseResult->project;
                $task->setProject($project);
            }
        }

        // Handle tags (all tags from parse result are already owned by user)
        foreach ($parseResult->tags as $tag) {
            $task->addTag($tag);
        }

        // Handle priority
        if ($parseResult->priority !== null) {
            $task->setPriority($parseResult->priority);
        } else {
            $task->setPriority(Task::PRIORITY_DEFAULT);
        }

        // Set position
        $maxPosition = $this->taskRepository->getMaxPosition($user, $project);
        $task->setPosition($maxPosition + 1);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return new TaskCreationResult(
            task: $task,
            parseResult: $parseResult,
            undoToken: null, // No undo for creates
        );
    }

    /**
     * Reschedule a task using natural language or ISO date.
     *
     * @param Task $task The task to reschedule
     * @param string $dateInput The date input (natural language or ISO format)
     * @param User $user The user context for date parsing
     * @return array{task: Task, undoToken: string|null}
     * @throws ValidationException If the date cannot be parsed
     */
    public function reschedule(Task $task, string $dateInput, User $user): array
    {
        // Store previous state for undo
        $previousState = $this->taskStateService->serializeTaskState($task);

        // Try parsing as ISO date first
        $date = $this->tryParseDueDate($dateInput);

        // If that fails, try natural language
        if ($date === null) {
            $this->naturalLanguageParser->configure($user);
            $parseResult = $this->naturalLanguageParser->parse($dateInput, $user);

            if ($parseResult->dueDate !== null) {
                $date = $parseResult->dueDate;
            } else {
                throw ValidationException::forField('due_date', 'Could not parse date: ' . $dateInput);
            }
        }

        $task->setDueDate($date);
        $this->entityManager->flush();

        // Create undo token
        $undoToken = $this->taskUndoService->createUpdateUndoToken($task, $previousState);

        return [
            'task' => $task,
            'undoToken' => $undoToken,
        ];
    }

    /**
     * Try to parse a date string, returning null on failure.
     *
     * @param string $dateString ISO 8601 date string
     * @return \DateTimeImmutable|null
     */
    private function tryParseDueDate(string $dateString): ?\DateTimeImmutable
    {
        try {
            // Try parsing as date only (Y-m-d)
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $dateString);

            if ($date !== false) {
                return $date;
            }

            // Try parsing as ISO 8601 with time
            $date = new \DateTimeImmutable($dateString);

            // Verify the date is valid by checking if it matches common formats
            $formatted = $date->format('Y-m-d');
            if (str_starts_with($dateString, $formatted)) {
                return $date;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
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
        $previousState = $this->taskStateService->serializeTaskState($task);

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
        $undoToken = $this->taskUndoService->createUpdateUndoToken($task, $previousState);

        return [
            'task' => $task,
            'undoToken' => $undoToken,
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
        // Create undo token before deleting
        $undoToken = $this->taskUndoService->createDeleteUndoToken($task);

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
        $previousState = $this->taskStateService->serializeStatusState($task);

        // Update status
        $task->setStatus($newStatus);

        $this->entityManager->flush();

        // Create undo token
        $undoToken = $this->taskUndoService->createStatusChangeUndoToken($task, $previousState);

        return [
            'task' => $task,
            'undoToken' => $undoToken,
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
        return $this->taskUndoService->undo($user, $token);
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
        return $this->taskUndoService->undoDelete($user, $token);
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
        return $this->taskUndoService->undoUpdate($user, $token);
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
}
