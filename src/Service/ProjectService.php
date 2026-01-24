<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateProjectRequest;
use App\DTO\UpdateProjectRequest;
use App\Entity\Project;
use App\Entity\User;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidStateException;
use App\Repository\ProjectRepository;
use App\ValueObject\UndoToken;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing Project entities.
 */
final class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidationHelper $validationHelper,
        private readonly OwnershipChecker $ownershipChecker,
        private readonly ProjectStateService $projectStateService,
        private readonly ProjectUndoService $projectUndoService,
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
        $previousState = $this->projectStateService->serializeProjectState($project);

        // Create undo token
        $undoToken = $this->projectUndoService->createUpdateUndoToken($project, $previousState);

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
     * Soft-delete a project.
     *
     * Sets deletedAt timestamp instead of actually removing the project.
     * Tasks remain associated but the project is hidden from normal queries.
     * Use undoDelete() to restore the project.
     *
     * @param Project $project The project to delete
     * @return UndoToken|null The undo token for restoring the project
     */
    public function delete(Project $project): ?UndoToken
    {
        $undoToken = $this->projectUndoService->createDeleteUndoToken($project);

        // Soft delete instead of hard delete
        $project->softDelete();
        $this->entityManager->flush();

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
        $previousState = $this->projectStateService->serializeArchiveState($project);

        $undoToken = $this->projectUndoService->createArchiveUndoToken($project, $previousState);

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
        $previousState = $this->projectStateService->serializeArchiveState($project);

        $undoToken = $this->projectUndoService->createArchiveUndoToken($project, $previousState);

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
        return $this->projectUndoService->undoArchive($user, $token);
    }

    /**
     * Undo a delete operation (restore soft-deleted project).
     *
     * Since we now use soft delete, this restores the original project
     * with its original ID and all associated tasks intact.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Project The restored project
     * @throws EntityNotFoundException If the project was permanently deleted
     */
    public function undoDelete(User $user, string $token): Project
    {
        return $this->projectUndoService->undoDelete($user, $token);
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
        return $this->projectUndoService->undoUpdate($user, $token);
    }

    /**
     * Undo any project operation using the token.
     *
     * Uses consume-then-validate pattern to avoid race conditions. The token
     * is atomically consumed first, then validated. This ensures only one
     * concurrent request can successfully use a token.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return array{project: Project, action: string, message: string, warning: string|null}
     * @throws EntityNotFoundException If the project no longer exists (for non-delete operations)
     */
    public function undo(User $user, string $token): array
    {
        return $this->projectUndoService->undo($user, $token);
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
}
