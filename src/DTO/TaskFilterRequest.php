<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Task;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for advanced task filtering.
 */
final class TaskFilterRequest
{
    public function __construct(
        /**
         * @var string[]|null
         */
        #[Assert\All([
            new Assert\Choice(
                choices: Task::STATUSES,
                message: 'Each status must be one of: {{ choices }}'
            )
        ])]
        public readonly ?array $statuses = null,

        #[Assert\Range(
            min: Task::PRIORITY_MIN,
            max: Task::PRIORITY_MAX,
            notInRangeMessage: 'Priority min must be between {{ min }} and {{ max }}'
        )]
        public readonly ?int $priorityMin = null,

        #[Assert\Range(
            min: Task::PRIORITY_MIN,
            max: Task::PRIORITY_MAX,
            notInRangeMessage: 'Priority max must be between {{ min }} and {{ max }}'
        )]
        public readonly ?int $priorityMax = null,

        /**
         * @var string[]|null
         */
        #[Assert\Count(
            max: 50,
            maxMessage: 'Cannot filter by more than {{ limit }} projects'
        )]
        #[Assert\All([
            new Assert\Uuid(message: 'Each project ID must be a valid UUID')
        ])]
        public readonly ?array $projectIds = null,

        public readonly bool $includeChildProjects = false,

        /**
         * @var string[]|null
         */
        #[Assert\Count(
            max: 50,
            maxMessage: 'Cannot filter by more than {{ limit }} tags'
        )]
        #[Assert\All([
            new Assert\Uuid(message: 'Each tag ID must be a valid UUID')
        ])]
        public readonly ?array $tagIds = null,

        #[Assert\Choice(
            choices: ['AND', 'OR'],
            message: 'Tag mode must be one of: {{ choices }}'
        )]
        public readonly string $tagMode = 'OR',

        public readonly ?string $dueBefore = null,

        public readonly ?string $dueAfter = null,

        public readonly ?bool $hasNoDueDate = null,

        public readonly ?string $search = null,

        public readonly bool $includeCompleted = true,
    ) {
    }

    /**
     * Creates a TaskFilterRequest from an HTTP request's query parameters.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            statuses: self::parseStringArray($request, ['status', 'statuses']),
            priorityMin: self::parseNullableInt($request, 'priority_min'),
            priorityMax: self::parseNullableInt($request, 'priority_max'),
            projectIds: self::parseStringArray($request, ['project_ids']),
            includeChildProjects: self::parseBool($request, 'include_child_projects', false),
            tagIds: self::parseStringArray($request, ['tag_ids']),
            tagMode: $request->query->getString('tag_mode', 'OR'),
            dueBefore: self::parseNullableString($request, 'due_before'),
            dueAfter: self::parseNullableString($request, 'due_after'),
            hasNoDueDate: self::parseNullableBool($request, 'has_no_due_date'),
            search: self::parseNullableString($request, 'search'),
            includeCompleted: self::parseBool($request, 'include_completed', true),
        );
    }

    /**
     * Parses a query parameter that can be comma-separated or an array.
     *
     * @param string[] $keys Query parameter keys to check (first match wins)
     * @return string[]|null
     */
    private static function parseStringArray(Request $request, array $keys): ?array
    {
        foreach ($keys as $key) {
            $value = $request->query->all()[$key] ?? null;

            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                return array_map('strval', array_filter($value, fn ($v) => $v !== '' && $v !== null));
            }

            if (is_string($value) && $value !== '') {
                return array_map('trim', explode(',', $value));
            }
        }

        return null;
    }

    private static function parseNullableInt(Request $request, string $key): ?int
    {
        $value = $request->query->get($key);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function parseNullableString(Request $request, string $key): ?string
    {
        $value = $request->query->get($key);

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private static function parseBool(Request $request, string $key, bool $default): bool
    {
        $value = $request->query->get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function parseNullableBool(Request $request, string $key): ?bool
    {
        $value = $request->query->get($key);

        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
