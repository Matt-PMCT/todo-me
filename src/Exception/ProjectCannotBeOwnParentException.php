<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when attempting to set a project as its own parent.
 */
final class ProjectCannotBeOwnParentException extends HttpException
{
    public readonly string $errorCode;

    public function __construct(
        public readonly string $projectId,
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'PROJECT_CANNOT_BE_OWN_PARENT';

        parent::__construct(
            statusCode: 422,
            message: sprintf('Project "%s" cannot be its own parent', $projectId),
            previous: $previous,
        );
    }

    public static function create(string $projectId): self
    {
        return new self($projectId);
    }
}
