<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tag;
use App\Entity\User;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for tag-related operations.
 */
class TagService
{
    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Find an existing tag or create a new one.
     *
     * @param User $user The tag owner
     * @param string $name The tag name (will be stored lowercase)
     * @return array{tag: Tag, created: bool}
     */
    public function findOrCreate(User $user, string $name): array
    {
        $normalizedName = strtolower(trim($name));

        // Look up tag by name (case-insensitive)
        $tag = $this->tagRepository->findByNameInsensitive($user, $normalizedName);

        if ($tag !== null) {
            return [
                'tag' => $tag,
                'created' => false,
            ];
        }

        // Create new tag with lowercase name
        $tag = new Tag();
        $tag->setOwner($user);
        $tag->setName($normalizedName);

        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return [
            'tag' => $tag,
            'created' => true,
        ];
    }

    /**
     * Find a tag by name (case-insensitive).
     *
     * @param User $user The tag owner
     * @param string $name The tag name to search for
     * @return Tag|null The tag if found, null otherwise
     */
    public function findByName(User $user, string $name): ?Tag
    {
        return $this->tagRepository->findByNameInsensitive($user, strtolower(trim($name)));
    }
}
