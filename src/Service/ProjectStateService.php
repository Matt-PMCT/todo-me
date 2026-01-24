<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;

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
    }
}
