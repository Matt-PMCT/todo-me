<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Entity\User;
use App\Enum\UndoAction;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidStateException;
use App\Exception\InvalidUndoTokenException;
use App\Repository\ProjectRepository;
use App\ValueObject\UndoToken;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for project undo operations.
 *
 * Handles undo token creation and consumption for project operations,
 * including delete, update, and archive operations.
 */
final class ProjectUndoService
{
    private const ENTITY_TYPE = 'project';

    public function __construct(
        private readonly UndoService $undoService,
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectStateService $projectStateService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Creates an undo token for a project update operation.
     *
     * @param Project $project The project being updated
     * @param array<string, mixed> $previousState The state before the update
     * @return UndoToken|null The undo token, or null if creation failed
     * @throws InvalidStateException If project has no owner or ID
     */
    public function createUpdateUndoToken(Project $project, array $previousState): ?UndoToken
    {
        $ownerId = $project->getOwner()?->getId();
        $projectId = $project->getId();

        if ($ownerId === null) {
            throw InvalidStateException::missingOwner('Project');
        }
        if ($projectId === null) {
            throw InvalidStateException::missingRequiredId('Project');
        }

        return $this->undoService->createUndoToken(
            userId: $ownerId,
            action: UndoAction::UPDATE->value,
            entityType: self::ENTITY_TYPE,
            entityId: $projectId,
            previousState: $previousState,
        );
    }

    /**
     * Creates an undo token for a project delete operation.
     *
     * @param Project $project The project being deleted
     * @return UndoToken|null The undo token, or null if creation failed
     * @throws InvalidStateException If project has no owner or ID
     */
    public function createDeleteUndoToken(Project $project): ?UndoToken
    {
        $ownerId = $project->getOwner()?->getId();
        $projectId = $project->getId();

        if ($ownerId === null) {
            throw InvalidStateException::missingOwner('Project');
        }
        if ($projectId === null) {
            throw InvalidStateException::missingRequiredId('Project');
        }

        // Store full state for undo
        $previousState = $this->projectStateService->serializeProjectState($project);

        return $this->undoService->createUndoToken(
            userId: $ownerId,
            action: UndoAction::DELETE->value,
            entityType: self::ENTITY_TYPE,
            entityId: $projectId,
            previousState: $previousState,
        );
    }

    /**
     * Creates an undo token for a project archive/unarchive operation.
     *
     * @param Project $project The project being archived/unarchived
     * @param array<string, mixed> $previousState The archive state before the change
     * @return UndoToken|null The undo token, or null if creation failed
     * @throws InvalidStateException If project has no owner or ID
     */
    public function createArchiveUndoToken(Project $project, array $previousState): ?UndoToken
    {
        $ownerId = $project->getOwner()?->getId();
        $projectId = $project->getId();

        if ($ownerId === null) {
            throw InvalidStateException::missingOwner('Project');
        }
        if ($projectId === null) {
            throw InvalidStateException::missingRequiredId('Project');
        }

        return $this->undoService->createUndoToken(
            userId: $ownerId,
            action: UndoAction::ARCHIVE->value,
            entityType: self::ENTITY_TYPE,
            entityId: $projectId,
            previousState: $previousState,
        );
    }

    /**
     * Undoes any project operation (generic handler for all undo types).
     *
     * Uses consume-then-validate pattern to avoid race conditions. The token
     * is atomically consumed first, then validated.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return array{project: Project, action: string, message: string, warning: string|null}
     * @throws InvalidUndoTokenException If the token is invalid or expired
     * @throws EntityNotFoundException If the project no longer exists (for non-delete operations)
     */
    public function undo(User $user, string $token): array
    {
        // Atomically consume the token first to prevent race conditions
        $undoToken = $this->undoService->consumeUndoToken($user->getId() ?? '', $token);

        if ($undoToken === null) {
            throw InvalidUndoTokenException::expired();
        }

        // Validate entity type after consumption
        if ($undoToken->entityType !== self::ENTITY_TYPE) {
            throw InvalidUndoTokenException::wrongEntityType(self::ENTITY_TYPE, $undoToken->entityType);
        }

        $warning = null;

        $result = match ($undoToken->action) {
            UndoAction::UPDATE->value => [
                'project' => $this->performUndoExisting($user, $undoToken),
                'message' => 'Update operation undone successfully',
            ],
            UndoAction::ARCHIVE->value => [
                'project' => $this->performUndoExisting($user, $undoToken),
                'message' => $this->getArchiveUndoMessage($undoToken),
            ],
            UndoAction::DELETE->value => [
                'project' => $this->performUndoDelete($user, $undoToken),
                'message' => 'Delete operation undone successfully. Project and all associated tasks have been restored.',
            ],
            default => throw InvalidUndoTokenException::unknownAction($undoToken->action),
        };

        return [
            'project' => $result['project'],
            'action' => $undoToken->action,
            'message' => $result['message'],
            'warning' => $warning,
        ];
    }

    /**
     * Undoes an archive/unarchive operation.
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Project The restored project
     * @throws InvalidUndoTokenException If the token is invalid or expired
     * @throws EntityNotFoundException If the project no longer exists
     */
    public function undoArchive(User $user, string $token): Project
    {
        $undoToken = $this->undoService->consumeUndoToken($user->getId() ?? '', $token);

        if ($undoToken === null) {
            throw InvalidUndoTokenException::expired();
        }

        if ($undoToken->entityType !== self::ENTITY_TYPE) {
            throw InvalidUndoTokenException::wrongEntityType(self::ENTITY_TYPE, $undoToken->entityType);
        }

        if ($undoToken->action !== UndoAction::ARCHIVE->value) {
            throw InvalidUndoTokenException::wrongActionType('archive');
        }

        return $this->performUndoExisting($user, $undoToken);
    }

    /**
     * Undoes a delete operation (restore soft-deleted project).
     *
     * @param User $user The user performing the undo
     * @param string $token The undo token
     * @return Project The restored project
     * @throws InvalidUndoTokenException If the token is invalid or expired
     * @throws EntityNotFoundException If the project was permanently deleted
     */
    public function undoDelete(User $user, string $token): Project
    {
        $undoToken = $this->undoService->consumeUndoToken($user->getId() ?? '', $token);

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
     * @return Project The restored project
     * @throws InvalidUndoTokenException If the token is invalid or expired
     * @throws EntityNotFoundException If the project no longer exists
     */
    public function undoUpdate(User $user, string $token): Project
    {
        $undoToken = $this->undoService->consumeUndoToken($user->getId() ?? '', $token);

        if ($undoToken === null) {
            throw InvalidUndoTokenException::expired();
        }

        if ($undoToken->entityType !== self::ENTITY_TYPE) {
            throw InvalidUndoTokenException::wrongEntityType(self::ENTITY_TYPE, $undoToken->entityType);
        }

        if ($undoToken->action !== UndoAction::UPDATE->value) {
            throw InvalidUndoTokenException::wrongActionType('update');
        }

        return $this->performUndoExisting($user, $undoToken);
    }

    /**
     * Perform undo on an existing project (update or archive operations).
     */
    private function performUndoExisting(User $user, UndoToken $undoToken): Project
    {
        $project = $this->projectRepository->findOneByOwnerAndId($user, $undoToken->entityId);

        if ($project === null) {
            throw EntityNotFoundException::project($undoToken->entityId);
        }

        $this->projectStateService->applyStateToProject($project, $undoToken->previousState);
        $this->entityManager->flush();

        return $project;
    }

    /**
     * Perform the actual delete undo using a consumed token.
     *
     * Restores a soft-deleted project by clearing its deletedAt timestamp.
     */
    private function performUndoDelete(User $user, UndoToken $undoToken): Project
    {
        // Find the soft-deleted project
        $project = $this->projectRepository->findOneByOwnerAndId(
            $user,
            $undoToken->entityId,
            includeDeleted: true
        );

        if ($project === null) {
            throw EntityNotFoundException::project($undoToken->entityId);
        }

        // Restore the project
        $project->restore();
        $this->entityManager->flush();

        return $project;
    }

    /**
     * Get the appropriate message for archive undo based on previous state.
     */
    private function getArchiveUndoMessage(UndoToken $undoToken): string
    {
        $wasArchived = array_key_exists('isArchived', $undoToken->previousState)
            ? $undoToken->previousState['isArchived']
            : false;

        return $wasArchived
            ? 'Project archived again (undo of unarchive)'
            : 'Project unarchived (undo of archive)';
    }
}
