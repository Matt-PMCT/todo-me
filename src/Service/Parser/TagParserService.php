<?php

declare(strict_types=1);

namespace App\Service\Parser;

use App\Entity\User;
use App\Service\TagService;
use App\ValueObject\TagParseResult;

/**
 * Service for parsing tags from natural language input.
 *
 * Recognizes tags in the format @tagname and creates/finds corresponding Tag entities.
 */
final class TagParserService
{
    private const TAG_PATTERN = '/@([a-zA-Z0-9_-]+)/';

    public function __construct(
        private readonly TagService $tagService,
    ) {
    }

    /**
     * Parse tags from input text.
     *
     * Finds all @tag patterns in the input and returns TagParseResult objects
     * for each unique tag found. Duplicates are ignored (only first occurrence is returned).
     *
     * @param string $input   The input text to parse
     * @param User   $user    The user for whom to find/create tags
     * @param bool   $preview If true, only looks up existing tags without creating new ones
     *
     * @return TagParseResult[] Array of parse results for each unique tag found
     */
    public function parse(string $input, User $user, bool $preview = false): array
    {
        if ($input === '') {
            return [];
        }

        $matches = [];
        if (!preg_match_all(self::TAG_PATTERN, $input, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $results = [];
        $seenNames = [];

        foreach ($matches[0] as $index => $match) {
            $fullMatch = $match[0]; // e.g., "@urgent"
            $startPosition = $match[1];
            $tagName = $matches[1][$index][0]; // The captured group without @
            $normalizedName = strtolower($tagName);

            // Skip duplicates
            if (isset($seenNames[$normalizedName])) {
                continue;
            }
            $seenNames[$normalizedName] = true;

            if ($preview) {
                // Preview mode: only look up existing tags, don't create new ones
                $tag = $this->tagService->findByName($user, $tagName);
                $results[] = TagParseResult::create(
                    tag: $tag,
                    originalText: $fullMatch,
                    startPosition: $startPosition,
                    endPosition: $startPosition + strlen($fullMatch),
                    wasCreated: false,
                );
            } else {
                // Normal mode: find or create the tag
                $findResult = $this->tagService->findOrCreate($user, $tagName);
                $results[] = TagParseResult::create(
                    tag: $findResult['tag'],
                    originalText: $fullMatch,
                    startPosition: $startPosition,
                    endPosition: $startPosition + strlen($fullMatch),
                    wasCreated: $findResult['created'],
                );
            }
        }

        return $results;
    }
}
