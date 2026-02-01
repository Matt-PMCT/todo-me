<?php

declare(strict_types=1);

namespace App\Service\Parser;

use App\ValueObject\PriorityParseResult;

class PriorityParserService
{
    private const int MAX_VALID_PRIORITY = 5;

    /**
     * Parse a priority from the input string.
     *
     * Looks for patterns like "p1", "p2", "P3", etc. with word boundaries.
     * Returns the FIRST priority found.
     *
     * Valid priorities: p1 (highest) through p5 (lowest)
     * Invalid priorities (p0, p6, p10, etc.): returns result with valid: false
     *
     * @param string $input The text to parse
     *
     * @return PriorityParseResult|null The parse result, or null if no priority pattern found
     */
    public function parse(string $input): ?PriorityParseResult
    {
        // Match priority pattern with word boundaries (case insensitive)
        // \b ensures we don't match "help1" or "p1a"
        if (preg_match('/\bp(\d+)\b/i', $input, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $fullMatch = $matches[0][0];
        $startPosition = $matches[0][1];
        $endPosition = $startPosition + strlen($fullMatch);
        $priorityValue = (int) $matches[1][0];

        $valid = $priorityValue >= 1 && $priorityValue <= self::MAX_VALID_PRIORITY;

        return PriorityParseResult::create(
            priority: $priorityValue,
            originalText: $fullMatch,
            startPosition: $startPosition,
            endPosition: $endPosition,
            valid: $valid,
        );
    }
}
