<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when attempting to move a project to an archived parent.
 */
final class ProjectMoveToArchivedException extends HttpException
{
    public readonly string $errorCode;

    public function __construct(
        public readonly string $projectId,
        public readonly string $archivedParentId,
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'PROJECT_CANNOT_MOVE_TO_ARCHIVED_PARENT';

        parent::__construct(
            statusCode: 422,
            message: sprintf(
                'Cannot move project "%s" to archived project "%s"',
                $projectId,
                $archivedParentId
            ),
            previous: $previous,
        );
    }

    public static function create(string $projectId, string $archivedParentId): self
    {
        return new self($projectId, $archivedParentId);
    }
}
