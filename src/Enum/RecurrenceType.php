<?php

declare(strict_types=1);

namespace App\Enum;

enum RecurrenceType: string
{
    case ABSOLUTE = 'absolute';
    case RELATIVE = 'relative';

    /**
     * Get a human-readable label for the recurrence type.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::ABSOLUTE => 'Absolute (from schedule)',
            self::RELATIVE => 'Relative (from completion)',
        };
    }

    /**
     * Get a short description of the recurrence type.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::ABSOLUTE => 'Next occurrence calculated from the original schedule',
            self::RELATIVE => 'Next occurrence calculated from when the task is completed',
        };
    }

    /**
     * Check if the given string is a valid recurrence type.
     */
    public static function isValid(string $type): bool
    {
        return self::tryFrom($type) !== null;
    }

    /**
     * Get all available type values.
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
