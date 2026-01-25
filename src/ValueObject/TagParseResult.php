<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Entity\Tag;

/**
 * Immutable value object representing a parsed tag from natural language input.
 */
final readonly class TagParseResult
{
    private function __construct(
        public ?Tag $tag,
        public string $originalText,
        public int $startPosition,
        public int $endPosition,
        public bool $wasCreated,
    ) {
    }

    /**
     * Create a new TagParseResult.
     *
     * @param Tag|null $tag           The tag entity (null if parsing failed)
     * @param string   $originalText  The original matched text (e.g., "@urgent")
     * @param int      $startPosition The start position in the input string
     * @param int      $endPosition   The end position in the input string
     * @param bool     $wasCreated    Whether the tag was newly created
     */
    public static function create(
        ?Tag $tag,
        string $originalText,
        int $startPosition,
        int $endPosition,
        bool $wasCreated,
    ): self {
        return new self(
            tag: $tag,
            originalText: $originalText,
            startPosition: $startPosition,
            endPosition: $endPosition,
            wasCreated: $wasCreated,
        );
    }

    /**
     * Serialize the result to an array.
     *
     * @return array{
     *     tagId: string|null,
     *     tagName: string|null,
     *     tagColor: string|null,
     *     originalText: string,
     *     startPosition: int,
     *     endPosition: int,
     *     wasCreated: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'tagId' => $this->tag?->getId(),
            'tagName' => $this->tag?->getName(),
            'tagColor' => $this->tag?->getColor(),
            'originalText' => $this->originalText,
            'startPosition' => $this->startPosition,
            'endPosition' => $this->endPosition,
            'wasCreated' => $this->wasCreated,
        ];
    }

    /**
     * Deserialize a result from an array.
     *
     * Note: This method creates a TagParseResult without a Tag entity.
     * Use this only for data transfer purposes where the Tag entity is not needed.
     *
     * @param array{
     *     originalText: string,
     *     startPosition: int,
     *     endPosition: int,
     *     wasCreated: bool,
     *     tagId?: string|null,
     *     tagName?: string|null,
     *     tagColor?: string|null
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $requiredKeys = ['originalText', 'startPosition', 'endPosition', 'wasCreated'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException(sprintf('Missing required key "%s" in tag parse result data', $key));
            }
        }

        return new self(
            tag: null,
            originalText: $data['originalText'],
            startPosition: $data['startPosition'],
            endPosition: $data['endPosition'],
            wasCreated: $data['wasCreated'],
        );
    }

    /**
     * Check if the parse was successful (has a tag).
     */
    public function isSuccessful(): bool
    {
        return $this->tag !== null;
    }

    /**
     * Get the length of the matched text.
     */
    public function getLength(): int
    {
        return $this->endPosition - $this->startPosition;
    }
}
