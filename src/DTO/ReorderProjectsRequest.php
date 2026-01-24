<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for reordering projects within a parent.
 */
final class ReorderProjectsRequest
{
    /**
     * @param string|null $parentId The parent project ID (null for root projects)
     * @param string[] $projectIds The project IDs in the desired order
     */
    public function __construct(
        #[Assert\Uuid(message: 'Parent ID must be a valid UUID')]
        public readonly ?string $parentId = null,

        #[Assert\NotBlank(message: 'Project IDs are required')]
        #[Assert\All([
            new Assert\Uuid(message: 'Each project ID must be a valid UUID'),
        ])]
        public readonly array $projectIds = [],
    ) {
    }

    /**
     * Creates a ReorderProjectsRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            parentId: isset($data['parentId']) && $data['parentId'] !== '' ? (string) $data['parentId'] : null,
            projectIds: isset($data['projectIds']) && is_array($data['projectIds'])
                ? array_map('strval', $data['projectIds'])
                : [],
        );
    }
}
