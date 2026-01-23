<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for creating a new project.
 */
final class CreateProjectRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name is required')]
        #[Assert\Length(
            max: 100,
            maxMessage: 'Name cannot be longer than {{ limit }} characters'
        )]
        public readonly string $name = '',

        #[Assert\Length(
            max: 500,
            maxMessage: 'Description cannot be longer than {{ limit }} characters'
        )]
        public readonly ?string $description = null,
    ) {
    }

    /**
     * Creates a CreateProjectRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
        );
    }
}
