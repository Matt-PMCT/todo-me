<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for task sorting parameters.
 *
 * Supports both single-field sorting (API) and preset-based compound sorting (Web UI).
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

    public const DEFAULT_PRESET = 'due_date_priority';

    /**
     * Sort presets: each maps to an array of [field, direction] tuples.
     *
     * @var array<string, array{label: string, sorts: list<array{string, string}>}>
     */
    public const PRESETS = [
        'due_date_priority' => [
            'label' => 'Due Date + Priority',
            'sorts' => [['due_date', 'ASC'], ['priority', 'DESC']],
        ],
        'priority_due_date' => [
            'label' => 'Priority + Due Date',
            'sorts' => [['priority', 'DESC'], ['due_date', 'ASC']],
        ],
        'created_newest' => [
            'label' => 'Newest First',
            'sorts' => [['created_at', 'DESC']],
        ],
        'created_oldest' => [
            'label' => 'Oldest First',
            'sorts' => [['created_at', 'ASC']],
        ],
        'updated_recent' => [
            'label' => 'Recently Updated',
            'sorts' => [['updated_at', 'DESC']],
        ],
        'title_az' => [
            'label' => 'Title A-Z',
            'sorts' => [['title', 'ASC']],
        ],
        'position' => [
            'label' => 'Manual (Position)',
            'sorts' => [['position', 'ASC'], ['priority', 'DESC']],
        ],
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

    private const NULLS_LAST_FIELDS = ['due_date', 'completed_at'];

    /**
     * @param list<array{string, string}>|null $secondarySorts Additional sort criteria beyond primary
     */
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
        public readonly ?array $secondarySorts = null,
        public readonly string $presetKey = '',
    ) {
    }

    /**
     * Creates a TaskSortRequest from an HTTP request's query parameters.
     * Silently falls back to defaults for invalid values.
     * Used by the API (single-field sorting).
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
     * Creates a TaskSortRequest from a preset key.
     * Falls back to the default preset for invalid keys.
     */
    public static function fromPreset(string $preset): self
    {
        if (!isset(self::PRESETS[$preset])) {
            $preset = self::DEFAULT_PRESET;
        }

        $sorts = self::PRESETS[$preset]['sorts'];
        $primary = $sorts[0];
        $secondary = \count($sorts) > 1 ? \array_slice($sorts, 1) : null;

        return new self(
            field: $primary[0],
            direction: $primary[1],
            secondarySorts: $secondary,
            presetKey: $preset,
        );
    }

    /**
     * Creates a TaskSortRequest from an HTTP request, falling back to a user's default preset.
     * Priority: URL ?sort=preset_key > provided default preset > system default preset.
     */
    public static function fromRequestWithDefaults(
        Request $request,
        string $defaultPreset = self::DEFAULT_PRESET,
    ): self {
        $preset = $request->query->getString('sort') ?: '';

        if ($preset !== '' && isset(self::PRESETS[$preset])) {
            return self::fromPreset($preset);
        }

        // Fall back to user's default preset
        if (isset(self::PRESETS[$defaultPreset])) {
            return self::fromPreset($defaultPreset);
        }

        // Fall back to system default
        return self::fromPreset(self::DEFAULT_PRESET);
    }

    /**
     * Returns the preset key if this was built from a preset, or empty string.
     */
    public function getPresetKey(): string
    {
        return $this->presetKey;
    }

    /**
     * Returns the human-readable label for the current preset.
     */
    public function getPresetLabel(): string
    {
        if ($this->presetKey !== '' && isset(self::PRESETS[$this->presetKey])) {
            return self::PRESETS[$this->presetKey]['label'];
        }

        return self::PRESETS[self::DEFAULT_PRESET]['label'];
    }

    /**
     * Returns all available preset keys.
     *
     * @return list<string>
     */
    public static function getPresetKeys(): array
    {
        return array_keys(self::PRESETS);
    }

    /**
     * Maps the API field name to its Doctrine DQL column name.
     */
    public function getDqlField(): string
    {
        return self::FIELD_TO_DQL[$this->field];
    }

    /**
     * Maps any field name to its DQL column.
     */
    public static function fieldToDql(string $field): string
    {
        return self::FIELD_TO_DQL[$field] ?? 't.'.$field;
    }

    /**
     * Returns true if the given field should use nulls-last sorting.
     */
    public static function isFieldNullsLast(string $field): bool
    {
        return \in_array($field, self::NULLS_LAST_FIELDS, true);
    }

    /**
     * Returns true if the primary field should use nulls-last sorting.
     */
    public function isNullsLastField(): bool
    {
        return self::isFieldNullsLast($this->field);
    }
}
