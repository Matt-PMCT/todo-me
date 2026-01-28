<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for creating a new tag.
 */
final class CreateTagRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name is required', normalizer: 'trim')]
        #[Assert\Length(
            max: 100,
            maxMessage: 'Name cannot be longer than {{ limit }} characters'
        )]
        public readonly string $name = '',
        #[Assert\Regex(
            pattern: '/^#[0-9A-Fa-f]{6}$/',
            message: 'Color must be a valid hex color'
        )]
        public readonly ?string $color = null,
    ) {
    }

    /**
     * Creates a CreateTagRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            color: isset($data['color']) && $data['color'] !== '' ? (string) $data['color'] : null,
        );
    }
}
