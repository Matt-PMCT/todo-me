<?php

declare(strict_types=1);

namespace App\Service\Recurrence;

use App\Enum\RecurrenceType;
use App\Exception\InvalidRecurrenceException;
use App\ValueObject\RecurrenceRule;

/**
 * Parses natural language recurrence rules into RecurrenceRule value objects.
 *
 * Supported patterns:
 * - Daily: "every day", "every 3 days", "daily", "every! day"
 * - Weekly: "every week", "every Monday", "every Mon, Wed, Fri", "biweekly", "weekday", "weekend"
 * - Monthly: "every month", "every 15th", "every month on the last day", "quarterly"
 * - Yearly: "every year", "every January 15", "annually"
 * - Time: "at 2pm", "at 14:00", "noon", "midnight"
 * - End date: "until March 1"
 * - Type: "every!" prefix = relative (from completion)
 */
final class RecurrenceRuleParser
{
    private const DAY_MAP = [
        'sunday' => 0, 'sun' => 0, 'su' => 0,
        'monday' => 1, 'mon' => 1, 'mo' => 1,
        'tuesday' => 2, 'tue' => 2, 'tu' => 2,
        'wednesday' => 3, 'wed' => 3, 'we' => 3,
        'thursday' => 4, 'thu' => 4, 'th' => 4,
        'friday' => 5, 'fri' => 5, 'fr' => 5,
        'saturday' => 6, 'sat' => 6, 'sa' => 6,
    ];

    private const MONTH_MAP = [
        'january' => 1, 'jan' => 1,
        'february' => 2, 'feb' => 2,
        'march' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4,
        'may' => 5,
        'june' => 6, 'jun' => 6,
        'july' => 7, 'jul' => 7,
        'august' => 8, 'aug' => 8,
        'september' => 9, 'sep' => 9, 'sept' => 9,
        'october' => 10, 'oct' => 10,
        'november' => 11, 'nov' => 11,
        'december' => 12, 'dec' => 12,
    ];

    /**
     * Parse a recurrence rule string into a RecurrenceRule object.
     *
     * @param string $rule The natural language rule
     *
     * @return RecurrenceRule The parsed rule
     *
     * @throws InvalidRecurrenceException If the rule cannot be parsed
     */
    public function parse(string $rule): RecurrenceRule
    {
        $originalText = trim($rule);
        if ($originalText === '') {
            throw InvalidRecurrenceException::invalidPattern($rule);
        }

        $normalizedRule = $this->normalizeRule($originalText);

        // Check for relative type (every! prefix)
        $type = RecurrenceType::ABSOLUTE;
        if (str_starts_with($normalizedRule, 'every!')) {
            $type = RecurrenceType::RELATIVE;
            $normalizedRule = 'every'.substr($normalizedRule, 6);
        }

        // Extract and remove end date
        $endDate = $this->extractEndDate($normalizedRule);
        if ($endDate !== null) {
            $normalizedRule = preg_replace('/\s+until\s+.+$/i', '', $normalizedRule);
        }

        // Extract and remove time
        $time = $this->extractTime($normalizedRule);
        if ($time !== null) {
            $normalizedRule = preg_replace('/\s+at\s+\d{1,2}(:\d{2})?\s*(am|pm)?/i', '', $normalizedRule);
            $normalizedRule = preg_replace('/\s+(noon|midnight)/i', '', $normalizedRule);
        }

        // Handle shortcuts
        $shortcutResult = $this->handleShortcuts($normalizedRule);
        if ($shortcutResult !== null) {
            return RecurrenceRule::create(
                originalText: $originalText,
                type: $type,
                interval: $shortcutResult['interval'],
                count: $shortcutResult['count'],
                days: $shortcutResult['days'],
                dayOfMonth: $shortcutResult['dayOfMonth'] ?? null,
                monthOfYear: $shortcutResult['monthOfYear'] ?? null,
                time: $time,
                endDate: $endDate,
            );
        }

        // Parse the main pattern
        $result = $this->parseMainPattern($normalizedRule);
        if ($result === null) {
            throw InvalidRecurrenceException::invalidPattern($rule);
        }

        return RecurrenceRule::create(
            originalText: $originalText,
            type: $type,
            interval: $result['interval'],
            count: $result['count'],
            days: $result['days'],
            dayOfMonth: $result['dayOfMonth'] ?? null,
            monthOfYear: $result['monthOfYear'] ?? null,
            time: $time,
            endDate: $endDate,
        );
    }

    /**
     * Normalize the rule for parsing.
     */
    private function normalizeRule(string $rule): string
    {
        // Convert to lowercase and trim
        $normalized = strtolower(trim($rule));

        // Normalize whitespace
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        // Handle common abbreviations and variations
        $normalized = str_replace(['every other'], ['every 2'], $normalized);

        return $normalized;
    }

