<?php

declare(strict_types=1);

namespace App\Message;

final class SendPushNotification
{
    public function __construct(
        private readonly string $userId,
        private readonly string $notificationId,
        private readonly string $type,
        private readonly string $title,
        private readonly ?string $message = null,
        private readonly array $data = [],
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getNotificationId(): string
    {
        return $this->notificationId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }
}
