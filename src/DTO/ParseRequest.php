<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Request DTO for parsing natural language task input.
 */
final class ParseRequest
{
    public function __construct(
        public readonly string $input,
    ) {
    }

    /**
     * Creates a ParseRequest from an array.
     *
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['input']) || !is_string($data['input'])) {
            throw new \InvalidArgumentException('input is required and must be a string');
        }

        return new self(input: $data['input']);
    }
}