    /**
     * Handle shortcut patterns like "daily", "weekly", "biweekly", etc.
     *
     * @return array{interval: string, count: int, days: int[], dayOfMonth?: int, monthOfYear?: int}|null
     */
    private function handleShortcuts(string $rule): ?array
    {
        $shortcuts = [
            'daily' => ['interval' => 'day', 'count' => 1, 'days' => []],
            'weekly' => ['interval' => 'week', 'count' => 1, 'days' => []],
            'biweekly' => ['interval' => 'week', 'count' => 2, 'days' => []],
            'monthly' => ['interval' => 'month', 'count' => 1, 'days' => []],
            'quarterly' => ['interval' => 'month', 'count' => 3, 'days' => []],
            'yearly' => ['interval' => 'year', 'count' => 1, 'days' => []],
            'annually' => ['interval' => 'year', 'count' => 1, 'days' => []],
            'weekday' => ['interval' => 'week', 'count' => 1, 'days' => [1, 2, 3, 4, 5]],
            'weekdays' => ['interval' => 'week', 'count' => 1, 'days' => [1, 2, 3, 4, 5]],
            'weekend' => ['interval' => 'week', 'count' => 1, 'days' => [0, 6]],
            'weekends' => ['interval' => 'week', 'count' => 1, 'days' => [0, 6]],
        ];

        return $shortcuts[$rule] ?? null;
    }

    /**
     * Parse the main "every X" pattern.
     *
     * @return array{interval: string, count: int, days: int[], dayOfMonth?: int, monthOfYear?: int}|null
     */
    private function parseMainPattern(string $rule): ?array
    {
        // Pattern: every [count] interval [on day/date]
        if (!str_starts_with($rule, 'every')) {
            return null;
        }

        // Remove "every" prefix
        $remaining = trim(substr($rule, 5));

        // Check for specific day of month pattern: "every 15th", "every 1st"
        if (preg_match('/^(\d{1,2})(st|nd|rd|th)?$/i', $remaining, $matches)) {
            $dayOfMonth = (int) $matches[1];
            if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                return [
                    'interval' => 'month',
                    'count' => 1,
                    'days' => [],
                    'dayOfMonth' => $dayOfMonth,
                ];
            }
        }

        // Check for "every last day" pattern
        if (preg_match('/^(month\s+on\s+the\s+)?last\s+day(\s+of\s+the\s+month)?$/i', $remaining)) {
            return [
                'interval' => 'month',
                'count' => 1,
                'days' => [],
                'dayOfMonth' => -1,
            ];
        }

        // Check for "every month on the 15th" pattern
        if (preg_match('/^month\s+on\s+the\s+(\d{1,2})(st|nd|rd|th)?$/i', $remaining, $matches)) {
            $dayOfMonth = (int) $matches[1];
            if ($dayOfMonth >= 1 && $dayOfMonth <= 31) {
                return [
                    'interval' => 'month',
                    'count' => 1,
                    'days' => [],
                    'dayOfMonth' => $dayOfMonth,
                ];
            }
        }

        // Check for yearly with month and day: "every January 15"
        if (preg_match('/^([a-z]+)\s+(\d{1,2})$/i', $remaining, $matches)) {
            $monthName = strtolower($matches[1]);
            $dayOfMonth = (int) $matches[2];
            if (isset(self::MONTH_MAP[$monthName]) && $dayOfMonth >= 1 && $dayOfMonth <= 31) {
                return [
                    'interval' => 'year',
                    'count' => 1,
                    'days' => [],
                    'dayOfMonth' => $dayOfMonth,
                    'monthOfYear' => self::MONTH_MAP[$monthName],
                ];
            }
        }

        // Check for count followed by interval: "every 3 days", "every 2 weeks"
        if (preg_match('/^(\d+)\s*(day|week|month|year)s?$/i', $remaining, $matches)) {
            return [
                'interval' => strtolower($matches[2]),
                'count' => (int) $matches[1],
                'days' => [],
            ];
        }

        // Check for single interval: "every day", "every week", "every month", "every year"
        if (preg_match('/^(day|week|month|year)s?$/i', $remaining, $matches)) {
            return [
                'interval' => strtolower($matches[1]),
                'count' => 1,
                'days' => [],
            ];
        }

        // Check for "every N weeks on Monday" pattern (with number) - MUST come before day names check
        if (preg_match('/^(\d+)\s+weeks?\s+on\s+(.+)$/i', $remaining, $matches)) {
            $count = (int) $matches[1];
            $days = $this->parseDayNames($matches[2]);
            if (!empty($days)) {
                return [
                    'interval' => 'week',
                    'count' => $count,
                    'days' => $days,
                ];
            }
        }

