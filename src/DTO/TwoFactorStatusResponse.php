<?php

declare(strict_types=1);

namespace App\DTO;

final class TwoFactorStatusResponse
{
    public function __construct(
        public readonly bool $enabled,
        public readonly ?string $enabledAt,
        public readonly int $backupCodesRemaining,
        public readonly ?string $backupCodesGeneratedAt,
    ) {
    }

    /**
     * @param array{enabled: bool, enabledAt: string|null, backupCodesRemaining: int, backupCodesGeneratedAt: string|null} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: $data['enabled'],
            enabledAt: $data['enabledAt'],
            backupCodesRemaining: $data['backupCodesRemaining'],
            backupCodesGeneratedAt: $data['backupCodesGeneratedAt'],
        );
    }

    /**
     * @return array{enabled: bool, enabledAt: string|null, backupCodesRemaining: int, backupCodesGeneratedAt: string|null}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'enabledAt' => $this->enabledAt,
            'backupCodesRemaining' => $this->backupCodesRemaining,
            'backupCodesGeneratedAt' => $this->backupCodesGeneratedAt,
        ];
    }
}
