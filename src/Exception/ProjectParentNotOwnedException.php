<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when attempting to use a parent project owned by a different user.
 */
final class ProjectParentNotOwnedException extends HttpException
{
    public readonly string $errorCode;

    public function __construct(
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'PROJECT_PARENT_NOT_OWNED_BY_USER';

        parent::__construct(
            statusCode: 403,
            message: 'You do not have permission to access the specified parent project',
            previous: $previous,
        );
    }

    public static function create(string $parentId): self
    {
        return new self();
    }
}