        // Check for "every week on Monday" pattern (without number)
        if (preg_match('/^weeks?\s+on\s+(.+)$/i', $remaining, $matches)) {
            $days = $this->parseDayNames($matches[1]);
            if (!empty($days)) {
                return [
                    'interval' => 'week',
                    'count' => 1,
                    'days' => $days,
                ];
            }
        }

        // Check for day names: "every Monday", "every Mon, Wed, Fri"
        $days = $this->parseDayNames($remaining);
        if (!empty($days)) {
            return [
                'interval' => 'week',
                'count' => 1,
                'days' => $days,
            ];
        }

        return null;
    }

    /**
     * Parse day names from a string.
     *
     * @return int[] Array of day numbers (0=Sunday, 6=Saturday)
     */
    private function parseDayNames(string $text): array
    {
        $days = [];

        // Split by comma, "and", or whitespace
        $parts = preg_split('/[\s,]+|(?:\s+and\s+)/i', $text);

        foreach ($parts as $part) {
            $part = strtolower(trim($part));
            if (isset(self::DAY_MAP[$part])) {
                $days[] = self::DAY_MAP[$part];
            }
        }

        // Sort and remove duplicates
        $days = array_unique($days);
        sort($days);

        return $days;
    }

    /**
     * Extract time from the rule.
     *
     * @return string|null Time in HH:MM format
     */
    private function extractTime(string $rule): ?string
    {
        // Check for noon/midnight
        if (preg_match('/\b(noon)\b/i', $rule)) {
            return '12:00';
        }
        if (preg_match('/\b(midnight)\b/i', $rule)) {
            return '00:00';
        }

        // Check for "at HH:MM" or "at H:MM" or "at Hpm/am"
        if (preg_match('/\bat\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?/i', $rule, $matches)) {
            $hour = (int) $matches[1];
            $minute = isset($matches[2]) ? (int) $matches[2] : 0;
            $ampm = strtolower($matches[3] ?? '');

            // Convert to 24-hour format
            if ($ampm === 'pm' && $hour < 12) {
                $hour += 12;
            } elseif ($ampm === 'am' && $hour === 12) {
                $hour = 0;
            }

            return sprintf('%02d:%02d', $hour, $minute);
        }

        return null;
    }

    /**
     * Extract end date from the rule.
     *
     * @throws InvalidRecurrenceException If date cannot be parsed
     */
    private function extractEndDate(string $rule): ?\DateTimeImmutable
    {
        if (!preg_match('/\buntil\s+(.+)$/i', $rule, $matches)) {
            return null;
        }

        $dateStr = trim($matches[1]);

        // Remove any time specification that might follow
        $dateStr = preg_replace('/\s+at\s+.+$/i', '', $dateStr);

        // Try to parse the date
        try {
            // Try ISO format first: 2026-12-31
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
                return new \DateTimeImmutable($dateStr);
            }

            // Try "Month Day" format: March 1, January 15
            if (preg_match('/^([a-z]+)\s+(\d{1,2})(?:,?\s+(\d{4}))?$/i', $dateStr, $matches)) {
                $monthName = strtolower($matches[1]);
                if (isset(self::MONTH_MAP[$monthName])) {
                    $month = self::MONTH_MAP[$monthName];
                    $day = (int) $matches[2];
                    $year = isset($matches[3]) ? (int) $matches[3] : (int) date('Y');

                    // If the date is in the past, assume next year
                    $date = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
                    if ($date < new \DateTimeImmutable('today') && !isset($matches[3])) {
                        $date = $date->modify('+1 year');
                    }

                    return $date;
                }
            }

            // Try "Day Month Year" format: 1 March 2026
            if (preg_match('/^(\d{1,2})\s+([a-z]+)(?:\s+(\d{4}))?$/i', $dateStr, $matches)) {
                $day = (int) $matches[1];
                $monthName = strtolower($matches[2]);
                if (isset(self::MONTH_MAP[$monthName])) {
                    $month = self::MONTH_MAP[$monthName];
                    $year = isset($matches[3]) ? (int) $matches[3] : (int) date('Y');

                    $date = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
                    if ($date < new \DateTimeImmutable('today') && !isset($matches[3])) {
                        $date = $date->modify('+1 year');
                    }

                    return $date;
                }
            }

            // Fallback: try PHP's natural parsing
            return new \DateTimeImmutable($dateStr);
        } catch (\Exception $e) {
            throw InvalidRecurrenceException::invalidDate($rule, $dateStr);
        }
    }
}
