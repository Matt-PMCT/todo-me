<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for task sorting parameters.
 */
final class TaskSortRequest
{
    public const ALLOWED_FIELDS = [
        'due_date',
        'priority',
        'created_at',
        'updated_at',
        'completed_at',
        'title',
        'position',
    ];

    private const FIELD_TO_DQL = [
        'due_date' => 't.dueDate',
        'priority' => 't.priority',
        'created_at' => 't.createdAt',
        'updated_at' => 't.updatedAt',
        'completed_at' => 't.completedAt',
        'title' => 't.title',
        'position' => 't.position',
    ];

    public function __construct(
        #[Assert\Choice(
            choices: self::ALLOWED_FIELDS,
            message: 'Sort field must be one of: {{ choices }}'
        )]
        public readonly string $field = 'position',

        #[Assert\Choice(
            choices: ['ASC', 'DESC'],
            message: 'Sort direction must be ASC or DESC'
        )]
        public readonly string $direction = 'ASC',
    ) {
    }

    /**
     * Creates a TaskSortRequest from an HTTP request's query parameters.
     * Silently falls back to defaults for invalid values.
     */
    public static function fromRequest(Request $request): self
    {
        $field = $request->query->getString('sort')
            ?: $request->query->getString('sort_by')
            ?: 'position';

        if (!in_array($field, self::ALLOWED_FIELDS, true)) {
            $field = 'position';
        }

        $direction = strtoupper(
            $request->query->getString('direction')
                ?: $request->query->getString('order')
                ?: 'ASC'
        );

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        return new self(field: $field, direction: $direction);
    }

    /**
     * Maps the API field name to its Doctrine DQL column name.
     */
    public function getDqlField(): string
    {
        return self::FIELD_TO_DQL[$this->field];
    }

    /**
     * Returns true if the field should use nulls-last sorting.
     */
    public function isNullsLastField(): bool
    {
        return $this->field === 'due_date' || $this->field === 'completed_at';
    }
}
