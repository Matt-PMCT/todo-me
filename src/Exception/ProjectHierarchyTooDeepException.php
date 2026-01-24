<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when a project hierarchy would exceed the maximum allowed depth.
 */
final class ProjectHierarchyTooDeepException extends HttpException
{
    public readonly string $errorCode;

    public function __construct(
        public readonly string $projectId,
        public readonly int $maxDepth,
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'PROJECT_HIERARCHY_TOO_DEEP';

        parent::__construct(
            statusCode: 422,
            message: sprintf(
                'Cannot set parent for project "%s" because it would exceed the maximum hierarchy depth of %d',
                $projectId,
                $maxDepth
            ),
            previous: $previous,
        );
    }

    public static function create(string $projectId, int $maxDepth): self
    {
        return new self($projectId, $maxDepth);
    }
}
