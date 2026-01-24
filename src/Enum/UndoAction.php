<?php

declare(strict_types=1);

namespace App\Enum;

enum UndoAction: string
{
    case DELETE = 'delete';
    case UPDATE = 'update';
    case STATUS_CHANGE = 'status_change';
    case ARCHIVE = 'archive';
    case BATCH = 'batch';

    /**
     * Get a human-readable label for the action.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::DELETE => 'Delete',
            self::UPDATE => 'Update',
            self::STATUS_CHANGE => 'Status Change',
            self::ARCHIVE => 'Archive',
            self::BATCH => 'Batch',
        };
    }

    /**
     * Check if the given string is a valid undo action.
     */
    public static function isValid(string $action): bool
    {
        return self::tryFrom($action) !== null;
    }

    /**
     * Get all available action values.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn(self $action) => $action->value, self::cases());
    }
}
