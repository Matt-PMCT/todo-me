<?php

declare(strict_types=1);

namespace App\Service\Recurrence;

use App\Enum\RecurrenceType;
use App\ValueObject\RecurrenceRule;

/**
 * Calculates the next occurrence date for recurring tasks.
 */
final class NextDateCalculator
{
    /**
     * Calculate the next occurrence date.
     *
     * @param RecurrenceRule $rule The recurrence rule
     * @param \DateTimeImmutable $referenceDate The reference date (due date for absolute, completion date for relative)
     * @return \DateTimeImmutable The next occurrence date
     */
    public function calculate(RecurrenceRule $rule, \DateTimeImmutable $referenceDate): \DateTimeImmutable
    {
        return match ($rule->interval) {
            'day' => $this->calculateDaily($rule, $referenceDate),
            'week' => $this->calculateWeekly($rule, $referenceDate),
            'month' => $this->calculateMonthly($rule, $referenceDate),
            'year' => $this->calculateYearly($rule, $referenceDate),
            default => $referenceDate->modify('+1 day'),
        };
    }

    /**
     * Check if a next instance should be created based on the end date.
     *
     * @param RecurrenceRule $rule The recurrence rule
     * @param \DateTimeImmutable $nextDate The calculated next date
     * @return bool True if a next instance should be created
     */
    public function shouldCreateNextInstance(RecurrenceRule $rule, \DateTimeImmutable $nextDate): bool
    {
        if ($rule->endDate === null) {
            return true;
        }

        // Compare dates only (ignore time)
        return $nextDate->format('Y-m-d') <= $rule->endDate->format('Y-m-d');
    }

    /**
     * Calculate next date for daily recurrence.
     */
    private function calculateDaily(RecurrenceRule $rule, \DateTimeImmutable $referenceDate): \DateTimeImmutable
    {
        $nextDate = $referenceDate->modify("+{$rule->count} days");

        if ($rule->time !== null) {
            $nextDate = $this->applyTime($nextDate, $rule->time);
        }

        return $nextDate;
    }

    /**
     * Calculate next date for weekly recurrence.
     */
    private function calculateWeekly(RecurrenceRule $rule, \DateTimeImmutable $referenceDate): \DateTimeImmutable
    {
        // If specific days are specified
        if (!empty($rule->days)) {
            $nextDate = $this->findNextMatchingDay($rule, $referenceDate);
        } else {
            // Simple "every N weeks" - add N weeks
            $nextDate = $referenceDate->modify("+{$rule->count} weeks");
        }

        if ($rule->time !== null) {
            $nextDate = $this->applyTime($nextDate, $rule->time);
        }

        return $nextDate;
    }

    /**
     * Find the next date matching the specified days of week.
     * Note: Always moves forward at least 1 day from the reference date.
     */
    private function findNextMatchingDay(RecurrenceRule $rule, \DateTimeImmutable $referenceDate): \DateTimeImmutable
    {
        $currentDayOfWeek = (int) $referenceDate->format('w'); // 0=Sunday, 6=Saturday
        $days = $rule->days;
        sort($days);

        // Find next matching day AFTER today (not including today)
        $daysToAdd = null;

        // First, look for a day strictly after the current day in the current week
        foreach ($days as $targetDay) {
            if ($targetDay > $currentDayOfWeek) {
                $daysToAdd = $targetDay - $currentDayOfWeek;
                break;
            }
        }

        // If no day later in current week, go to the next cycle
        if ($daysToAdd === null) {
            // Calculate days to the first matching day in the next cycle
            $daysUntilEndOfWeek = 7 - $currentDayOfWeek;
            $weeksToSkip = $rule->count - 1; // Skip additional weeks for "every N weeks"
            $daysToAdd = $daysUntilEndOfWeek + ($weeksToSkip * 7) + $days[0];
        }

        return $referenceDate->modify("+{$daysToAdd} days");
    }

    /**
     * Calculate next date for monthly recurrence.
     */
    private function calculateMonthly(RecurrenceRule $rule, \DateTimeImmutable $referenceDate): \DateTimeImmutable
    {
        // Calculate target month by going to day 1 first to avoid overflow issues
        $year = (int) $referenceDate->format('Y');
        $month = (int) $referenceDate->format('n');
        $originalDay = (int) $referenceDate->format('j');

        // Add N months
        $month += $rule->count;
        while ($month > 12) {
            $month -= 12;
            $year++;
        }

        // Create a date for the first of the target month to get days in that month
        $firstOfMonth = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $daysInMonth = (int) $firstOfMonth->format('t');

        // Handle specific day of month
        if ($rule->dayOfMonth !== null) {
            if ($rule->dayOfMonth === -1) {
                // Last day of month
                $targetDay = $daysInMonth;
            } else {
                // Specific day, handle edge cases (31st in February → 28/29th)
                $targetDay = min($rule->dayOfMonth, $daysInMonth);
            }
        } else {
            // Keep the same day of month, adjusting if necessary
            $targetDay = min($originalDay, $daysInMonth);
        }

        $nextDate = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $targetDay));

        if ($rule->time !== null) {
            $nextDate = $this->applyTime($nextDate, $rule->time);
        }

        return $nextDate;
    }

    /**
     * Calculate next date for yearly recurrence.
     */
    private function calculateYearly(RecurrenceRule $rule, \DateTimeImmutable $referenceDate): \DateTimeImmutable
    {
        $nextDate = $referenceDate->modify("+{$rule->count} years");

        // Handle specific month and day
        if ($rule->monthOfYear !== null) {
            $year = (int) $nextDate->format('Y');
            $month = $rule->monthOfYear;

            // Determine day
            $day = 1;
            if ($rule->dayOfMonth !== null) {
                if ($rule->dayOfMonth === -1) {
                    // Last day of the target month
                    $tempDate = new \DateTimeImmutable("$year-$month-01");
                    $day = (int) $tempDate->format('t');
                } else {
                    // Specific day, handle edge cases (Feb 30 → Feb 28/29)
                    $tempDate = new \DateTimeImmutable("$year-$month-01");
                    $day = min($rule->dayOfMonth, (int) $tempDate->format('t'));
                }
            }

            // Handle leap year edge case (Feb 29 on non-leap year → Feb 28)
            $nextDate = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
        } elseif ($rule->dayOfMonth !== null) {
            // Handle day of month without specific month
            if ($rule->dayOfMonth === -1) {
                $nextDate = new \DateTimeImmutable($nextDate->format('Y-m-t'));
            } else {
                $targetDay = min($rule->dayOfMonth, (int) $nextDate->format('t'));
                $nextDate = $nextDate->setDate(
                    (int) $nextDate->format('Y'),
                    (int) $nextDate->format('m'),
                    $targetDay
                );
            }
        }

        if ($rule->time !== null) {
            $nextDate = $this->applyTime($nextDate, $rule->time);
        }

        return $nextDate;
    }

    /**
     * Apply time to a date.
     */
    private function applyTime(\DateTimeImmutable $date, string $time): \DateTimeImmutable
    {
        [$hour, $minute] = explode(':', $time);

        return $date->setTime((int) $hour, (int) $minute);
    }
}
