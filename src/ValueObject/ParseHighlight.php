<?php

declare(strict_types=1);

namespace App\ValueObject;

/**
 * Immutable value object representing a highlighted portion of parsed text.
 *
 * This is used to indicate which parts of the input text were recognized
 * as metadata (date, project, tag, priority) during natural language parsing.
 */
final readonly class ParseHighlight
{
    private function __construct(
        public string $type,
        public string $text,
        public int $startPosition,
        public int $endPosition,
        public mixed $value,
        public bool $valid,
    ) {
    }

    /**
     * Create a new ParseHighlight.
     *
     * @param string $type The type of highlight ('date', 'project', 'tag', 'priority')
     * @param string $text The matched text from the input
     * @param int $startPosition The start position in the input string
     * @param int $endPosition The end position in the input string
     * @param mixed $value The parsed value (depends on type)
     * @param bool $valid Whether the parsed value is valid (false for invalid priority, not-found project, etc.)
     */
    public static function create(
        string $type,
        string $text,
        int $startPosition,
        int $endPosition,
        mixed $value,
        bool $valid = true,
    ): self {
        return new self(
            type: $type,
            text: $text,
            startPosition: $startPosition,
            endPosition: $endPosition,
            value: $value,
            valid: $valid,
        );
    }

    /**
     * Serialize the result to an array.
     *
     * @return array{
     *     type: string,
     *     text: string,
     *     startPosition: int,
     *     endPosition: int,
     *     value: mixed,
     *     valid: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'text' => $this->text,
            'startPosition' => $this->startPosition,
            'endPosition' => $this->endPosition,
            'value' => $this->value,
            'valid' => $this->valid,
        ];
    }

    /**
     * Deserialize a result from an array.
     *
     * @param array{
     *     type: string,
     *     text: string,
     *     startPosition: int,
     *     endPosition: int,
     *     value: mixed,
     *     valid: bool
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $requiredKeys = ['type', 'text', 'startPosition', 'endPosition', 'value', 'valid'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException(sprintf('Missing required key "%s" in parse highlight data', $key));
            }
        }

        return new self(
            type: $data['type'],
            text: $data['text'],
            startPosition: $data['startPosition'],
            endPosition: $data['endPosition'],
            value: $data['value'],
            valid: $data['valid'],
        );
    }

    /**
     * Get the length of the matched text.
     */
    public function getLength(): int
    {
        return $this->endPosition - $this->startPosition;
    }
}
