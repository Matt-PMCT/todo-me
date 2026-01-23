<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when an entity is not found.
 */
final class EntityNotFoundException extends HttpException
{
    public readonly string $errorCode;

    /**
     * @param string $entityType The type of entity (e.g., 'Task', 'Project')
     * @param string $entityId The ID that was not found
     */
    public function __construct(
        public readonly string $entityType,
        public readonly string $entityId,
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'NOT_FOUND';

        parent::__construct(
            statusCode: 404,
            message: sprintf('%s with ID "%s" not found', $entityType, $entityId),
            previous: $previous,
        );
    }

    /**
     * Creates an exception for a Task entity.
     */
    public static function task(string $id): self
    {
        return new self('Task', $id);
    }

    /**
     * Creates an exception for a Project entity.
     */
    public static function project(string $id): self
    {
        return new self('Project', $id);
    }

    /**
     * Creates an exception for a Tag entity.
     */
    public static function tag(string $id): self
    {
        return new self('Tag', $id);
    }

    /**
     * Creates an exception for a User entity.
     */
    public static function user(string $id): self
    {
        return new self('User', $id);
    }
}
