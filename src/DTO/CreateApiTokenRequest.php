<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Request DTO for creating a new API token.
 */
final class CreateApiTokenRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name is required')]
        #[Assert\Length(
            max: 100,
            maxMessage: 'Name must be at most {{ limit }} characters'
        )]
        public readonly string $name = '',

        /**
         * Token scopes. Null means all access (["*"]).
         *
         * @var string[]|null
         */
        public readonly ?array $scopes = null,

        /**
         * Expiration date in ISO 8601 format.
         */
        public readonly ?string $expiresAt = null,
    ) {
    }

    /**
     * Creates a CreateApiTokenRequest from an array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            scopes: isset($data['scopes']) && is_array($data['scopes'])
                ? array_map('strval', $data['scopes'])
                : null,
            expiresAt: isset($data['expiresAt']) ? (string) $data['expiresAt'] : null,
        );
    }

    /**
     * Gets the scopes, defaulting to ['*'] if not specified.
     *
     * @return string[]
     */
    public function getScopes(): array
    {
        return $this->scopes ?? ['*'];
    }

    /**
     * Parses the expiresAt string into a DateTimeImmutable.
     *
     * @return \DateTimeImmutable|null
     */
    public function getExpiresAtDateTime(): ?\DateTimeImmutable
    {
        if ($this->expiresAt === null || $this->expiresAt === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($this->expiresAt);
        } catch (\Exception $e) {
            return null;
        }
    }
}
