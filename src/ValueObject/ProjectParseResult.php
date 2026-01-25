<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Entity\Project;

/**
 * Immutable value object representing the result of parsing a project reference from text.
 */
final readonly class ProjectParseResult
{
    private function __construct(
        public ?Project $project,
        public string $originalText,
        public int $startPosition,
        public int $endPosition,
        public string $matchedName,
        public bool $found,
    ) {
    }

    /**
     * Create a new ProjectParseResult.
     *
     * @param Project|null $project       The matched project entity (null if not found)
     * @param string       $originalText  The original input text
     * @param int          $startPosition The start position of the match in the original text
     * @param int          $endPosition   The end position of the match in the original text
     * @param string       $matchedName   The matched project name/path from the hashtag
     * @param bool         $found         Whether the project was found in the database
     */
    public static function create(
        ?Project $project,
        string $originalText,
        int $startPosition,
        int $endPosition,
        string $matchedName,
        bool $found,
    ): self {
        return new self(
            project: $project,
            originalText: $originalText,
            startPosition: $startPosition,
            endPosition: $endPosition,
            matchedName: $matchedName,
            found: $found,
        );
    }

    /**
     * Serialize the result to an array.
     *
     * @return array{
     *     projectId: string|null,
     *     projectName: string|null,
     *     projectFullPath: string|null,
     *     originalText: string,
     *     startPosition: int,
     *     endPosition: int,
     *     matchedName: string,
     *     found: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'projectId' => $this->project?->getId(),
            'projectName' => $this->project?->getName(),
            'projectFullPath' => $this->project?->getFullPath(),
            'originalText' => $this->originalText,
            'startPosition' => $this->startPosition,
            'endPosition' => $this->endPosition,
            'matchedName' => $this->matchedName,
            'found' => $this->found,
        ];
    }

    /**
     * Deserialize a result from an array.
     *
     * Note: The project entity cannot be fully restored from array data alone.
     * This method is primarily useful for testing or when the project is not needed.
     *
     * @param array{
     *     projectId?: string|null,
     *     projectName?: string|null,
     *     projectFullPath?: string|null,
     *     originalText: string,
     *     startPosition: int,
     *     endPosition: int,
     *     matchedName: string,
     *     found: bool
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $requiredKeys = ['originalText', 'startPosition', 'endPosition', 'matchedName', 'found'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException(sprintf('Missing required key "%s" in parse result data', $key));
            }
        }

        return new self(
            project: null, // Cannot restore entity from array
            originalText: $data['originalText'],
            startPosition: $data['startPosition'],
            endPosition: $data['endPosition'],
            matchedName: $data['matchedName'],
            found: $data['found'],
        );
    }

    /**
     * Get the length of the matched text (including the # symbol).
     */
    public function getMatchLength(): int
    {
        return $this->endPosition - $this->startPosition;
    }

    /**
     * Get the matched text from the original input.
     */
    public function getMatchedText(): string
    {
        return substr($this->originalText, $this->startPosition, $this->getMatchLength());
    }
}
