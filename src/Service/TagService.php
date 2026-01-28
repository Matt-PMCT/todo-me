<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateTagRequest;
use App\DTO\UpdateTagRequest;
use App\Entity\Tag;
use App\Entity\User;
use App\Exception\EntityNotFoundException;
use App\Exception\ValidationException;
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
        private readonly ValidationHelper $validationHelper,
    ) {
    }

    /**
     * Find an existing tag or create a new one.
     *
     * @param User   $user The tag owner
     * @param string $name The tag name (will be stored lowercase)
     *
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
     * @param User   $user The tag owner
     * @param string $name The tag name to search for
     *
     * @return Tag|null The tag if found, null otherwise
     */
    public function findByName(User $user, string $name): ?Tag
    {
        return $this->tagRepository->findByNameInsensitive($user, strtolower(trim($name)));
    }

    /**
     * Find a tag by ID or throw exception if not found.
     *
     * @param string $id   The tag ID
     * @param User   $user The owner (for ownership validation)
     *
     * @return Tag The found tag
     *
     * @throws EntityNotFoundException If tag not found or not owned by user
     */
    public function findByIdOrFail(string $id, User $user): Tag
    {
        $tag = $this->tagRepository->findOneByOwnerAndId($user, $id);

        if ($tag === null) {
            throw EntityNotFoundException::tag($id);
        }

        return $tag;
    }

    /**
     * Create a new tag.
     *
     * @param User             $user The tag owner
     * @param CreateTagRequest $dto  The tag data
     *
     * @return Tag The created tag
     *
     * @throws ValidationException If validation fails or tag name already exists
     */
    public function create(User $user, CreateTagRequest $dto): Tag
    {
        $this->validationHelper->validate($dto);

        $normalizedName = strtolower(trim($dto->name));

        // Check if tag with this name already exists
        $existingTag = $this->tagRepository->findByNameInsensitive($user, $normalizedName);
        if ($existingTag !== null) {
            throw ValidationException::forField('name', 'A tag with this name already exists');
        }

        $tag = new Tag();
        $tag->setOwner($user);
        $tag->setName($normalizedName);

        if ($dto->color !== null) {
            $tag->setColor($dto->color);
        }

        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return $tag;
    }

    /**
     * Update an existing tag.
     *
     * @param Tag              $tag The tag to update
     * @param UpdateTagRequest $dto The update data
     *
     * @return Tag The updated tag
     *
     * @throws ValidationException If validation fails or new name already exists
     */
    public function update(Tag $tag, UpdateTagRequest $dto): Tag
    {
        $this->validationHelper->validate($dto);

        $owner = $tag->getOwner();
        if ($owner === null) {
            throw new \RuntimeException('Tag must have an owner');
        }

        if ($dto->name !== null) {
            $normalizedName = strtolower(trim($dto->name));

            // Check if another tag with this name exists
            $existingTag = $this->tagRepository->findByNameInsensitive($owner, $normalizedName);
            if ($existingTag !== null && $existingTag->getId() !== $tag->getId()) {
                throw ValidationException::forField('name', 'A tag with this name already exists');
            }

            $tag->setName($normalizedName);
        }

        if ($dto->color !== null) {
            $tag->setColor($dto->color);
        }

        $this->entityManager->flush();

        return $tag;
    }

    /**
     * Delete a tag.
     *
     * The tag will be removed from all associated tasks.
     *
     * @param Tag $tag The tag to delete
     */
    public function delete(Tag $tag): void
    {
        // Doctrine will handle removing the tag from all tasks via the many-to-many relationship
        $this->entityManager->remove($tag);
        $this->entityManager->flush();
    }
}
