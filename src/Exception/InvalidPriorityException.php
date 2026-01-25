<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Task;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when an invalid task priority is provided.
 */
final class InvalidPriorityException extends HttpException
{
    public readonly string $errorCode;

    /**
     * @param int $invalidPriority The invalid priority value
     * @param int $minPriority     The minimum valid priority
     * @param int $maxPriority     The maximum valid priority
     */
    public function __construct(
        public readonly int $invalidPriority,
        public readonly int $minPriority = Task::PRIORITY_MIN,
        public readonly int $maxPriority = Task::PRIORITY_MAX,
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'INVALID_PRIORITY';

        $message = sprintf(
            'Invalid priority %d. Priority must be between %d and %d',
            $invalidPriority,
            $minPriority,
            $maxPriority
        );

        parent::__construct(
            statusCode: 400,
            message: $message,
            previous: $previous,
        );
    }

    /**
     * Creates an exception with the default task priority range.
     */
    public static function forTaskPriority(int $invalidPriority): self
    {
        return new self($invalidPriority, Task::PRIORITY_MIN, Task::PRIORITY_MAX);
    }
}
