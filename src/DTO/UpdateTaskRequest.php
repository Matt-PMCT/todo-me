<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Task;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for updating an existing task.
 * All fields are optional - only provided fields will be updated.
 */
final class UpdateTaskRequest
{
    public function __construct(
        #[Assert\Length(
            max: 500,
            maxMessage: 'Title must be at most {{ limit }} characters'
        )]
        public readonly ?string $title = null,

        #[Assert\Length(
            max: 2000,
            maxMessage: 'Description must be at most {{ limit }} characters'
        )]
        public readonly ?string $description = null,

        #[Assert\Choice(
            choices: Task::STATUSES,
            message: 'Status must be one of: {{ choices }}'
        )]
        public readonly ?string $status = null,

        #[Assert\Range(
            min: Task::PRIORITY_MIN,
            max: Task::PRIORITY_MAX,
            notInRangeMessage: 'Priority must be between {{ min }} and {{ max }}'
        )]
        public readonly ?int $priority = null,

        public readonly ?string $dueDate = null,

        #[Assert\Uuid(message: 'Project ID must be a valid UUID')]
        public readonly ?string $projectId = null,

        /**
         * @var string[]|null
         */
        #[Assert\All([
            new Assert\Uuid(message: 'Each tag ID must be a valid UUID')
        ])]
        public readonly ?array $tagIds = null,

        /**
         * Flag to indicate if description should be cleared (set to null).
         */
        public readonly bool $clearDescription = false,

        /**
         * Flag to indicate if project should be cleared (set to null).
         */
        public readonly bool $clearProject = false,

        /**
         * Flag to indicate if due date should be cleared (set to null).
         */
        public readonly bool $clearDueDate = false,
    ) {
    }

    /**
     * Creates an UpdateTaskRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: isset($data['title']) ? (string) $data['title'] : null,
            description: isset($data['description']) ? (string) $data['description'] : null,
            status: isset($data['status']) ? (string) $data['status'] : null,
            priority: isset($data['priority']) ? (int) $data['priority'] : null,
            dueDate: isset($data['dueDate']) ? (string) $data['dueDate'] : null,
            projectId: isset($data['projectId']) ? (string) $data['projectId'] : null,
            tagIds: isset($data['tagIds']) && is_array($data['tagIds'])
                ? array_map('strval', $data['tagIds'])
                : null,
            clearDescription: (bool) ($data['clearDescription'] ?? false),
            clearProject: (bool) ($data['clearProject'] ?? false),
            clearDueDate: (bool) ($data['clearDueDate'] ?? false),
        );
    }

    /**
     * Check if any field has been set for update.
     */
    public function hasChanges(): bool
    {
        return $this->title !== null
            || $this->description !== null
            || $this->status !== null
            || $this->priority !== null
            || $this->dueDate !== null
            || $this->projectId !== null
            || $this->tagIds !== null
            || $this->clearDescription
            || $this->clearProject
            || $this->clearDueDate;
    }
}
