<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Exception thrown when a batch operation exceeds the maximum allowed size.
 */
final class BatchSizeLimitExceededException extends HttpException
{
    public readonly string $errorCode;

    public function __construct(
        public readonly int $size,
        public readonly int $maxSize,
        ?\Throwable $previous = null,
    ) {
        $this->errorCode = 'BATCH_SIZE_LIMIT_EXCEEDED';

        parent::__construct(
            statusCode: 422,
            message: sprintf(
                'Batch operation size %d exceeds the maximum allowed size of %d',
                $size,
                $maxSize
            ),
            previous: $previous,
        );
    }

    public static function create(int $size, int $maxSize): self
    {
        return new self($size, $maxSize);
    }
}
