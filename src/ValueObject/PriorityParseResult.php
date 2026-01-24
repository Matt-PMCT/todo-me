<?php

declare(strict_types=1);

namespace App\ValueObject;

final readonly class PriorityParseResult
{
    private function __construct(
        public ?int $priority,
        public string $originalText,
        public int $startPosition,
        public int $endPosition,
        public bool $valid,
    ) {
    }

    /**
     * Create a new PriorityParseResult.
     *
     * @param int|null $priority The parsed priority value (0-4), or null if invalid
     * @param string $originalText The matched text from the input
     * @param int $startPosition The start position of the match in the input
     * @param int $endPosition The end position of the match in the input
     * @param bool $valid Whether the parsed priority is valid (0-4)
     */
    public static function create(
        ?int $priority,
        string $originalText,
        int $startPosition,
        int $endPosition,
        bool $valid,
    ): self {
        return new self(
            priority: $priority,
            originalText: $originalText,
            startPosition: $startPosition,
            endPosition: $endPosition,
            valid: $valid,
        );
    }

    /**
     * Serialize the result to an array.
     *
     * @return array{
     *     priority: int|null,
     *     originalText: string,
     *     startPosition: int,
     *     endPosition: int,
     *     valid: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'priority' => $this->priority,
            'originalText' => $this->originalText,
            'startPosition' => $this->startPosition,
            'endPosition' => $this->endPosition,
            'valid' => $this->valid,
        ];
    }

    /**
     * Deserialize a result from an array.
     *
     * @param array{
     *     priority: int|null,
     *     originalText: string,
     *     startPosition: int,
     *     endPosition: int,
     *     valid: bool
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $requiredKeys = ['priority', 'originalText', 'startPosition', 'endPosition', 'valid'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException(sprintf('Missing required key "%s" in priority parse result data', $key));
            }
        }

        return new self(
            priority: $data['priority'],
            originalText: $data['originalText'],
            startPosition: $data['startPosition'],
            endPosition: $data['endPosition'],
            valid: $data['valid'],
        );
    }
}
