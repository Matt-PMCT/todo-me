<?php

declare(strict_types=1);

namespace App\Service\Parser;

use App\Entity\User;
use App\ValueObject\ParseHighlight;
use App\ValueObject\TaskParseResult;

/**
 * Service that combines all individual parsers to extract task components from natural language input.
 *
 * Parsing rules:
 * - FIRST WINS for dates, projects, and priorities - if multiple found, use first and add warning
 * - ALL tags are collected (duplicates ignored by TagParserService)
 * - Independent parsing - failed components don't block others
 * - Title extraction - remove all matched metadata spans from input, trim/collapse whitespace
 */
class NaturalLanguageParserService
{
    public function __construct(
        private readonly DateParserService $dateParser,
        private readonly ProjectParserService $projectParser,
        private readonly TagParserService $tagParser,
        private readonly PriorityParserService $priorityParser,
    ) {
    }

    /**
     * Configure user settings before parsing.
     *
     * Sets timezone, date format, and start of week preferences on the date parser.
     */
    public function configure(User $user): self
    {
        $this->dateParser
            ->setUserTimezone($user->getTimezone())
            ->setDateFormat($user->getDateFormat())
            ->setStartOfWeek($user->getStartOfWeek());

        return $this;
    }

    /**
     * Parse input text into task components.
     *
     * @param string $input The raw input text to parse
     * @param User $user The user context for finding projects, creating tags, etc.
     * @return TaskParseResult The parsed result with all components
     */
    public function parse(string $input, User $user): TaskParseResult
    {
        $highlights = [];
        $warnings = [];

        // Track spans to remove from title
        $spansToRemove = [];

        // Parse dates (FIRST WINS)
        $dateResult = $this->dateParser->parse($input);
        $dueDate = null;
        $dueTime = null;

        if ($dateResult !== null) {
            $dueDate = $dateResult->date;
            $dueTime = $dateResult->time;

            $highlights[] = ParseHighlight::create(
                type: 'date',
                text: $dateResult->originalText,
                startPosition: $dateResult->startPosition,
                endPosition: $dateResult->endPosition,
                value: $dueDate?->format(\DateTimeInterface::ATOM),
                valid: true,
            );

            $spansToRemove[] = [$dateResult->startPosition, $dateResult->endPosition];

            // Check for additional dates and warn
            $remainingInput = $this->maskSpan($input, $dateResult->startPosition, $dateResult->endPosition);
            $additionalDate = $this->dateParser->parse($remainingInput);
            if ($additionalDate !== null) {
                $warnings[] = 'Multiple dates found; using first: "' . $dateResult->originalText . '"';
            }
        }

        // Parse projects (FIRST WINS)
        $projectResult = $this->projectParser->parse($input, $user);
        $project = null;

        if ($projectResult !== null) {
            $project = $projectResult->project;

            $highlights[] = ParseHighlight::create(
                type: 'project',
                text: $projectResult->getMatchedText(),
                startPosition: $projectResult->startPosition,
                endPosition: $projectResult->endPosition,
                value: $projectResult->matchedName,
                valid: $projectResult->found,
            );

            $spansToRemove[] = [$projectResult->startPosition, $projectResult->endPosition];

            if (!$projectResult->found) {
                $warnings[] = 'Project not found: "' . $projectResult->matchedName . '"';
            }

            // Check for additional project references and warn
            $remainingInput = $this->maskSpan($input, $projectResult->startPosition, $projectResult->endPosition);
            $additionalProject = $this->projectParser->parse($remainingInput, $user);
            if ($additionalProject !== null) {
                $warnings[] = 'Multiple projects found; using first: "#' . $projectResult->matchedName . '"';
            }
        }

        // Parse tags (ALL collected)
        $tagResults = $this->tagParser->parse($input, $user);
        $tags = [];

        foreach ($tagResults as $tagResult) {
            if ($tagResult->tag !== null) {
                $tags[] = $tagResult->tag;
            }

            $highlights[] = ParseHighlight::create(
                type: 'tag',
                text: $tagResult->originalText,
                startPosition: $tagResult->startPosition,
                endPosition: $tagResult->endPosition,
                value: $tagResult->tag?->getName(),
                valid: $tagResult->isSuccessful(),
            );

            $spansToRemove[] = [$tagResult->startPosition, $tagResult->endPosition];
        }

        // Parse priority (FIRST WINS)
        $priorityResult = $this->priorityParser->parse($input);
        $priority = null;

        if ($priorityResult !== null) {
            $priority = $priorityResult->valid ? $priorityResult->priority : null;

            $highlights[] = ParseHighlight::create(
                type: 'priority',
                text: $priorityResult->originalText,
                startPosition: $priorityResult->startPosition,
                endPosition: $priorityResult->endPosition,
                value: $priorityResult->priority,
                valid: $priorityResult->valid,
            );

            $spansToRemove[] = [$priorityResult->startPosition, $priorityResult->endPosition];

            if (!$priorityResult->valid) {
                $warnings[] = 'Invalid priority: "' . $priorityResult->originalText . '" (valid: p0-p4)';
            }

            // Check for additional priorities and warn
            $remainingInput = $this->maskSpan($input, $priorityResult->startPosition, $priorityResult->endPosition);
            $additionalPriority = $this->priorityParser->parse($remainingInput);
            if ($additionalPriority !== null) {
                $warnings[] = 'Multiple priorities found; using first: "' . $priorityResult->originalText . '"';
            }
        }

        // Extract title by removing all metadata spans
        $title = $this->extractTitle($input, $spansToRemove);

        // Sort highlights by position
        usort($highlights, fn(ParseHighlight $a, ParseHighlight $b) => $a->startPosition <=> $b->startPosition);

        return TaskParseResult::create(
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
     * Mask a span in the input string with spaces.
     *
     * This is used to prevent re-matching the same span when checking for duplicates.
     */
    private function maskSpan(string $input, int $start, int $end): string
    {
        $length = $end - $start;

        return substr($input, 0, $start) . str_repeat(' ', $length) . substr($input, $end);
    }

    /**
     * Extract the title by removing all metadata spans.
     *
     * @param string $input The original input string
     * @param array<array{0: int, 1: int}> $spans Array of [start, end] position pairs
     * @return string The cleaned title
     */
    private function extractTitle(string $input, array $spans): string
    {
        if (empty($spans)) {
            return trim($input);
        }

        // Sort spans by start position in descending order
        // This allows us to remove from end to start without affecting positions
        usort($spans, fn(array $a, array $b) => $b[0] <=> $a[0]);

        $result = $input;
        foreach ($spans as [$start, $end]) {
            $result = substr($result, 0, $start) . substr($result, $end);
        }

        // Collapse multiple spaces into one and trim
        $result = preg_replace('/\s+/', ' ', $result);

        return trim($result);
    }
}
