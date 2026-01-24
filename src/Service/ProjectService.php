<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateProjectRequest;
use App\DTO\UpdateProjectRequest;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\UndoAction;
use App\Exception\EntityNotFoundException;
use App\Repository\ProjectRepository;
use App\ValueObject\UndoToken;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing Project entities.
 */
final class ProjectService
{
    private const ENTITY_TYPE = 'project';

    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UndoService $undoService,
        private readonly ValidationHelper $validationHelper,
        private readonly OwnershipChecker $ownershipChecker,
    ) {
    }

    /**
     * Create a new project.
     *
     * @param User $user The owner of the project
     * @param CreateProjectRequest $dto The project data
     * @return Project The created project
     */
    public function create(User $user, CreateProjectRequest $dto): Project
    {
        $this->validationHelper->validate($dto);

        $project = new Project();
        $project->setOwner($user);
        $project->setName($dto->name);
        $project->setDescription($dto->description);

        $this->projectRepository->save($project, true);

        return $project;
    }

    /**
     * Update an existing project.
     *
     * @param Project $project The project to update
     * @param UpdateProjectRequest $dto The update data
     * @return array{project: Project, undoToken: UndoToken|null}
     */
    public function update(Project $project, UpdateProjectRequest $dto): array
    {
        $this->validationHelper->validate($dto);

        // Store previous state for undo
        $previousState = $this->serializeProjectState($project);

        // Create undo token
        $undoToken = $this->undoService->createUndoToken(
            userId: $project->getOwner()?->getId() ?? '',
            action: UndoAction::UPDATE->value,
            entityType: self::ENTITY_TYPE,
            entityId: $project->getId() ?? '',
            previousState: $previousState,
        );

        // Apply updates
        if ($dto->name !== null) {
            $project->setName($dto->name);
        }

        if ($dto->description !== null) {
            $project->setDescription($dto->description);
        }

        $this->entityManager->flush();

        return [
            'project' => $project,
            'undoToken' => $undoToken,
        ];
    }

    /**
     * Delete a project.
     *
     * WARNING: This will CASCADE DELETE all tasks associated with this project.
     * The tasks cannot be recovered with the undo operation.
     *
     * @param Project $project The project to delete
     * @return UndoToken|null The undo token for restoring the project (NOT its tasks)
     */
    public function delete(Project $project): ?UndoToken
    {
        // Store previous state for undo
        // Note: We only store the project state, not the tasks.
        // Tasks will be permanently deleted and cannot be recovered via undo.
        $previousState = $this->serializeProjectState($project);

        $undoToken = $this->undoService->createUndoToken(
            userId: $project->getOwner()?->getId() ?? '',
            action: UndoAction::DELETE->value,
            entityType: self::ENTITY_TYPE,
            entityId: $project->getId() ?? '',
            previousState: $previousState,
        );

        $this->projectRepository->remove($project, true);

        return $undoToken;
    }

    /**
     * Archive a project.
     *
     * Archived projects are hidden by default but tasks remain intact.
     *
     * @param Project $project The project to archive
     * @return array{project: Project, undoToken: UndoToken|null}
     */
    public function archive(Project $project): array
    {
        $previousState = [
            'isArchived' => $project->isArchived(),
            'archivedAt' => $project->getArchivedAt()?->format(\DateTimeInterface::ATOM),
        ];

        $undoToken = $this->undoService->createUndoToken(
            userId: $project->getOwner()?->getId() ?? '',
            action: UndoAction::ARCHIVE->value,
            entityType: self::ENTITY_TYPE,
            entityId: $project->getId() ?? '',
            previousState: $previousState,
        );

        $project->setIsArchived(true);
        $project->setArchivedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return [
            'project' => $project,
            'undoToken' => $undoToken,
        ];
    }

    /**
     * Unarchive a project.
     *
     * @param Project $project The project to unarchive
     * @return array{project: Project, undoToken: UndoToken|null}
     */
    public function unarchive(Project $project): array
    {
        $previousState = [
            'isArchived' => $project->isArchived(),
            'archivedAt' => $project->getArchivedAt()?->format(\DateTimeInterface::ATOM),
        ];

        $undoToken = $this->undoService->createUndoToken(
            userId: $project->getOwner()?->getId() ?? '',
            action: UndoAction::ARCHIVE->value,
            entityType: self::ENTITY_TYPE,
            entityId: $project->getId() ?? '',
            previousState: $previousState,
        );

        $project->setIsArchived(false);
        $project->setArchivedAt(null);
        $this->entityManager->flush();

        return [
            'project' => $project,
            'undoToken' => $undoToken,
        ];
    }

    /**
     * Undo an archive/unarchive operation.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Project The restored project
     * @throws EntityNotFoundException If the project no longer exists
     */
    public function undoArchive(User $user, string $token): Project
    {
        $undoToken = $this->undoService->consumeUndoToken($user->getId() ?? '', $token);

        if ($undoToken === null) {
            throw new \InvalidArgumentException('Invalid or expired undo token');
        }

        if ($undoToken->action !== UndoAction::ARCHIVE->value) {
            throw new \InvalidArgumentException('Invalid undo token type for archive operation');
        }

        $project = $this->findByIdOrFail($undoToken->entityId, $user);
        $this->applyStateToProject($project, $undoToken->previousState);
        $this->entityManager->flush();

        return $project;
    }

    /**
     * Undo a delete operation.
     *
     * LIMITATION: This only restores the project itself, NOT its tasks.
     * Tasks that were cascade-deleted when the project was deleted are permanently lost.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Project The restored project
     */
    public function undoDelete(User $user, string $token): Project
    {
        $undoToken = $this->undoService->consumeUndoToken($user->getId() ?? '', $token);

        if ($undoToken === null) {
            throw new \InvalidArgumentException('Invalid or expired undo token');
        }

        if ($undoToken->action !== UndoAction::DELETE->value) {
            throw new \InvalidArgumentException('Invalid undo token type for delete operation');
        }

        // Note: The original ID, createdAt, and tasks are NOT restored.
        // The project will get a new ID and createdAt timestamp.
        $project = new Project();
        $project->setOwner($user);
        $this->applyStateToProject($project, $undoToken->previousState);
        $this->projectRepository->save($project, true);

        return $project;
    }

    /**
     * Undo an update operation.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Project The restored project
     * @throws EntityNotFoundException If the project no longer exists
     */
    public function undoUpdate(User $user, string $token): Project
    {
        $undoToken = $this->undoService->consumeUndoToken($user->getId() ?? '', $token);

        if ($undoToken === null) {
            throw new \InvalidArgumentException('Invalid or expired undo token');
        }

        if ($undoToken->action !== UndoAction::UPDATE->value) {
            throw new \InvalidArgumentException('Invalid undo token type for update operation');
        }

        $project = $this->findByIdOrFail($undoToken->entityId, $user);
        $this->applyStateToProject($project, $undoToken->previousState);
        $this->entityManager->flush();

        return $project;
    }

    /**
     * Undo any project operation using the token.
     *
     * This method peeks at the token to determine the action type,
     * then delegates to the appropriate undo method.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return array{project: Project, action: string, message: string, warning: string|null}
     * @throws \InvalidArgumentException If the token is invalid or expired
     * @throws EntityNotFoundException If the project no longer exists (for non-delete operations)
     */
    public function undo(User $user, string $token): array
    {
        // First, peek at the token to determine the action type
        $undoToken = $this->undoService->getUndoToken($user->getId() ?? '', $token);

        if ($undoToken === null) {
            throw new \InvalidArgumentException('Invalid or expired undo token');
        }

        // Verify this is a project token
        if ($undoToken->entityType !== self::ENTITY_TYPE) {
            throw new \InvalidArgumentException('This undo token is not for a project');
        }

        // Now consume the token and perform the undo based on action type
        $consumedToken = $this->undoService->consumeUndoToken($user->getId() ?? '', $token);

        if ($consumedToken === null) {
            throw new \InvalidArgumentException('Failed to consume undo token');
        }

        $warning = null;

        switch ($consumedToken->action) {
            case UndoAction::UPDATE->value:
                $project = $this->performUndoExisting($user, $consumedToken);
                $message = 'Update operation undone successfully';
                break;

            case UndoAction::ARCHIVE->value:
                $project = $this->performUndoExisting($user, $consumedToken);
                $wasArchived = $consumedToken->previousState['isArchived'] ?? false;
                $message = $wasArchived
                    ? 'Project archived again (undo of unarchive)'
                    : 'Project unarchived (undo of archive)';
                break;

            case UndoAction::DELETE->value:
                $project = $this->performUndoDelete($user, $consumedToken);
                $message = 'Delete operation undone successfully. Note: Previously associated tasks were not restored.';
                $warning = 'Tasks that were deleted with the project have been permanently lost';
                break;

            default:
                throw new \InvalidArgumentException('Unknown undo action type: ' . $consumedToken->action);
        }

        return [
            'project' => $project,
            'action' => $consumedToken->action,
            'message' => $message,
            'warning' => $warning,
        ];
    }

    /**
     * Perform undo on an existing project (update or archive operations).
     */
    private function performUndoExisting(User $user, UndoToken $undoToken): Project
    {
        $project = $this->findByIdOrFail($undoToken->entityId, $user);
        $this->applyStateToProject($project, $undoToken->previousState);
        $this->entityManager->flush();

        return $project;
    }

    /**
     * Perform the actual delete undo using a consumed token.
     */
    private function performUndoDelete(User $user, UndoToken $undoToken): Project
    {
        $project = new Project();
        $project->setOwner($user);
        $this->applyStateToProject($project, $undoToken->previousState);
        $this->projectRepository->save($project, true);

        return $project;
    }

    /**
     * Find a project by ID and verify ownership.
     *
     * @param string $id The project ID
     * @param User $user The user who should own the project
     * @return Project The project
     * @throws EntityNotFoundException If the project is not found or not owned by user
     */
    public function findByIdOrFail(string $id, User $user): Project
    {
        $project = $this->projectRepository->findOneByOwnerAndId($user, $id);

        if ($project === null) {
            throw EntityNotFoundException::project($id);
        }

        return $project;
    }

    /**
     * Get task counts for a project.
     *
     * @param Project $project The project
     * @return array{total: int, completed: int}
     */
    public function getTaskCounts(Project $project): array
    {
        return [
            'total' => $this->projectRepository->countTasksByProject($project),
            'completed' => $this->projectRepository->countCompletedTasksByProject($project),
        ];
    }

    /**
     * Serialize project state for undo operations.
     *
     * @param Project $project The project to serialize
     * @return array<string, mixed>
     */
    private function serializeProjectState(Project $project): array
    {
        return [
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'isArchived' => $project->isArchived(),
        ];
    }

    /**
     * Apply a serialized state to an existing project.
     *
     * @param Project $project The project to update
     * @param array<string, mixed> $state The state to apply
     */
    private function applyStateToProject(Project $project, array $state): void
    {
        if (array_key_exists('name', $state)) {
            $project->setName($state['name']);
        }

        if (array_key_exists('description', $state)) {
            $project->setDescription($state['description']);
        }

        if (array_key_exists('isArchived', $state)) {
            $project->setIsArchived($state['isArchived']);
        }

        if (array_key_exists('archivedAt', $state)) {
            $project->setArchivedAt(
                $state['archivedAt'] !== null
                    ? new \DateTimeImmutable($state['archivedAt'])
                    : null
            );
        }
    }
}
