<?php

declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\UndoAction;
use DateTimeImmutable;

final readonly class UndoToken
{
    private function __construct(
        public string $token,
        public string $action,
        public string $entityType,
        public string $entityId,
        public array $previousState,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * Create a new UndoToken.
     *
     * @param string $action The undo action (delete, update, status_change, archive)
     * @param string $entityType The type of entity (e.g., 'todo', 'category')
     * @param string $entityId The entity ID
     * @param array $previousState The previous state of the entity for restoration
     * @param int $ttl Time to live in seconds (default 60)
     */
    public static function create(
        string $action,
        string $entityType,
        string $entityId,
        array $previousState,
        int $ttl = 60
    ): self {
        $token = bin2hex(random_bytes(16)); // 32 characters
        $createdAt = new DateTimeImmutable();
        $expiresAt = $createdAt->modify("+{$ttl} seconds");

        return new self(
            token: $token,
            action: $action,
            entityType: $entityType,
            entityId: $entityId,
            previousState: $previousState,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
        );
    }

    /**
     * Serialize the token to an array.
     *
     * @return array{
     *     token: string,
     *     action: string,
     *     entityType: string,
     *     entityId: string,
     *     previousState: array,
     *     createdAt: string,
     *     expiresAt: string
     * }
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'action' => $this->action,
            'entityType' => $this->entityType,
            'entityId' => $this->entityId,
            'previousState' => $this->previousState,
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'expiresAt' => $this->expiresAt->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * Deserialize a token from an array.
     *
     * @param array{
     *     token: string,
     *     action: string,
     *     entityType: string,
     *     entityId: string,
     *     previousState: array,
     *     createdAt: string,
     *     expiresAt: string
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            token: $data['token'],
            action: $data['action'],
            entityType: $data['entityType'],
            entityId: $data['entityId'],
            previousState: $data['previousState'],
            createdAt: new DateTimeImmutable($data['createdAt']),
            expiresAt: new DateTimeImmutable($data['expiresAt']),
        );
    }

    /**
     * Check if the token has expired.
     */
    public function isExpired(): bool
    {
        return new DateTimeImmutable() > $this->expiresAt;
    }

    /**
     * Get the UndoAction enum value if valid.
     */
    public function getActionEnum(): ?UndoAction
    {
        return UndoAction::tryFrom($this->action);
    }

    /**
     * Get remaining time in seconds before expiration.
     */
    public function getRemainingSeconds(): int
    {
        $now = new DateTimeImmutable();
        $diff = $this->expiresAt->getTimestamp() - $now->getTimestamp();

        return max(0, $diff);
    }
}
