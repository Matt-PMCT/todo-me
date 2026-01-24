<?php

declare(strict_types=1);

namespace App\Service\Parser;

use DateTimeImmutable;
use DateTimeZone;

class TimezoneHelper
{
    /**
     * Convert a date from a user's timezone to UTC.
     */
    public function resolveToUtc(DateTimeImmutable $date, string $timezone): DateTimeImmutable
    {
        $userTz = new DateTimeZone($timezone);
        $utcTz = new DateTimeZone('UTC');

        // If the date already has UTC timezone, return as-is
        if ($date->getTimezone()->getName() === 'UTC') {
            return $date;
        }

        // Create the date in the user's timezone, then convert to UTC
        $dateInUserTz = $date->setTimezone($userTz);

        return $dateInUserTz->setTimezone($utcTz);
    }

    /**
     * Convert a date from UTC to a user's timezone.
     */
    public function resolveFromUtc(DateTimeImmutable $date, string $timezone): DateTimeImmutable
    {
        $userTz = new DateTimeZone($timezone);

        return $date->setTimezone($userTz);
    }

    /**
     * Get the start of the current day in the specified timezone.
     */
    public function getStartOfDay(string $timezone): DateTimeImmutable
    {
        $userTz = new DateTimeZone($timezone);

        return new DateTimeImmutable('today', $userTz);
    }

    /**
     * Get the current time in the specified timezone.
     */
    public function getNow(string $timezone): DateTimeImmutable
    {
        $userTz = new DateTimeZone($timezone);

        return new DateTimeImmutable('now', $userTz);
    }
}
