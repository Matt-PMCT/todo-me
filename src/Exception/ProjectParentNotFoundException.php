<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when the specified parent project does not exist.
 */
final class ProjectParentNotFoundException extends HttpException
{
    public readonly string $errorCode;

    public function __construct(
        public readonly string $parentId,
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'PROJECT_PARENT_NOT_FOUND';

        parent::__construct(
            statusCode: 422,
            message: sprintf('Parent project with ID "%s" not found', $parentId),
            previous: $previous,
        );
    }

    public static function create(string $parentId): self
    {
        return new self($parentId);
    }
}
