<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class NotificationPreferencesRequest
{
    public function __construct(
        #[Assert\Type('bool')]
        public readonly ?bool $emailEnabled = null,

        #[Assert\Type('bool')]
        public readonly ?bool $pushEnabled = null,

        #[Assert\Type('bool')]
        public readonly ?bool $taskDueSoon = null,

        #[Assert\Type('bool')]
        public readonly ?bool $taskOverdue = null,

        #[Assert\Type('bool')]
        public readonly ?bool $taskDueToday = null,

        #[Assert\Type('bool')]
        public readonly ?bool $recurringCreated = null,

        #[Assert\Type('bool')]
        public readonly ?bool $quietHoursEnabled = null,

        #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/')]
        public readonly ?string $quietHoursStart = null,

        #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/')]
        public readonly ?string $quietHoursEnd = null,

        #[Assert\Choice(choices: [1, 2, 4, 8, 12, 24, 48])]
        public readonly ?int $dueSoonHours = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            emailEnabled: isset($data['emailEnabled']) ? (bool) $data['emailEnabled'] : null,
            pushEnabled: isset($data['pushEnabled']) ? (bool) $data['pushEnabled'] : null,
            taskDueSoon: isset($data['taskDueSoon']) ? (bool) $data['taskDueSoon'] : null,
            taskOverdue: isset($data['taskOverdue']) ? (bool) $data['taskOverdue'] : null,
            taskDueToday: isset($data['taskDueToday']) ? (bool) $data['taskDueToday'] : null,
            recurringCreated: isset($data['recurringCreated']) ? (bool) $data['recurringCreated'] : null,
            quietHoursEnabled: isset($data['quietHoursEnabled']) ? (bool) $data['quietHoursEnabled'] : null,
            quietHoursStart: isset($data['quietHoursStart']) ? (string) $data['quietHoursStart'] : null,
            quietHoursEnd: isset($data['quietHoursEnd']) ? (string) $data['quietHoursEnd'] : null,
            dueSoonHours: isset($data['dueSoonHours']) ? (int) $data['dueSoonHours'] : null,
        );
    }

    /**
     * Convert to array, excluding null values.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->emailEnabled !== null) {
            $data['emailEnabled'] = $this->emailEnabled;
        }
        if ($this->pushEnabled !== null) {
            $data['pushEnabled'] = $this->pushEnabled;
        }
        if ($this->taskDueSoon !== null) {
            $data['taskDueSoon'] = $this->taskDueSoon;
        }
        if ($this->taskOverdue !== null) {
            $data['taskOverdue'] = $this->taskOverdue;
        }
        if ($this->taskDueToday !== null) {
            $data['taskDueToday'] = $this->taskDueToday;
        }
        if ($this->recurringCreated !== null) {
            $data['recurringCreated'] = $this->recurringCreated;
        }
        if ($this->quietHoursEnabled !== null) {
            $data['quietHoursEnabled'] = $this->quietHoursEnabled;
        }
        if ($this->quietHoursStart !== null) {
            $data['quietHoursStart'] = $this->quietHoursStart;
        }
        if ($this->quietHoursEnd !== null) {
            $data['quietHoursEnd'] = $this->quietHoursEnd;
        }
        if ($this->dueSoonHours !== null) {
            $data['dueSoonHours'] = $this->dueSoonHours;
        }

        return $data;
    }
}
