<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Result DTO for batch operations.
 */
final class BatchResult
{
    /**
     * @param BatchOperationResult[] $results
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $results,
        public readonly int $totalOperations,
        public readonly int $successfulOperations,
        public readonly int $failedOperations,
        public readonly ?string $undoToken = null,
    ) {
    }

    /**
     * Creates a BatchResult from operation results.
     *
     * @param BatchOperationResult[] $results
     */
    public static function fromResults(array $results, ?string $undoToken = null): self
    {
        $successCount = 0;
        $failCount = 0;

        foreach ($results as $result) {
            if ($result->success) {
                $successCount++;
            } else {
                $failCount++;
            }
        }

        return new self(
            success: $failCount === 0,
            results: $results,
            totalOperations: count($results),
            successfulOperations: $successCount,
            failedOperations: $failCount,
            undoToken: $undoToken,
        );
    }

    /**
     * Converts to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'success' => $this->success,
            'totalOperations' => $this->totalOperations,
            'successfulOperations' => $this->successfulOperations,
            'failedOperations' => $this->failedOperations,
            'results' => array_map(fn($r) => $r->toArray(), $this->results),
        ];

        if ($this->undoToken !== null) {
            $data['undoToken'] = $this->undoToken;
        }

        return $data;
    }
}
