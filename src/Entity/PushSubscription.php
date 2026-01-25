<?php

declare(strict_types=1);

namespace App\Entity;

use App\Interface\UserOwnedInterface;
use App\Repository\PushSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
#[ORM\Table(name: 'push_subscriptions')]
#[ORM\Index(columns: ['owner_id'], name: 'idx_push_subscriptions_owner')]
#[ORM\UniqueConstraint(name: 'uniq_push_subscription_endpoint', columns: ['endpoint_hash'])]
#[ORM\HasLifecycleCallbacks]
class PushSubscription implements UserOwnedInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $endpoint;

    #[ORM\Column(type: Types::STRING, length: 64, name: 'endpoint_hash')]
    private string $endpointHash;

    #[ORM\Column(type: Types::STRING, length: 255, name: 'public_key')]
    private string $publicKey;

    #[ORM\Column(type: Types::STRING, length: 255, name: 'auth_token')]
    private string $authToken;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, name: 'user_agent')]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, name: 'last_used_at')]
    private ?\DateTimeImmutable $lastUsedAt = null;

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

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): static
    {
        $this->endpoint = $endpoint;
        $this->endpointHash = hash('sha256', $endpoint);

        return $this;
    }

    public function getEndpointHash(): string
    {
        return $this->endpointHash;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): static
    {
        $this->publicKey = $publicKey;

        return $this;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function setAuthToken(string $authToken): static
    {
        $this->authToken = $authToken;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

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

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }

    public function markAsUsed(): static
    {
        $this->lastUsedAt = new \DateTimeImmutable();

        return $this;
    }
}
