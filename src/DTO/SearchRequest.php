<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for global search.
 */
final class SearchRequest
{
    public const TYPE_ALL = 'all';
    public const TYPE_TASKS = 'tasks';
    public const TYPE_PROJECTS = 'projects';
    public const TYPE_TAGS = 'tags';

    public const VALID_TYPES = [
        self::TYPE_ALL,
        self::TYPE_TASKS,
        self::TYPE_PROJECTS,
        self::TYPE_TAGS,
    ];

    public function __construct(
        #[Assert\NotBlank(message: 'Search query is required')]
        #[Assert\Length(
            min: 2,
            max: 200,
            minMessage: 'Search query must be at least {{ limit }} characters',
            maxMessage: 'Search query must be at most {{ limit }} characters'
        )]
        public readonly string $query,
        #[Assert\Choice(
            choices: self::VALID_TYPES,
            message: 'Type must be one of: {{ choices }}'
        )]
        public readonly string $type = self::TYPE_ALL,
        #[Assert\Range(
            min: 1,
            max: 100,
            notInRangeMessage: 'Page must be between {{ min }} and {{ max }}'
        )]
        public readonly int $page = 1,
        #[Assert\Range(
            min: 1,
            max: 100,
            notInRangeMessage: 'Limit must be between {{ min }} and {{ max }}'
        )]
        public readonly int $limit = 20,
        public readonly bool $highlight = true,
    ) {
    }

    /**
     * Creates a SearchRequest from query parameters.
     *
     * @param array<string, mixed> $params
     */
    public static function fromArray(array $params): self
    {
        return new self(
            query: (string) ($params['q'] ?? $params['query'] ?? ''),
            type: (string) ($params['type'] ?? self::TYPE_ALL),
            page: isset($params['page']) ? (int) $params['page'] : 1,
            limit: isset($params['limit']) ? (int) $params['limit'] : 20,
            highlight: !isset($params['highlight']) || filter_var($params['highlight'], FILTER_VALIDATE_BOOLEAN),
        );
    }
}
