<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Project;
use App\ValueObject\ParseHighlight;
use App\ValueObject\TaskParseResult;

/**
 * Response DTO for parsed task input.
 *
 * Converts TaskParseResult to API response format with additional fields
 * like fullPath for projects.
 */
final class ParseResponse
{
    /**
     * @param string $title The extracted title
     * @param string|null $dueDate ISO date string or null
     * @param string|null $dueTime Time string or null
     * @param bool $hasTime Whether the parsed date includes a time component
     * @param array{id: string, name: string, fullPath: string, color: string}|null $project
     * @param array<array{id: string, name: string, color: string}> $tags
     * @param int|null $priority Priority 0-4 or null
     * @param array<array{type: string, text: string, startPosition: int, endPosition: int, value: mixed, valid: bool}> $highlights
     * @param string[] $warnings
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $dueDate,
        public readonly ?string $dueTime,
        public readonly bool $hasTime,
        public readonly ?array $project,
        public readonly array $tags,
        public readonly ?int $priority,
        public readonly array $highlights,
        public readonly array $warnings,
    ) {
    }

    /**
     * Creates a ParseResponse from a TaskParseResult.
     */
    public static function fromTaskParseResult(TaskParseResult $result): self
    {
        $project = null;
        if ($result->project !== null) {
            $project = [
                'id' => $result->project->getId(),
                'name' => $result->project->getName(),
                'fullPath' => $result->project->getFullPath(),
                'color' => $result->project->getColor(),
            ];
        }

        $tags = array_map(
            fn($tag) => [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'color' => $tag->getColor(),
            ],
            $result->tags
        );

        $highlights = array_map(
            fn(ParseHighlight $h) => $h->toArray(),
            $result->highlights
        );

        return new self(
            title: $result->title,
            dueDate: $result->dueDate?->format('Y-m-d'),
            dueTime: $result->dueTime,
            hasTime: $result->dueTime !== null,
            project: $project,
            tags: $tags,
            priority: $result->priority,
            highlights: $highlights,
            warnings: $result->warnings,
        );
    }

    /**
     * Converts the response to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'due_date' => $this->dueDate,
            'due_time' => $this->dueTime,
            'has_time' => $this->hasTime,
            'project' => $this->project,
            'tags' => $this->tags,
            'priority' => $this->priority,
            'highlights' => $this->highlights,
            'warnings' => $this->warnings,
        ];
    }
}
