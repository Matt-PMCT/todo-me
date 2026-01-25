<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for a single batch operation.
 */
final class BatchOperationRequest
{
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';
    public const ACTION_COMPLETE = 'complete';
    public const ACTION_RESCHEDULE = 'reschedule';

    public const VALID_ACTIONS = [
        self::ACTION_CREATE,
        self::ACTION_UPDATE,
        self::ACTION_DELETE,
        self::ACTION_COMPLETE,
        self::ACTION_RESCHEDULE,
    ];

    public function __construct(
        #[Assert\NotBlank(message: 'Action is required')]
        #[Assert\Choice(
            choices: self::VALID_ACTIONS,
            message: 'Action must be one of: {{ choices }}'
        )]
        public readonly string $action,

        /**
         * Task ID - required for update, delete, complete, reschedule actions.
         */
        #[Assert\Uuid(message: 'Task ID must be a valid UUID')]
        public readonly ?string $taskId = null,

        /**
         * Operation data - required for create, update, reschedule actions.
         *
         * @var array<string, mixed>
         */
        public readonly array $data = [],
    ) {
    }

    /**
     * Creates a BatchOperationRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            action: (string) ($data['action'] ?? ''),
            taskId: isset($data['taskId']) ? (string) $data['taskId'] : null,
            data: isset($data['data']) && is_array($data['data']) ? $data['data'] : [],
        );
    }

    /**
     * Validates that the operation has all required fields.
     *
     * @return array<string, string> Validation errors
     */
    public function validateRequirements(): array
    {
        $errors = [];

        // Actions that require taskId
        $actionsRequiringTaskId = [
            self::ACTION_UPDATE,
            self::ACTION_DELETE,
            self::ACTION_COMPLETE,
            self::ACTION_RESCHEDULE,
        ];

        if (in_array($this->action, $actionsRequiringTaskId, true) && $this->taskId === null) {
            $errors['taskId'] = 'Task ID is required for '.$this->action.' action';
        }

        // Actions that require data
        if ($this->action === self::ACTION_CREATE && empty($this->data)) {
            $errors['data'] = 'Data is required for create action';
        }

        if ($this->action === self::ACTION_RESCHEDULE && !isset($this->data['due_date'])) {
            $errors['data.due_date'] = 'due_date is required for reschedule action';
        }

        return $errors;
    }
}
