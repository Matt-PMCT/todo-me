<?php

declare(strict_types=1);

namespace App\Entity;

use App\Interface\UserOwnedInterface;
use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'tasks')]
#[ORM\Index(columns: ['owner_id'], name: 'idx_tasks_owner')]
#[ORM\Index(columns: ['project_id'], name: 'idx_tasks_project')]
#[ORM\Index(columns: ['status'], name: 'idx_tasks_status')]
#[ORM\Index(columns: ['priority'], name: 'idx_tasks_priority')]
#[ORM\Index(columns: ['due_date'], name: 'idx_tasks_due_date')]
#[ORM\Index(columns: ['position'], name: 'idx_tasks_position')]
#[ORM\Index(columns: ['status', 'priority'], name: 'idx_tasks_status_priority')]
#[ORM\Index(columns: ['owner_id', 'status'], name: 'idx_tasks_owner_status')]
#[ORM\Index(columns: ['parent_task_id'], name: 'idx_tasks_parent')]
#[ORM\Index(columns: ['original_task_id'], name: 'idx_tasks_original')]
#[ORM\Index(columns: ['owner_id', 'is_recurring'], name: 'idx_tasks_owner_recurring')]
#[ORM\Index(columns: ['owner_id', 'parent_task_id'], name: 'idx_tasks_owner_parent')]
#[ORM\HasLifecycleCallbacks]
class Task implements UserOwnedInterface
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
    ];

    public const PRIORITY_MIN = 0;
    public const PRIORITY_MAX = 4;
    public const PRIORITY_DEFAULT = 2;

    public const OVERDUE_SEVERITY_LOW = 'low';
    public const OVERDUE_SEVERITY_MEDIUM = 'medium';
    public const OVERDUE_SEVERITY_HIGH = 'high';

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => self::STATUS_PENDING])]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => self::PRIORITY_DEFAULT])]
    private int $priority = self::PRIORITY_DEFAULT;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true, name: 'due_date')]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true, name: 'due_time')]
    private ?\DateTimeImmutable $dueTime = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'completed_at')]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(name: 'project_id', nullable: true, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: Task::class, inversedBy: 'subtasks')]
    #[ORM\JoinColumn(name: 'parent_task_id', nullable: true, onDelete: 'CASCADE')]
    private ?Task $parentTask = null;

    /**
     * @var Collection<int, Task>
     */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'parentTask')]
    private Collection $subtasks;

    #[ORM\ManyToOne(targetEntity: Task::class)]
    #[ORM\JoinColumn(name: 'original_task_id', nullable: true, onDelete: 'SET NULL')]
    private ?Task $originalTask = null;

    #[ORM\Column(type: Types::BOOLEAN, name: 'is_recurring', options: ['default' => false])]
    private bool $isRecurring = false;

    #[ORM\Column(type: Types::TEXT, name: 'recurrence_rule', nullable: true)]
    private ?string $recurrenceRule = null;

    #[ORM\Column(type: Types::STRING, length: 20, name: 'recurrence_type', nullable: true)]
    private ?string $recurrenceType = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, name: 'recurrence_end_date', nullable: true)]
    private ?\DateTimeImmutable $recurrenceEndDate = null;

    /**
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'tasks')]
    #[ORM\JoinTable(name: 'task_tag')]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $tags;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->subtasks = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid status "%s". Allowed values: %s',
                $status,
                implode(', ', self::STATUSES)
            ));
        }

        $previousStatus = $this->status;
        $this->status = $status;

        // Set completedAt when status changes to completed
        if ($status === self::STATUS_COMPLETED && $previousStatus !== self::STATUS_COMPLETED) {
            $this->completedAt = new \DateTimeImmutable();
        } elseif ($status !== self::STATUS_COMPLETED) {
            $this->completedAt = null;
        }

        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        if ($priority < self::PRIORITY_MIN || $priority > self::PRIORITY_MAX) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid priority %d. Allowed range: %d-%d',
                $priority,
                self::PRIORITY_MIN,
                self::PRIORITY_MAX
            ));
        }

        $this->priority = $priority;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    /**
     * @return Collection<int, Tag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isOverdue(): bool
    {
        if ($this->dueDate === null || $this->isCompleted()) {
            return false;
        }

        return $this->dueDate < new \DateTimeImmutable('today');
    }

    /**
     * Returns the number of days the task is overdue.
     *
     * @return int|null Number of days overdue, or null if not overdue
     */
    public function getOverdueDays(): ?int
    {
        if ($this->dueDate === null || $this->isCompleted()) {
            return null;
        }

        $today = new \DateTimeImmutable('today');

        if ($this->dueDate >= $today) {
            return null;
        }

        return (int) $this->dueDate->diff($today)->days;
    }

    /**
     * Returns the severity level based on how many days overdue.
     *
     * @return string|null Severity level (low, medium, high), or null if not overdue
     */
    public function getOverdueSeverity(): ?string
    {
        $days = $this->getOverdueDays();

        if ($days === null) {
            return null;
        }

        if ($days <= 2) {
            return self::OVERDUE_SEVERITY_LOW;
        }

        if ($days <= 7) {
            return self::OVERDUE_SEVERITY_MEDIUM;
        }

        return self::OVERDUE_SEVERITY_HIGH;
    }

    public function getDueTime(): ?\DateTimeImmutable
    {
        return $this->dueTime;
    }

    public function setDueTime(?\DateTimeImmutable $dueTime): static
    {
        $this->dueTime = $dueTime;

        return $this;
    }

    public function getParentTask(): ?Task
    {
        return $this->parentTask;
    }

    public function setParentTask(?Task $parentTask): static
    {
        $this->parentTask = $parentTask;

        return $this;
    }

    /**
     * @return Collection<int, Task>
     */
    public function getSubtasks(): Collection
    {
        return $this->subtasks;
    }

    public function addSubtask(Task $subtask): static
    {
        if (!$this->subtasks->contains($subtask)) {
            $this->subtasks->add($subtask);
            $subtask->setParentTask($this);
        }

        return $this;
    }

    public function removeSubtask(Task $subtask): static
    {
        if ($this->subtasks->removeElement($subtask)) {
            if ($subtask->getParentTask() === $this) {
                $subtask->setParentTask(null);
            }
        }

        return $this;
    }

    public function getOriginalTask(): ?Task
    {
        return $this->originalTask;
    }

    public function setOriginalTask(?Task $originalTask): static
    {
        $this->originalTask = $originalTask;

        return $this;
    }

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function setIsRecurring(bool $isRecurring): static
    {
        $this->isRecurring = $isRecurring;

        return $this;
    }

    public function getRecurrenceRule(): ?string
    {
        return $this->recurrenceRule;
    }

    public function setRecurrenceRule(?string $recurrenceRule): static
    {
        $this->recurrenceRule = $recurrenceRule;

        return $this;
    }

    public function getRecurrenceType(): ?string
    {
        return $this->recurrenceType;
    }

    public function setRecurrenceType(?string $recurrenceType): static
    {
        $this->recurrenceType = $recurrenceType;

        return $this;
    }

    public function getRecurrenceEndDate(): ?\DateTimeImmutable
    {
        return $this->recurrenceEndDate;
    }

    public function setRecurrenceEndDate(?\DateTimeImmutable $recurrenceEndDate): static
    {
        $this->recurrenceEndDate = $recurrenceEndDate;

        return $this;
    }

    /**
     * Restores task state from a serialized undo state array.
     *
     * This method directly sets properties without triggering side effects
     * (like auto-setting completedAt) because we're restoring exact state.
     * Validation is still performed to ensure data integrity.
     *
     * @param array<string, mixed> $state The state to restore
     * @internal Only for use by TaskService undo operations
     */
    public function restoreFromState(array $state): void
    {
        if (isset($state['title'])) {
            $this->title = $state['title'];
        }

        if (array_key_exists('description', $state)) {
            $this->description = $state['description'];
        }

        if (isset($state['status'])) {
            // Validate status value but don't trigger completedAt logic
            if (!in_array($state['status'], self::STATUSES, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid status "%s". Allowed values: %s',
                    $state['status'],
                    implode(', ', self::STATUSES)
                ));
            }
            $this->status = $state['status'];
        }

        if (isset($state['priority'])) {
            // Validate priority
            if ($state['priority'] < self::PRIORITY_MIN || $state['priority'] > self::PRIORITY_MAX) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid priority %d. Allowed range: %d-%d',
                    $state['priority'],
                    self::PRIORITY_MIN,
                    self::PRIORITY_MAX
                ));
            }
            $this->priority = $state['priority'];
        }

        if (array_key_exists('dueDate', $state)) {
            $this->dueDate = $state['dueDate'] !== null
                ? new \DateTimeImmutable($state['dueDate'])
                : null;
        }

        if (array_key_exists('dueTime', $state)) {
            $this->dueTime = $state['dueTime'] !== null
                ? new \DateTimeImmutable($state['dueTime'])
                : null;
        }

        if (isset($state['position'])) {
            $this->position = $state['position'];
        }

        if (array_key_exists('completedAt', $state)) {
            $this->completedAt = $state['completedAt'] !== null
                ? new \DateTimeImmutable($state['completedAt'])
                : null;
        }

        if (array_key_exists('isRecurring', $state)) {
            $this->isRecurring = (bool) $state['isRecurring'];
        }

        if (array_key_exists('recurrenceRule', $state)) {
            $this->recurrenceRule = $state['recurrenceRule'];
        }

        if (array_key_exists('recurrenceType', $state)) {
            $this->recurrenceType = $state['recurrenceType'];
        }

        if (array_key_exists('recurrenceEndDate', $state)) {
            $this->recurrenceEndDate = $state['recurrenceEndDate'] !== null
                ? new \DateTimeImmutable($state['recurrenceEndDate'])
                : null;
        }
    }
}
