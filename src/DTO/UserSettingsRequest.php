<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for updating user settings.
 */
final class UserSettingsRequest
{
    /**
     * Valid date format choices.
     */
    public const VALID_DATE_FORMATS = ['MDY', 'DMY', 'YMD'];

    /**
     * Valid start of week choices.
     */
    public const VALID_START_OF_WEEK = [0, 1];

    public function __construct(
        #[Assert\Timezone(message: 'Invalid timezone')]
        public readonly ?string $timezone = null,

        #[Assert\Choice(choices: self::VALID_DATE_FORMATS, message: 'Invalid date format. Must be MDY, DMY, or YMD')]
        public readonly ?string $dateFormat = null,

        #[Assert\Choice(choices: self::VALID_START_OF_WEEK, message: 'Invalid start of week. Must be 0 (Sunday) or 1 (Monday)')]
        public readonly ?int $startOfWeek = null,
    ) {
    }

    /**
     * Creates a UserSettingsRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            timezone: isset($data['timezone']) ? (string) $data['timezone'] : null,
            dateFormat: isset($data['dateFormat']) ? (string) $data['dateFormat'] : null,
            startOfWeek: isset($data['startOfWeek']) ? (int) $data['startOfWeek'] : null,
        );
    }

    /**
     * Converts the DTO to an array of settings to merge, excluding null values.
     *
     * @return array<string, mixed>
     */
    public function toSettingsArray(): array
    {
        $settings = [];

        if ($this->timezone !== null) {
            $settings['timezone'] = $this->timezone;
        }

        if ($this->dateFormat !== null) {
            $settings['date_format'] = $this->dateFormat;
        }

        if ($this->startOfWeek !== null) {
            $settings['start_of_week'] = $this->startOfWeek;
        }

        return $settings;
    }
}
