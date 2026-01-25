<?php

declare(strict_types=1);

namespace App\Entity;

use App\Interface\UserOwnedInterface;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(columns: ['owner_id', 'read_at'], name: 'idx_notifications_owner_read')]
#[ORM\Index(columns: ['owner_id', 'created_at'], name: 'idx_notifications_owner_created')]
#[ORM\Index(columns: ['type'], name: 'idx_notifications_type')]
#[ORM\HasLifecycleCallbacks]
class Notification implements UserOwnedInterface
{
    public const TYPE_TASK_DUE_SOON = 'task_due_soon';
    public const TYPE_TASK_OVERDUE = 'task_overdue';
    public const TYPE_TASK_DUE_TODAY = 'task_due_today';
    public const TYPE_RECURRING_CREATED = 'recurring_created';
    public const TYPE_SYSTEM = 'system';

    public const TYPES = [
        self::TYPE_TASK_DUE_SOON,
        self::TYPE_TASK_OVERDUE,
        self::TYPE_TASK_DUE_TODAY,
        self::TYPE_RECURRING_CREATED,
        self::TYPE_SYSTEM,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $type;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(type: Types::JSON, options: ['default' => '{}'])]
    private array $data = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'read_at')]
    private ?\DateTimeImmutable $readAt = null;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid notification type "%s". Allowed values: %s',
                $type,
                implode(', ', self::TYPES)
            ));
        }

        $this->type = $type;

        return $this;
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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): static
    {
        $this->readAt = $readAt;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function markAsRead(): static
    {
        if ($this->readAt === null) {
            $this->readAt = new \DateTimeImmutable();
        }

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
