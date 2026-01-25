<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Request DTO for creating a task from natural language input.
 */
final class NaturalLanguageTaskRequest
{
    public function __construct(
        public readonly string $inputText,
        public readonly bool $isRecurring = false,
        public readonly ?string $recurrenceRule = null,
    ) {
    }

    /**
     * Creates a NaturalLanguageTaskRequest from an array.
     *
     * @param array<string, mixed> $data
     *
     * @throws \InvalidArgumentException If input_text is missing or not a string
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['input_text']) || !is_string($data['input_text'])) {
            throw new \InvalidArgumentException('input_text is required');
        }

        return new self(
            inputText: $data['input_text'],
            isRecurring: (bool) ($data['isRecurring'] ?? false),
            recurrenceRule: isset($data['recurrenceRule']) && is_string($data['recurrenceRule'])
                ? $data['recurrenceRule']
                : null,
        );
    }
}
