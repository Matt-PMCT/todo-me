<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateProjectRequest;
use App\DTO\MoveProjectRequest;
use App\DTO\ProjectSettingsRequest;
use App\DTO\UpdateProjectRequest;
use App\Entity\Project;
use App\Entity\User;
use App\Exception\BatchSizeLimitExceededException;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidStateException;
use App\Exception\ProjectMoveToArchivedException;
use App\Exception\ProjectMoveToDescendantException;
use App\Exception\ProjectParentNotFoundException;
use App\Interface\ActivityLogServiceInterface;
use App\Interface\OwnershipCheckerInterface;
use App\Repository\ProjectRepository;
use App\Transformer\ProjectTreeTransformer;
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
        private readonly OwnershipCheckerInterface $ownershipChecker,
        private readonly ProjectStateService $projectStateService,
        private readonly ProjectUndoService $projectUndoService,
        private readonly ProjectCacheService $projectCacheService,
        private readonly ProjectTreeTransformer $projectTreeTransformer,
        private readonly ActivityLogServiceInterface $activityLogService,
    ) {
    }

    /**
     * Assign the next available position to a project within its parent.
     */
    private function assignNextPosition(User $user, ?string $parentId, Project $project): void
    {
        $maxPosition = $this->projectRepository->getMaxPositionInParent($user, $parentId);
        $project->setPosition($maxPosition + 1);
    }

    /**
     * Get validated owner ID from a project, throwing if missing.
     */
    private function getValidatedOwnerId(Project $project): string
    {
        $ownerId = $project->getOwner()?->getId();
        if ($ownerId === null) {
            throw InvalidStateException::missingOwner('Project');
        }

        return $ownerId;
    }

    /**
     * Get validated owner from a project, throwing if missing.
     *
     * @return array{User, string} The owner and owner ID
     */
    private function getValidatedOwner(Project $project): array
    {
        $user = $project->getOwner();
        $ownerId = $user?->getId();
        if ($ownerId === null || $user === null) {
            throw InvalidStateException::missingOwner('Project');
        }

        return [$user, $ownerId];
    }

    /**
     * Create a new project.
     *
     * @param User                 $user The owner of the project
     * @param CreateProjectRequest $dto  The project data
     *
     * @return Project The created project
     */
    public function create(User $user, CreateProjectRequest $dto): Project
    {
        $this->validationHelper->validate($dto);

        $project = new Project();
        $project->setOwner($user);
        $project->setName($dto->name);
        $project->setDescription($dto->description);

        if ($dto->color !== null) {
            $project->setColor($dto->color);
        }

        if ($dto->icon !== null) {
            $project->setIcon($dto->icon);
        }

        // Handle parent assignment
        if ($dto->parentId !== null) {
            $parent = $this->validateAndGetParent($user, $dto->parentId);
            $project->setParent($parent);
        }

        // Set position at end of siblings
        $this->assignNextPosition($user, $dto->parentId, $project);

        $this->entityManager->persist($project);

        // Log the project creation
        $this->activityLogService->logProjectCreated($project);

        $this->entityManager->flush();

        // Invalidate cache
        $this->projectCacheService->invalidate($user->getId() ?? '');

        return $project;
    }

    /**
     * Update an existing project.
     *
     * @param Project              $project The project to update
     * @param UpdateProjectRequest $dto     The update data
     *
     * @return array{project: Project, undoToken: UndoToken|null}
     */
    public function update(Project $project, UpdateProjectRequest $dto): array
    {
        $this->validationHelper->validate($dto);

        // Validate required IDs for undo token creation
        [$user, $ownerId] = $this->getValidatedOwner($project);
        $projectId = $project->getId();

        if ($projectId === null) {
            throw InvalidStateException::missingRequiredId('Project');
        }

        // Track changes for activity log
        $oldName = $project->getName();
        $oldDescription = $project->getDescription();
        $oldParentId = $project->getParent()?->getId();

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

        // Handle parent change
        if ($dto->hasParentIdChange()) {
            $newParentId = $dto->parentId;

            if ($newParentId === null) {
                // Move to root
                $project->setParent(null);
            } else {
                // Move to new parent
                $parent = $this->validateAndGetParent($user, $newParentId, $project);
                $project->setParent($parent);
            }

            // Set position at end of new sibling list
            $this->assignNextPosition($user, $newParentId, $project);

            // Normalize positions in old parent
            if ($oldParentId !== $newParentId) {
                $this->projectRepository->normalizePositions($user, $oldParentId);
            }
        }

        // Build changes array for activity log
        $changes = [];
        if ($dto->name !== null && $project->getName() !== $oldName) {
            $changes['name'] = ['old' => $oldName, 'new' => $project->getName()];
        }
        if ($dto->description !== null && $project->getDescription() !== $oldDescription) {
            $changes['description'] = ['old' => $oldDescription, 'new' => $project->getDescription()];
        }
        $newParentId = $project->getParent()?->getId();
        if ($dto->hasParentIdChange() && $newParentId !== $oldParentId) {
            $changes['parentId'] = ['old' => $oldParentId, 'new' => $newParentId];
        }

        // Log the update if there were changes
        if (!empty($changes)) {
            $this->activityLogService->logProjectUpdated($project, $changes);
        }

        $this->entityManager->flush();

        // Invalidate cache
        $this->projectCacheService->invalidate($ownerId);

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
     *
     * @return UndoToken|null The undo token for restoring the project
     */
    public function delete(Project $project): ?UndoToken
    {
        $ownerId = $this->getValidatedOwnerId($project);

        // Capture info for activity log before deletion
        $owner = $project->getOwner();
        $projectId = $project->getId();
        $projectName = $project->getName();

        $undoToken = $this->projectUndoService->createDeleteUndoToken($project);

        // Log the deletion
        $this->activityLogService->logProjectDeleted($owner, $projectId, $projectName);

        // Soft delete instead of hard delete
        $project->softDelete();
        $this->entityManager->flush();

        // Invalidate cache
        $this->projectCacheService->invalidate($ownerId);

        return $undoToken;
    }

    /**
     * Archive a project.
     *
     * Archived projects are hidden by default but tasks remain intact.
     *
     * @param Project $project The project to archive
     *
     * @return array{project: Project, undoToken: UndoToken|null}
     */
    public function archive(Project $project): array
    {
        $ownerId = $this->getValidatedOwnerId($project);

        $previousState = $this->projectStateService->serializeArchiveState($project);

        $undoToken = $this->projectUndoService->createArchiveUndoToken($project, $previousState);

        $project->setIsArchived(true);
        $project->setArchivedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // Invalidate cache
        $this->projectCacheService->invalidate($ownerId);

        return [
            'project' => $project,
            'undoToken' => $undoToken,
        ];
    }

    /**
     * Unarchive a project.
     *
     * @param Project $project The project to unarchive
     *
     * @return array{project: Project, undoToken: UndoToken|null}
     */
    public function unarchive(Project $project): array
    {
        $ownerId = $this->getValidatedOwnerId($project);

        $previousState = $this->projectStateService->serializeArchiveState($project);

        $undoToken = $this->projectUndoService->createArchiveUndoToken($project, $previousState);

        $project->setIsArchived(false);
        $project->setArchivedAt(null);
        $this->entityManager->flush();

        // Invalidate cache
        $this->projectCacheService->invalidate($ownerId);

        return [
            'project' => $project,
            'undoToken' => $undoToken,
        ];
    }

    /**
     * Undo an archive/unarchive operation.
     *
     * @param User   $user  The user performing the undo
     * @param string $token The undo token
     *
     * @return Project The restored project
     *
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
     * @param User   $user  The user performing the undo
     * @param string $token The undo token
     *
     * @return Project The restored project
     *
     * @throws EntityNotFoundException If the project was permanently deleted
     */
    public function undoDelete(User $user, string $token): Project
    {
        return $this->projectUndoService->undoDelete($user, $token);
    }

    /**
     * Undo an update operation.
     *
     * @param User   $user  The user performing the undo
     * @param string $token The undo token
     *
     * @return Project The restored project
     *
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
     * @param User   $user  The user performing the undo
     * @param string $token The undo token
     *
     * @return array{project: Project, action: string, message: string, warning: string|null}
     *
     * @throws EntityNotFoundException If the project no longer exists (for non-delete operations)
     */
    public function undo(User $user, string $token): array
    {
        return $this->projectUndoService->undo($user, $token);
    }

    /**
     * Find a project by ID and verify ownership.
     *
     * @param string $id   The project ID
     * @param User   $user The user who should own the project
     *
     * @return Project The project
     *
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
     *
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
     * Move a project to a new parent.
     *
     * @param Project            $project The project to move
     * @param MoveProjectRequest $dto     The move request
     *
     * @return array{project: Project, undoToken: UndoToken|null}
     */
    public function move(Project $project, MoveProjectRequest $dto): array
    {
        $this->validationHelper->validate($dto);

        [$user, $ownerId] = $this->getValidatedOwner($project);

        $this->entityManager->beginTransaction();

        try {
            // Store previous state for undo
            $previousState = $this->projectStateService->serializeProjectState($project);

            // Create undo token
            $undoToken = $this->projectUndoService->createUpdateUndoToken($project, $previousState);

            $oldParentId = $project->getParent()?->getId();

            if ($dto->parentId === null) {
                // Move to root
                $project->setParent(null);
            } else {
                // Move to new parent
                $newParent = $this->validateAndGetParent($user, $dto->parentId, $project);
                $project->setParent($newParent);
            }

            // Set position
            if ($dto->position !== null) {
                $project->setPosition($dto->position);
            } else {
                $this->assignNextPosition($user, $dto->parentId, $project);
            }

            $this->entityManager->flush();

            // Normalize positions in old parent
            if ($oldParentId !== $dto->parentId) {
                $this->projectRepository->normalizePositions($user, $oldParentId);
            }

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            throw $e;
        }

        // Invalidate cache
        $this->projectCacheService->invalidate($ownerId);

        return [
            'project' => $project,
            'undoToken' => $undoToken,
        ];
    }

    /**
     * Reorder a project within its current parent.
     *
     * @param Project $project     The project to reorder
     * @param int     $newPosition The new position
     *
     * @return array{project: Project, undoToken: UndoToken|null}
     */
    public function reorder(Project $project, int $newPosition): array
    {
        [$user, $ownerId] = $this->getValidatedOwner($project);

        $previousState = $this->projectStateService->serializeProjectState($project);

        $undoToken = $this->projectUndoService->createUpdateUndoToken($project, $previousState);

        $project->setPosition($newPosition);
        $this->entityManager->flush();

        // Normalize positions
        $parentId = $project->getParent()?->getId();
        $this->projectRepository->normalizePositions($user, $parentId);

        // Invalidate cache
        $this->projectCacheService->invalidate($ownerId);

        return [
            'project' => $project,
            'undoToken' => $undoToken,
        ];
    }

    /**
     * Batch reorder projects within a parent.
     *
     * @param User        $user       The user
     * @param string|null $parentId   The parent project ID (null for root)
     * @param string[]    $projectIds The project IDs in desired order
     */
    public function batchReorder(User $user, ?string $parentId, array $projectIds): void
    {
        if (count($projectIds) > 1000) {
            throw BatchSizeLimitExceededException::create(count($projectIds), 1000);
        }

        $ownerId = $user->getId();
        if ($ownerId === null) {
            throw InvalidStateException::missingRequiredId('User');
        }

        // Validate parent ownership if parentId is provided
        if ($parentId !== null) {
            $parent = $this->projectRepository->findOneByOwnerAndId($user, $parentId);
            if ($parent === null) {
                throw EntityNotFoundException::project($parentId);
            }
        }

        $this->entityManager->beginTransaction();

        try {
            // Validate all projects belong to user and have the correct parent
            foreach ($projectIds as $position => $projectId) {
                $project = $this->projectRepository->findOneByOwnerAndId($user, $projectId);

                if ($project === null) {
                    throw EntityNotFoundException::project($projectId);
                }

                $actualParentId = $project->getParent()?->getId();
                if ($actualParentId !== $parentId) {
                    throw new \InvalidArgumentException(
                        sprintf('Project %s does not have parent %s', $projectId, $parentId ?? 'root')
                    );
                }

                $project->setPosition($position);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            throw $e;
        }

        // Invalidate cache
        $this->projectCacheService->invalidate($ownerId);
    }

    /**
     * Update project settings.
     *
     * @param Project                $project The project to update
     * @param ProjectSettingsRequest $dto     The settings
     *
     * @return Project The updated project
     */
    public function updateSettings(Project $project, ProjectSettingsRequest $dto): Project
    {
        $this->validationHelper->validate($dto);

        $ownerId = $this->getValidatedOwnerId($project);

        if ($dto->showChildrenTasks !== null) {
            $project->setShowChildrenTasks($dto->showChildrenTasks);
        }

        $this->entityManager->flush();

        // Invalidate cache
        $this->projectCacheService->invalidate($ownerId);

        return $project;
    }

    /**
     * Get the project tree for a user.
     *
     * @param User $user              The user
     * @param bool $includeArchived   Whether to include archived projects
     * @param bool $includeTaskCounts Whether to include task counts
     *
     * @return array The tree structure
     */
    public function getTree(User $user, bool $includeArchived = false, bool $includeTaskCounts = false): array
    {
        $userId = $user->getId();
        if ($userId === null) {
            throw InvalidStateException::missingRequiredId('User');
        }

        // Try cache first
        $cached = $this->projectCacheService->get($userId, $includeArchived, $includeTaskCounts);
        if ($cached !== null) {
            return $cached;
        }

        // Build tree
        $projects = $this->projectRepository->getTreeByUser($user, $includeArchived);

        $taskCounts = [];
        if ($includeTaskCounts) {
            $taskCounts = $this->projectRepository->getTaskCountsForProjects($projects);
        }

        $tree = $this->projectTreeTransformer->transformToTree($projects, $taskCounts);

        // Cache result
        $this->projectCacheService->set($userId, $includeArchived, $includeTaskCounts, $tree);

        return $tree;
    }

    /**
     * Archive a project with options for handling children.
     *
     * @param Project $project         The project to archive
     * @param bool    $cascade         Archive all descendants
     * @param bool    $promoteChildren Move children to the project's parent
     *
     * @return array{project: Project, undoToken: UndoToken|null, affectedProjects: array}
     */
    public function archiveWithOptions(Project $project, bool $cascade = false, bool $promoteChildren = false): array
    {
        $ownerId = $this->getValidatedOwnerId($project);

        $this->entityManager->beginTransaction();

        try {
            $previousState = $this->projectStateService->serializeArchiveState($project);

            $undoToken = $this->projectUndoService->createArchiveUndoToken($project, $previousState);

            $affectedProjects = [];

            if ($cascade) {
                // Archive all descendants (findAllDescendants already filters by owner)
                $descendants = $this->projectRepository->findAllDescendants($project);
                foreach ($descendants as $descendant) {
                    // Verify ownership of each descendant for defense in depth
                    if ($descendant->getOwner()?->getId() !== $ownerId) {
                        continue;
                    }
                    if (!$descendant->isArchived()) {
                        $descendant->setIsArchived(true);
                        $descendant->setArchivedAt(new \DateTimeImmutable());
                        $affectedProjects[] = $descendant->getId();
                    }
                }
            } elseif ($promoteChildren) {
                // Move children to this project's parent
                $parentProject = $project->getParent();
                $children = $this->projectRepository->findChildrenByParent($project, true);
                foreach ($children as $child) {
                    $child->setParent($parentProject);
                    $affectedProjects[] = $child->getId();
                }
            }

            $project->setIsArchived(true);
            $project->setArchivedAt(new \DateTimeImmutable());

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            throw $e;
        }

        // Invalidate cache
        $this->projectCacheService->invalidate($ownerId);

        return [
            'project' => $project,
            'undoToken' => $undoToken,
            'affectedProjects' => $affectedProjects,
        ];
    }

    /**
     * Unarchive a project with options for handling children.
     *
     * @param Project $project The project to unarchive
     * @param bool    $cascade Unarchive all descendants
     *
     * @return array{project: Project, undoToken: UndoToken|null, affectedProjects: array}
     */
    public function unarchiveWithOptions(Project $project, bool $cascade = false): array
    {
        $ownerId = $this->getValidatedOwnerId($project);

        $this->entityManager->beginTransaction();

        try {
            $previousState = $this->projectStateService->serializeArchiveState($project);

            $undoToken = $this->projectUndoService->createArchiveUndoToken($project, $previousState);

            $affectedProjects = [];

            if ($cascade) {
                // Unarchive all descendants
                $descendants = $this->projectRepository->findAllDescendants($project);
                foreach ($descendants as $descendant) {
                    if ($descendant->isArchived()) {
                        $descendant->setIsArchived(false);
                        $descendant->setArchivedAt(null);
                        $affectedProjects[] = $descendant->getId();
                    }
                }
            }

            $project->setIsArchived(false);
            $project->setArchivedAt(null);

            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            throw $e;
        }

        // Invalidate cache
        $this->projectCacheService->invalidate($ownerId);

        return [
            'project' => $project,
            'undoToken' => $undoToken,
            'affectedProjects' => $affectedProjects,
        ];
    }

    /**
     * Get archived projects for a user.
     *
     * @param User $user The user
     *
     * @return Project[] The archived projects
     */
    public function getArchivedProjects(User $user): array
    {
        return $this->projectRepository->findArchivedByOwner($user);
    }

    /**
     * Validate and get a parent project.
     *
     * @param User         $user     The user
     * @param string       $parentId The parent ID to validate
     * @param Project|null $project  The project being moved (to check for circular refs)
     *
     * @return Project The validated parent project
     */
    private function validateAndGetParent(User $user, string $parentId, ?Project $project = null): Project
    {
        $parent = $this->projectRepository->findOneByOwnerAndId($user, $parentId);

        if ($parent === null) {
            throw ProjectParentNotFoundException::create($parentId);
        }

        if ($parent->isArchived()) {
            throw ProjectMoveToArchivedException::create($project?->getId() ?? '', $parentId);
        }

        // Check for circular reference if we're moving an existing project
        if ($project !== null && $project->getId() !== null) {
            if ($parent->getId() === $project->getId()) {
                throw ProjectMoveToDescendantException::create($project->getId(), $parentId);
            }

            // Use database CTE query for reliable circular reference detection
            // This ensures we detect cycles even if relationships aren't fully loaded in memory
            $descendantIds = $this->projectRepository->getDescendantIds($project);
            if (in_array($parent->getId(), $descendantIds, true)) {
                throw ProjectMoveToDescendantException::create($project->getId(), $parentId);
            }
        }

        return $parent;
    }
}
