<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for batch operations container.
 */
final class BatchOperationsRequest
{
    public const MAX_OPERATIONS = 100;

    /**
     * @param BatchOperationRequest[] $operations
     */
    public function __construct(
        #[Assert\NotBlank(message: 'Operations array is required')]
        #[Assert\Count(
            min: 1,
            max: self::MAX_OPERATIONS,
            minMessage: 'At least one operation is required',
            maxMessage: 'Maximum {{ limit }} operations allowed per batch'
        )]
        #[Assert\Valid]
        public readonly array $operations,

        /**
         * Whether to run in atomic mode (rollback on any failure).
         */
        public readonly bool $atomic = false,
    ) {
    }

    /**
     * Creates a BatchOperationsRequest from an array.
     *
     * @param array<string, mixed> $data
     * @param bool $atomic Whether to run atomically
     */
    public static function fromArray(array $data, bool $atomic = false): self
    {
        $operations = [];

        if (isset($data['operations']) && is_array($data['operations'])) {
            foreach ($data['operations'] as $opData) {
                if (is_array($opData)) {
                    $operations[] = BatchOperationRequest::fromArray($opData);
                }
            }
        }

        return new self(
            operations: $operations,
            atomic: $atomic,
        );
    }

    /**
     * Validates all operations in the batch.
     *
     * @return array<int, array<string, string>> Array of errors per operation index
     */
    public function validateOperations(): array
    {
        $errors = [];

        foreach ($this->operations as $index => $operation) {
            $opErrors = $operation->validateRequirements();
            if (!empty($opErrors)) {
                $errors[$index] = $opErrors;
            }
        }

        return $errors;
    }
}
