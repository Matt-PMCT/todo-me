<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when a project hierarchy operation would create a circular reference.
 */
final class ProjectCircularReferenceException extends HttpException
{
    public readonly string $errorCode;

    public function __construct(
        public readonly string $projectId,
        public readonly string $targetParentId,
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'PROJECT_CIRCULAR_REFERENCE';

        parent::__construct(
            statusCode: 422,
            message: sprintf(
                'Cannot set project "%s" as parent of project "%s" because it would create a circular reference',
                $targetParentId,
                $projectId
            ),
            previous: $previous,
        );
    }

    public static function create(string $projectId, string $targetParentId): self
    {
        return new self($projectId, $targetParentId);
    }
}
