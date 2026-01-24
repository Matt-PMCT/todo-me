<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Result DTO for a single batch operation.
 */
final class BatchOperationResult
{
    public function __construct(
        public readonly int $index,
        public readonly string $action,
        public readonly bool $success,
        public readonly ?string $taskId = null,
        public readonly ?string $error = null,
        public readonly ?string $errorCode = null,
    ) {
    }

    /**
     * Creates a successful result.
     */
    public static function success(int $index, string $action, string $taskId): self
    {
        return new self(
            index: $index,
            action: $action,
            success: true,
            taskId: $taskId,
        );
    }

    /**
     * Creates a failed result.
     */
    public static function failure(int $index, string $action, string $error, string $errorCode, ?string $taskId = null): self
    {
        return new self(
            index: $index,
            action: $action,
            success: false,
            taskId: $taskId,
            error: $error,
            errorCode: $errorCode,
        );
    }

    /**
     * Converts to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'index' => $this->index,
            'action' => $this->action,
            'success' => $this->success,
        ];

        if ($this->taskId !== null) {
            $result['taskId'] = $this->taskId;
        }

        if (!$this->success) {
            $result['error'] = $this->error;
            $result['errorCode'] = $this->errorCode;
        }

        return $result;
    }
}
