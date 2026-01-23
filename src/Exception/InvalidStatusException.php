<?php

declare(strict_types=1);

namespace App\Exception;

use App\Entity\Task;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when an invalid task status is provided.
 */
final class InvalidStatusException extends HttpException
{
    public readonly string $errorCode;

    /**
     * @param string $invalidStatus The invalid status value
     * @param array<string> $validStatuses List of valid status values
     */
    public function __construct(
        public readonly string $invalidStatus,
        public readonly array $validStatuses = [],
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'INVALID_STATUS';

        $message = sprintf('Invalid status "%s"', $invalidStatus);

        if (!empty($validStatuses)) {
            $message .= sprintf('. Valid statuses are: %s', implode(', ', $validStatuses));
        }

        parent::__construct(
            statusCode: 400,
            message: $message,
            previous: $previous,
        );
    }

    /**
     * Creates an exception with the default valid task statuses.
     */
    public static function forTaskStatus(string $invalidStatus): self
    {
        return new self($invalidStatus, Task::STATUSES);
    }
}
