<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Request DTO for updating project settings.
 */
final class ProjectSettingsRequest
{
    public function __construct(
        public readonly ?bool $showChildrenTasks = null,
    ) {
    }

    /**
     * Creates a ProjectSettingsRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            showChildrenTasks: isset($data['showChildrenTasks']) ? (bool) $data['showChildrenTasks'] : null,
        );
    }

    /**
     * Check if any fields were provided for update.
     */
    public function hasChanges(): bool
    {
        return $this->showChildrenTasks !== null;
    }
}
