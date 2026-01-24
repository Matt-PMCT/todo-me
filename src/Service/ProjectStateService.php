<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use Psr\Log\LoggerInterface;

/**
 * Service for project state serialization and deserialization.
 *
 * Handles converting project entities to/from serializable array representations
 * for undo operations and state restoration.
 *
 * @internal Used by ProjectService and ProjectUndoService for undo operations
 */
final class ProjectStateService
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly LoggerInterface $logger,
    ) {
    }
    /**
     * Serializes a project state for undo operations.
     *
     * @param Project $project The project to serialize
     * @return array<string, mixed> The serialized state
     */
    public function serializeProjectState(Project $project): array
    {
        return [
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'isArchived' => $project->isArchived(),
            'archivedAt' => $project->getArchivedAt()?->format(\DateTimeInterface::ATOM),
            'deletedAt' => $project->getDeletedAt()?->format(\DateTimeInterface::ATOM),
            // Phase 3 additions:
            'parentId' => $project->getParent()?->getId(),
            'position' => $project->getPosition(),
            'showChildrenTasks' => $project->isShowChildrenTasks(),
        ];
    }

    /**
     * Serializes only archive-related state for archive/unarchive undo.
     *
     * @param Project $project The project
     * @return array<string, mixed> The archive-related state
     */
    public function serializeArchiveState(Project $project): array
    {
        return [
            'isArchived' => $project->isArchived(),
            'archivedAt' => $project->getArchivedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Applies a serialized state to an existing project.
     *
     * @param Project $project The project to update
     * @param array<string, mixed> $state The state to apply
     */
    public function applyStateToProject(Project $project, array $state): void
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

        if (array_key_exists('deletedAt', $state)) {
            $project->setDeletedAt(
                $state['deletedAt'] !== null
                    ? new \DateTimeImmutable($state['deletedAt'])
                    : null
            );
        }

        // Phase 3 additions:
        if (array_key_exists('parentId', $state)) {
            if ($state['parentId'] === null) {
                $project->setParent(null);
            } else {
                $parent = $this->projectRepository->findOneByOwnerAndId(
                    $project->getOwner(),
                    $state['parentId']
                );
                if ($parent !== null && !$parent->isDeleted() && !$parent->isArchived()) {
                    $project->setParent($parent);
                } else {
                    $this->logger->warning('Cannot restore parent project during undo: parent not found, deleted, or archived', [
                        'projectId' => $project->getId(),
                        'parentId' => $state['parentId'],
                        'parentExists' => $parent !== null,
                        'parentDeleted' => $parent?->isDeleted(),
                        'parentArchived' => $parent?->isArchived(),
                    ]);
                }
            }
        }

        if (array_key_exists('position', $state)) {
            $project->setPosition($state['position']);
        }

        if (array_key_exists('showChildrenTasks', $state)) {
            $project->setShowChildrenTasks($state['showChildrenTasks']);
        }
    }
}
