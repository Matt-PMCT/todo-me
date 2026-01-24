<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for moving a project to a new parent.
 */
final class MoveProjectRequest
{
    public function __construct(
        #[Assert\Uuid(message: 'Parent ID must be a valid UUID')]
        public readonly ?string $parentId = null,

        #[Assert\PositiveOrZero(message: 'Position must be a non-negative integer')]
        public readonly ?int $position = null,
    ) {
    }

    /**
     * Creates a MoveProjectRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            parentId: isset($data['parentId']) && $data['parentId'] !== '' ? (string) $data['parentId'] : null,
            position: isset($data['position']) ? (int) $data['position'] : null,
        );
    }
}
