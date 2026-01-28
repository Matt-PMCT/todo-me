<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for updating a tag.
 */
final class UpdateTagRequest
{
    public function __construct(
        #[Assert\Length(
            max: 100,
            maxMessage: 'Name cannot be longer than {{ limit }} characters'
        )]
        public readonly ?string $name = null,
        #[Assert\Regex(
            pattern: '/^#[0-9A-Fa-f]{6}$/',
            message: 'Color must be a valid hex color'
        )]
        public readonly ?string $color = null,
    ) {
    }

    /**
     * Creates an UpdateTagRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: isset($data['name']) && $data['name'] !== '' ? (string) $data['name'] : null,
            color: isset($data['color']) && $data['color'] !== '' ? (string) $data['color'] : null,
        );
    }
}
