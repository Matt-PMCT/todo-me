<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when attempting to move a project to one of its descendants.
 */
final class ProjectMoveToDescendantException extends HttpException
{
    public readonly string $errorCode;

    public function __construct(
        public readonly string $projectId,
        public readonly string $descendantId,
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'PROJECT_MOVE_TO_DESCENDANT';

        parent::__construct(
            statusCode: 422,
            message: sprintf(
                'Cannot move project "%s" to its descendant "%s"',
                $projectId,
                $descendantId
            ),
            previous: $previous,
        );
    }

    public static function create(string $projectId, string $descendantId): self
    {
        return new self($projectId, $descendantId);
    }
}
