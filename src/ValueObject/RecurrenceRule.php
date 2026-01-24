<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\RecurrenceType;

/**
 * Value object representing a parsed recurrence rule.
 */
final readonly class RecurrenceRule
{
    /**
     * @param string $originalText The original text that was parsed
     * @param RecurrenceType $type Whether recurrence is absolute (from schedule) or relative (from completion)
     * @param string $interval The base interval: 'day', 'week', 'month', 'year'
     * @param int $count How many intervals between occurrences (e.g., 2 for "every 2 weeks")
     * @param int[] $days Days of week for weekly patterns (0=Sunday, 6=Saturday)
     * @param int|null $dayOfMonth Day of month for monthly patterns (1-31, -1 for last day)
     * @param int|null $monthOfYear Month for yearly patterns (1-12)
     * @param string|null $time Time of day (HH:MM format)
     * @param \DateTimeImmutable|null $endDate When the recurrence should stop
     */
    private function __construct(
        public string $originalText,
        public RecurrenceType $type,
        public string $interval,
        public int $count,
        public array $days,
        public ?int $dayOfMonth,
        public ?int $monthOfYear,
        public ?string $time,
        public ?\DateTimeImmutable $endDate,
    ) {
    }

    /**
     * Create a new RecurrenceRule.
     *
     * @param string $originalText The original text that was parsed
     * @param RecurrenceType $type Whether recurrence is absolute or relative
     * @param string $interval The base interval: 'day', 'week', 'month', 'year'
     * @param int $count How many intervals between occurrences
     * @param int[] $days Days of week for weekly patterns
     * @param int|null $dayOfMonth Day of month for monthly patterns
     * @param int|null $monthOfYear Month for yearly patterns
     * @param string|null $time Time of day
     * @param \DateTimeImmutable|null $endDate When the recurrence should stop
     */
    public static function create(
        string $originalText,
        RecurrenceType $type,
        string $interval,
        int $count = 1,
        array $days = [],
        ?int $dayOfMonth = null,
        ?int $monthOfYear = null,
        ?string $time = null,
        ?\DateTimeImmutable $endDate = null,
    ): self {
        return new self(
            originalText: $originalText,
            type: $type,
            interval: $interval,
            count: $count,
            days: $days,
            dayOfMonth: $dayOfMonth,
            monthOfYear: $monthOfYear,
            time: $time,
            endDate: $endDate,
        );
    }

    /**
     * Check if this is an absolute (schedule-based) recurrence.
     */
    public function isAbsolute(): bool
    {
        return $this->type === RecurrenceType::ABSOLUTE;
    }

    /**
     * Check if this is a relative (completion-based) recurrence.
     */
    public function isRelative(): bool
    {
        return $this->type === RecurrenceType::RELATIVE;
    }

    /**
     * Serialize the rule to an array.
     *
     * @return array{
     *     originalText: string,
     *     type: string,
     *     interval: string,
     *     count: int,
     *     days: int[],
     *     dayOfMonth: int|null,
     *     monthOfYear: int|null,
     *     time: string|null,
     *     endDate: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'originalText' => $this->originalText,
            'type' => $this->type->value,
            'interval' => $this->interval,
            'count' => $this->count,
            'days' => $this->days,
            'dayOfMonth' => $this->dayOfMonth,
            'monthOfYear' => $this->monthOfYear,
            'time' => $this->time,
            'endDate' => $this->endDate?->format('Y-m-d'),
        ];
    }

    /**
     * Deserialize a rule from an array.
     *
     * @param array{
     *     originalText: string,
     *     type: string,
     *     interval: string,
     *     count?: int,
     *     days?: int[],
     *     dayOfMonth?: int|null,
     *     monthOfYear?: int|null,
     *     time?: string|null,
     *     endDate?: string|null
     * } $data
     */
    public static function fromArray(array $data): self
    {
        $requiredKeys = ['originalText', 'type', 'interval'];
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $data)) {
                throw new \InvalidArgumentException(sprintf('Missing required key "%s" in recurrence rule data', $key));
            }
        }

        $type = RecurrenceType::tryFrom($data['type']);
        if ($type === null) {
            throw new \InvalidArgumentException(sprintf('Invalid recurrence type "%s"', $data['type']));
        }

        $endDate = null;
        if (!empty($data['endDate'])) {
            $endDate = new \DateTimeImmutable($data['endDate']);
        }

        return new self(
            originalText: $data['originalText'],
            type: $type,
            interval: $data['interval'],
            count: $data['count'] ?? 1,
            days: $data['days'] ?? [],
            dayOfMonth: $data['dayOfMonth'] ?? null,
            monthOfYear: $data['monthOfYear'] ?? null,
            time: $data['time'] ?? null,
            endDate: $endDate,
        );
    }

    /**
     * Serialize the rule to JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Deserialize a rule from JSON.
     */
    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return self::fromArray($data);
    }

    /**
     * Get a human-readable description of the recurrence.
     */
    public function getDescription(): string
    {
        $parts = [];

        // Base interval
        if ($this->count === 1) {
            $parts[] = match ($this->interval) {
                'day' => 'Daily',
                'week' => 'Weekly',
                'month' => 'Monthly',
                'year' => 'Yearly',
                default => 'Every ' . $this->interval,
            };
        } else {
            $parts[] = sprintf('Every %d %ss', $this->count, $this->interval);
        }

        // Days of week
        if (!empty($this->days)) {
            $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            $names = array_map(fn($d) => $dayNames[$d] ?? '', $this->days);
            $parts[] = 'on ' . implode(', ', $names);
        }

        // Day of month
        if ($this->dayOfMonth !== null) {
            if ($this->dayOfMonth === -1) {
                $parts[] = 'on the last day';
            } else {
                $parts[] = 'on the ' . $this->ordinal($this->dayOfMonth);
            }
        }

        // Month
        if ($this->monthOfYear !== null) {
            $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            $parts[] = 'in ' . ($monthNames[$this->monthOfYear] ?? '');
        }

        // Time
        if ($this->time !== null) {
            $parts[] = 'at ' . $this->time;
        }

        // End date
        if ($this->endDate !== null) {
            $parts[] = 'until ' . $this->endDate->format('M j, Y');
        }

        // Type indicator
        if ($this->type === RecurrenceType::RELATIVE) {
            $parts[] = '(from completion)';
        }

        return implode(' ', $parts);
    }

    /**
     * Convert a number to its ordinal form.
     */
    private function ordinal(int $number): string
    {
        $suffixes = ['th', 'st', 'nd', 'rd'];
        $mod100 = $number % 100;

        if ($mod100 >= 11 && $mod100 <= 13) {
            return $number . 'th';
        }

        return $number . ($suffixes[$number % 10] ?? 'th');
    }
}
