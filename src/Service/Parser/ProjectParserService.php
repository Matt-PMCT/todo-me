<?php

declare(strict_types=1);

namespace App\Service\Parser;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\ValueObject\ProjectParseResult;

/**
 * Service for parsing project references from natural language input.
 *
 * Supports:
 * - Simple project names: #work, #personal
 * - Nested project paths: #work/meetings, #work/reports/q1
 * - Case-insensitive matching
 */
final class ProjectParserService
{
    /**
     * Pattern to match project hashtags.
     * Supports alphanumeric names with underscores and hyphens,
     * and nested paths separated by forward slashes.
     */
    private const PATTERN = '/#([a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_-]+)*)/';

    public function __construct(
        private readonly ProjectRepository $projectRepository,
    ) {
    }

    /**
     * Parse a project reference from the input text.
     *
     * Finds the FIRST project hashtag in the input and attempts to match
     * it to a project owned by the user.
     *
     * @param string $input The input text to parse
     * @param User   $user  The user whose projects to search
     *
     * @return ProjectParseResult|null The parse result, or null if no hashtag found
     */
    public function parse(string $input, User $user): ?ProjectParseResult
    {
        if ($input === '') {
            return null;
        }

        if (preg_match(self::PATTERN, $input, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        // $matches[0] is the full match (including #)
        // $matches[1] is the captured group (without #)
        $fullMatch = $matches[0][0];
        $projectPath = $matches[1][0];
        $startPosition = (int) $matches[0][1];
        $endPosition = $startPosition + strlen($fullMatch);

        // Try to find the project
        $project = $this->findProject($user, $projectPath);

        return ProjectParseResult::create(
            project: $project,
            originalText: $input,
            startPosition: $startPosition,
            endPosition: $endPosition,
            matchedName: $projectPath,
            found: $project !== null,
        );
    }

    /**
     * Find a project by name or path (case-insensitive).
     */
    private function findProject(User $user, string $projectPath): ?Project
    {
        // Check if this is a nested path
        if (str_contains($projectPath, '/')) {
            return $this->projectRepository->findByPathInsensitive($user, $projectPath);
        }

        return $this->projectRepository->findByNameInsensitive($user, $projectPath);
    }
}
