<?php

declare(strict_types=1);

namespace App\Entity;

use App\Interface\UserOwnedInterface;
use App\Repository\ActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: 'activity_logs')]
#[ORM\Index(columns: ['owner_id', 'created_at'], name: 'idx_activity_owner_created')]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_activity_entity')]
class ActivityLog implements UserOwnedInterface
{
    public const ACTION_TASK_CREATED = 'task_created';
    public const ACTION_TASK_UPDATED = 'task_updated';
    public const ACTION_TASK_COMPLETED = 'task_completed';
    public const ACTION_TASK_DELETED = 'task_deleted';
    public const ACTION_PROJECT_CREATED = 'project_created';
    public const ACTION_PROJECT_UPDATED = 'project_updated';
    public const ACTION_PROJECT_DELETED = 'project_deleted';

    public const ENTITY_TYPE_TASK = 'task';
    public const ENTITY_TYPE_PROJECT = 'project';

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $action;

    #[ORM\Column(type: Types::STRING, length: 50, name: 'entity_type')]
    private string $entityType;

    #[ORM\Column(type: 'guid', nullable: true, name: 'entity_id')]
    private ?string $entityId = null;

    #[ORM\Column(type: Types::STRING, length: 255, name: 'entity_title')]
    private string $entityTitle;

    #[ORM\Column(type: Types::JSON)]
    private array $changes = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): static
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): static
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getEntityTitle(): string
    {
        return $this->entityTitle;
    }

    public function setEntityTitle(string $entityTitle): static
    {
        $this->entityTitle = $entityTitle;

        return $this;
    }

    public function getChanges(): array
    {
        return $this->changes;
    }

    public function setChanges(array $changes): static
    {
        $this->changes = $changes;

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
}
