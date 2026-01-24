<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Entity\Project;
use App\Entity\Tag;
use DateTimeImmutable;

/**
 * Immutable value object representing the complete result of parsing task input.
 *
 * Contains all extracted components from natural language task input:
 * - Cleaned title (with metadata removed)
 * - Due date and time
 * - Project reference
 * - Tags
 * - Priority
 * - Highlights showing what was parsed
 * - Warnings for issues like duplicate values
 */
final readonly class TaskParseResult
{
    /**
     * @param string $title The cleaned title with metadata removed
     * @param DateTimeImmutable|null $dueDate The parsed due date
     * @param string|null $dueTime The parsed time portion (e.g., "14:00")
     * @param Project|null $project The matched project entity
     * @param Tag[] $tags Array of matched/created Tag entities
     * @param int|null $priority The parsed priority (0-4)
     * @param ParseHighlight[] $highlights Array of highlights showing parsed portions
     * @param string[] $warnings Array of warning messages for issues
     */
    private function __construct(
        public string $title,
        public ?DateTimeImmutable $dueDate,
        public ?string $dueTime,
        public ?Project $project,
        public array $tags,
        public ?int $priority,
        public array $highlights,
        public array $warnings,
    ) {
    }

    /**
     * Create a new TaskParseResult.
     *
     * @param string $title The cleaned title with metadata removed
     * @param DateTimeImmutable|null $dueDate The parsed due date
     * @param string|null $dueTime The parsed time portion
     * @param Project|null $project The matched project entity
     * @param Tag[] $tags Array of matched/created Tag entities
     * @param int|null $priority The parsed priority (0-4)
     * @param ParseHighlight[] $highlights Array of highlights showing parsed portions
     * @param string[] $warnings Array of warning messages for issues
     */
    public static function create(
        string $title,
        ?DateTimeImmutable $dueDate = null,
        ?string $dueTime = null,
        ?Project $project = null,
        array $tags = [],
        ?int $priority = null,
        array $highlights = [],
        array $warnings = [],
    ): self {
        return new self(
            title: $title,
            dueDate: $dueDate,
            dueTime: $dueTime,
            project: $project,
            tags: $tags,
            priority: $priority,
            highlights: $highlights,
            warnings: $warnings,
        );
    }

    /**
     * Check if any metadata was parsed.
     */
    public function hasMetadata(): bool
    {
        return $this->dueDate !== null
            || $this->project !== null
            || count($this->tags) > 0
            || $this->priority !== null;
    }

    /**
     * Check if there are any warnings.
     */
    public function hasWarnings(): bool
    {
        return count($this->warnings) > 0;
    }

    /**
     * Serialize the result to an array.
     *
     * @return array{
     *     title: string,
     *     dueDate: string|null,
     *     dueTime: string|null,
     *     project: array{id: string, name: string}|null,
     *     tags: array<array{id: string, name: string, color: string}>,
     *     priority: int|null,
     *     highlights: array<array{type: string, text: string, startPosition: int, endPosition: int, value: mixed, valid: bool}>,
     *     warnings: string[]
     * }
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'dueDate' => $this->dueDate?->format('Y-m-d'),
            'dueTime' => $this->dueTime,
            'project' => $this->project !== null ? [
                'id' => $this->project->getId(),
                'name' => $this->project->getName(),
            ] : null,
            'tags' => array_map(
                fn(Tag $tag) => [
                    'id' => $tag->getId(),
                    'name' => $tag->getName(),
                    'color' => $tag->getColor(),
                ],
                $this->tags
            ),
            'priority' => $this->priority,
            'highlights' => array_map(
                fn(ParseHighlight $h) => $h->toArray(),
                $this->highlights
            ),
            'warnings' => $this->warnings,
        ];
    }
}
