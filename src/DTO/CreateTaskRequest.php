<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Task;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for creating a new task.
 */
final class CreateTaskRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Title is required')]
        #[Assert\Length(
            max: 500,
            maxMessage: 'Title must be at most {{ limit }} characters'
        )]
        public readonly string $title = '',

        #[Assert\Length(
            max: 2000,
            maxMessage: 'Description must be at most {{ limit }} characters'
        )]
        public readonly ?string $description = null,

        #[Assert\Choice(
            choices: Task::STATUSES,
            message: 'Status must be one of: {{ choices }}'
        )]
        public readonly string $status = Task::STATUS_PENDING,

        #[Assert\Range(
            min: Task::PRIORITY_MIN,
            max: Task::PRIORITY_MAX,
            notInRangeMessage: 'Priority must be between {{ min }} and {{ max }}'
        )]
        public readonly int $priority = Task::PRIORITY_DEFAULT,

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
    ) {
    }

    /**
     * Creates a CreateTaskRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: (string) ($data['title'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            status: (string) ($data['status'] ?? Task::STATUS_PENDING),
            priority: isset($data['priority']) ? (int) $data['priority'] : Task::PRIORITY_DEFAULT,
            dueDate: isset($data['dueDate']) ? (string) $data['dueDate'] : null,
            projectId: isset($data['projectId']) ? (string) $data['projectId'] : null,
            tagIds: isset($data['tagIds']) && is_array($data['tagIds'])
                ? array_map('strval', $data['tagIds'])
                : null,
        );
    }
}
