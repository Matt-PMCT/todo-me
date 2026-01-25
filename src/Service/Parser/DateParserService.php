<?php

declare(strict_types=1);

namespace App\Service\Parser;

use App\ValueObject\DateParseResult;

class DateParserService
{
    private string $userTimezone = 'UTC';
    private int $startOfWeek = 0; // 0 = Sunday, 1 = Monday
    private string $dateFormat = 'MDY'; // MDY, DMY, or YMD

    private const MONTHS = [
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

    private const DAY_NAMES = [
        'sunday' => 0, 'sun' => 0,
        'monday' => 1, 'mon' => 1,
        'tuesday' => 2, 'tue' => 2, 'tues' => 2,
        'wednesday' => 3, 'wed' => 3,
        'thursday' => 4, 'thu' => 4, 'thur' => 4, 'thurs' => 4,
        'friday' => 5, 'fri' => 5,
        'saturday' => 6, 'sat' => 6,
    ];

    public function __construct(
        private readonly TimezoneHelper $timezoneHelper,
    ) {
    }

    public function setUserTimezone(string $timezone): self
    {
        $this->userTimezone = $timezone;

        return $this;
    }

    public function setStartOfWeek(int $day): self
    {
        $this->startOfWeek = $day;

        return $this;
    }

    public function setDateFormat(string $format): self
    {
        $this->dateFormat = $format;

        return $this;
    }

    /**
     * Parse a string for date/time patterns.
     * Returns the first match found, or null if no pattern matches.
     */
    public function parse(string $input): ?DateParseResult
    {
        $patterns = [
            [$this, 'parseRelativeDate'],
            [$this, 'parseDayOffset'],
            [$this, 'parseDayName'],
            [$this, 'parseMonthDay'],
            [$this, 'parseIsoDate'],
            [$this, 'parseNumericDate'],
        ];

        $bestResult = null;
        $bestPosition = PHP_INT_MAX;

        foreach ($patterns as $parser) {
            $result = $parser($input);
            if ($result !== null && $result->startPosition < $bestPosition) {
                $bestResult = $result;
                $bestPosition = $result->startPosition;
            }
        }

        if ($bestResult === null) {
            return null;
        }

        // Try to parse time after the date
        return $this->parseTimeComponent($input, $bestResult);
    }

    private function parseRelativeDate(string $input): ?DateParseResult
    {
        $pattern = '/\b(today|tomorrow|yesterday)\b/i';

        if (!preg_match($pattern, $input, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $word = strtolower($matches[1][0]);
        $startPos = $matches[0][1];
        $endPos = $startPos + strlen($matches[0][0]);

        $baseDate = $this->timezoneHelper->getStartOfDay($this->userTimezone);

        $date = match ($word) {
            'today' => $baseDate,
            'tomorrow' => $baseDate->modify('+1 day'),
            'yesterday' => $baseDate->modify('-1 day'),
        };

        return DateParseResult::create(
            date: $date,
            time: null,
            originalText: $matches[0][0],
            startPosition: $startPos,
            endPosition: $endPos,
            hasTime: false,
        );
    }

    private function parseDayOffset(string $input): ?DateParseResult
    {
        // Match "in X days/weeks/months" or "in a day/week/month" or "next week/month"
        $patterns = [
            '/\bin\s+(\d+)\s+(day|days|week|weeks|month|months)\b/i',
            '/\bin\s+a\s+(day|week|month)\b/i',
            '/\bnext\s+(week|month)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $matches, PREG_OFFSET_CAPTURE)) {
                $startPos = $matches[0][1];
                $endPos = $startPos + strlen($matches[0][0]);
                $baseDate = $this->timezoneHelper->getStartOfDay($this->userTimezone);

                if (str_starts_with(strtolower($matches[0][0]), 'in a ')) {
                    // "in a day/week/month"
                    $unit = strtolower($matches[1][0]);
                    $date = $baseDate->modify("+1 {$unit}");
                } elseif (str_starts_with(strtolower($matches[0][0]), 'next ')) {
                    // "next week/month"
                    $unit = strtolower($matches[1][0]);
                    $date = $baseDate->modify("+1 {$unit}");
                } else {
                    // "in X days/weeks/months"
                    $number = (int) $matches[1][0];
                    $unit = strtolower($matches[2][0]);
                    // Normalize plural to singular
                    $unit = rtrim($unit, 's');
                    $date = $baseDate->modify("+{$number} {$unit}");
                }

                return DateParseResult::create(
                    date: $date,
                    time: null,
                    originalText: $matches[0][0],
                    startPosition: $startPos,
                    endPosition: $endPos,
                    hasTime: false,
                );
            }
        }

        return null;
    }

    private function parseDayName(string $input): ?DateParseResult
    {
        // Match "monday", "next monday", "this monday"
        $dayNamesPattern = implode('|', array_keys(self::DAY_NAMES));
        $pattern = '/\b(next\s+|this\s+)?('.$dayNamesPattern.')\b/i';

        if (!preg_match($pattern, $input, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $modifier = strtolower(trim($matches[1][0] ?? ''));
        $dayName = strtolower($matches[2][0]);
        $targetDay = self::DAY_NAMES[$dayName];

        $startPos = $matches[0][1];
        $endPos = $startPos + strlen($matches[0][0]);

        $baseDate = $this->timezoneHelper->getStartOfDay($this->userTimezone);
        $currentDay = (int) $baseDate->format('w'); // 0 = Sunday

        $daysUntil = ($targetDay - $currentDay + 7) % 7;

        // For "next", always go to the next occurrence (at least 1 day away)
        // If the day is today and no modifier, default to next occurrence
        if ($modifier === 'next' || $daysUntil === 0) {
            if ($daysUntil === 0) {
                $daysUntil = 7;
            }
        }

        // For "this", if the target day has already passed this week, still use this week
        if ($modifier === 'this') {
            $daysUntil = ($targetDay - $currentDay + 7) % 7;
            if ($daysUntil === 0) {
                // "this monday" when today is Monday = today
                $daysUntil = 0;
            }
        }

        $date = $baseDate->modify("+{$daysUntil} days");

        return DateParseResult::create(
            date: $date,
            time: null,
            originalText: $matches[0][0],
            startPosition: $startPos,
            endPosition: $endPos,
            hasTime: false,
        );
    }

    private function parseMonthDay(string $input): ?DateParseResult
    {
        $monthNames = implode('|', array_keys(self::MONTHS));

        // Match "Jan 23", "January 23rd", "23 Jan", "23rd January"
        $patterns = [
            // Month Day: Jan 23, January 23rd
            '/\b('.$monthNames.')\s+(\d{1,2})(?:st|nd|rd|th)?\b/i',
            // Day Month: 23 Jan, 23rd January
            '/\b(\d{1,2})(?:st|nd|rd|th)?\s+('.$monthNames.')\b/i',
        ];

        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $input, $matches, PREG_OFFSET_CAPTURE)) {
                if ($index === 0) {
                    // Month Day format
                    $monthName = strtolower($matches[1][0]);
                    $day = (int) $matches[2][0];
                } else {
                    // Day Month format
                    $day = (int) $matches[1][0];
                    $monthName = strtolower($matches[2][0]);
                }

                $month = self::MONTHS[$monthName];
                $startPos = $matches[0][1];
                $endPos = $startPos + strlen($matches[0][0]);

                $baseDate = $this->timezoneHelper->getStartOfDay($this->userTimezone);
                $year = (int) $baseDate->format('Y');

                // If the date has passed or is today, use next year
                $testDate = $baseDate->setDate($year, $month, $day);
                if ($testDate <= $baseDate) {
                    $year++;
                }

                $date = $baseDate->setDate($year, $month, $day);

                return DateParseResult::create(
                    date: $date,
                    time: null,
                    originalText: $matches[0][0],
                    startPosition: $startPos,
                    endPosition: $endPos,
                    hasTime: false,
                );
            }
        }

        return null;
    }

    private function parseIsoDate(string $input): ?DateParseResult
    {
        // ISO format: 2026-01-23
        $pattern = '/\b(\d{4})-(\d{2})-(\d{2})\b/';

        if (!preg_match($pattern, $input, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $year = (int) $matches[1][0];
        $month = (int) $matches[2][0];
        $day = (int) $matches[3][0];

        $startPos = $matches[0][1];
        $endPos = $startPos + strlen($matches[0][0]);

        $baseDate = $this->timezoneHelper->getStartOfDay($this->userTimezone);
        $date = $baseDate->setDate($year, $month, $day);

        return DateParseResult::create(
            date: $date,
            time: null,
            originalText: $matches[0][0],
            startPosition: $startPos,
            endPosition: $endPos,
            hasTime: false,
        );
    }

    private function parseNumericDate(string $input): ?DateParseResult
    {
        // Match MM/DD, MM-DD, MM/DD/YYYY, MM-DD-YYYY patterns
        $patterns = [
            // With year: 01/23/2026 or 01-23-2026
            '/\b(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})\b/',
            // Without year: 1/23 or 01-23
            '/\b(\d{1,2})[\/\-](\d{1,2})\b/',
        ];

        foreach ($patterns as $index => $pattern) {
            if (preg_match($pattern, $input, $matches, PREG_OFFSET_CAPTURE)) {
                $startPos = $matches[0][1];
                $endPos = $startPos + strlen($matches[0][0]);

                $baseDate = $this->timezoneHelper->getStartOfDay($this->userTimezone);

                if ($index === 0) {
                    // With year
                    [$month, $day, $year] = $this->interpretNumericParts(
                        (int) $matches[1][0],
                        (int) $matches[2][0],
                        (int) $matches[3][0]
                    );
                } else {
                    // Without year - use current or next year
                    [$month, $day] = $this->interpretNumericPartsNoYear(
                        (int) $matches[1][0],
                        (int) $matches[2][0]
                    );
                    $year = (int) $baseDate->format('Y');

                    // If the date has passed this year, use next year
                    $testDate = $baseDate->setDate($year, $month, $day);
                    if ($testDate < $baseDate) {
                        $year++;
                    }
                }

                $date = $baseDate->setDate($year, $month, $day);

                return DateParseResult::create(
                    date: $date,
                    time: null,
                    originalText: $matches[0][0],
                    startPosition: $startPos,
                    endPosition: $endPos,
                    hasTime: false,
                );
            }
        }

        return null;
    }

    /**
     * Interpret numeric date parts based on user's date format preference.
     * Returns [month, day, year]
     */
    private function interpretNumericParts(int $first, int $second, int $third): array
    {
        return match ($this->dateFormat) {
            'DMY' => [$second, $first, $third],
            'YMD' => [$second, $third, $first],
            default => [$first, $second, $third], // MDY
        };
    }

    /**
     * Interpret numeric date parts without year based on user's date format preference.
     * Returns [month, day]
     */
    private function interpretNumericPartsNoYear(int $first, int $second): array
    {
        return match ($this->dateFormat) {
            'DMY' => [$second, $first],
            'YMD' => [$first, $second], // For YMD without year, assume M/D
            default => [$first, $second], // MDY
        };
    }

    /**
     * Try to parse a time component after the date match.
     */
    private function parseTimeComponent(string $input, DateParseResult $dateResult): DateParseResult
    {
        // Look for time after the date: "at 2pm", "at 14:00", "2:30pm"
        $afterDate = substr($input, $dateResult->endPosition);

        $timePatterns = [
            // "at 2pm", "at 2:30pm", "at 14:00"
            '/^\s*at\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)?\b/i',
            // Direct time: "2pm", "2:30pm", "14:00"
            '/^\s+(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i',
        ];

        foreach ($timePatterns as $pattern) {
            if (preg_match($pattern, $afterDate, $matches)) {
                $hour = (int) $matches[1];
                $minute = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;
                $ampm = strtolower($matches[3] ?? '');

                // Convert to 24-hour format
                if ($ampm === 'pm' && $hour < 12) {
                    $hour += 12;
                } elseif ($ampm === 'am' && $hour === 12) {
                    $hour = 0;
                }

                $timeString = sprintf('%02d:%02d', $hour, $minute);
                $newEndPos = $dateResult->endPosition + strlen($matches[0]);

                // Update the date with the time
                $dateWithTime = $dateResult->date->setTime($hour, $minute);

                return DateParseResult::create(
                    date: $dateWithTime,
                    time: $timeString,
                    originalText: $dateResult->originalText.$matches[0],
                    startPosition: $dateResult->startPosition,
                    endPosition: $newEndPos,
                    hasTime: true,
                );
            }
        }

        return $dateResult;
    }
}
