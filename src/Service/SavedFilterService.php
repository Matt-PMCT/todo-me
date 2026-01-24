<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreateSavedFilterRequest;
use App\DTO\UpdateSavedFilterRequest;
use App\Entity\SavedFilter;
use App\Entity\User;
use App\Exception\EntityNotFoundException;
use App\Exception\ForbiddenException;
use App\Interface\OwnershipCheckerInterface;
use App\Repository\SavedFilterRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for saved filter operations.
 *
 * Handles CRUD operations for saved filters including position management
 * and default filter handling.
 */
final class SavedFilterService
{
    public function __construct(
        private readonly SavedFilterRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidationHelper $validationHelper,
        private readonly OwnershipCheckerInterface $ownershipChecker,
    ) {
    }

    /**
     * Creates a new saved filter.
     *
     * @param User $user The filter owner
     * @param CreateSavedFilterRequest $dto The filter creation data
     * @return SavedFilter The created filter
     */
    public function create(User $user, CreateSavedFilterRequest $dto): SavedFilter
    {
        $this->validationHelper->validate($dto);

        $filter = new SavedFilter();
        $filter->setOwner($user);
        $filter->setName($dto->name);
        $filter->setCriteria($dto->criteria);
        $filter->setIcon($dto->icon);
        $filter->setColor($dto->color);

        // Set position to max + 1
        $maxPosition = $this->repository->getMaxPosition($user);
        $filter->setPosition($maxPosition + 1);

        // Handle default setting
        if ($dto->isDefault) {
            $this->clearDefaultForOwner($user);
            $filter->setIsDefault(true);
        }

        $this->entityManager->persist($filter);
        $this->entityManager->flush();

        return $filter;
    }

    /**
     * Updates an existing saved filter.
     *
     * @param SavedFilter $filter The filter to update
     * @param UpdateSavedFilterRequest $dto The update data
     * @return SavedFilter The updated filter
     */
    public function update(SavedFilter $filter, UpdateSavedFilterRequest $dto): SavedFilter
    {
        $this->validationHelper->validate($dto);

        if ($dto->name !== null) {
            $filter->setName($dto->name);
        }

        if ($dto->criteria !== null) {
            $filter->setCriteria($dto->criteria);
        }

        if ($dto->isDefault !== null) {
            if ($dto->isDefault) {
                $this->clearDefaultForOwner($filter->getOwner());
                $filter->setIsDefault(true);
            } else {
                $filter->setIsDefault(false);
            }
        }

        if ($dto->icon !== null) {
            $filter->setIcon($dto->icon);
        }

        if ($dto->color !== null) {
            $filter->setColor($dto->color);
        }

        $this->entityManager->flush();

        return $filter;
    }

    /**
     * Deletes a saved filter.
     *
     * @param SavedFilter $filter The filter to delete
     */
    public function delete(SavedFilter $filter): void
    {
        $this->entityManager->remove($filter);
        $this->entityManager->flush();
    }

    /**
     * Sets a filter as the default for its owner.
     *
     * @param SavedFilter $filter The filter to set as default
     * @return SavedFilter The updated filter
     */
    public function setAsDefault(SavedFilter $filter): SavedFilter
    {
        $this->clearDefaultForOwner($filter->getOwner());
        $filter->setIsDefault(true);
        $this->entityManager->flush();

        return $filter;
    }

    /**
     * Reorders filters for a user.
     *
     * @param User $user The filter owner
     * @param string[] $filterIds The filter IDs in the desired order
     */
    public function reorder(User $user, array $filterIds): void
    {
        $this->repository->reorderFilters($user, $filterIds);
    }

    /**
     * Finds a filter by ID and verifies ownership.
     *
     * @param string $id The filter ID
     * @param User $user The expected owner
     * @return SavedFilter The filter
     * @throws EntityNotFoundException If the filter is not found
     * @throws ForbiddenException If the user doesn't own the filter
     */
    public function findByIdOrFail(string $id, User $user): SavedFilter
    {
        $filter = $this->repository->find($id);

        if ($filter === null) {
            throw new EntityNotFoundException('SavedFilter', $id);
        }

        if (!$this->ownershipChecker->isOwner($filter, $user)) {
            throw ForbiddenException::notOwner('SavedFilter');
        }

        return $filter;
    }

    /**
     * Clears the default flag from any existing default filter for the owner.
     */
    private function clearDefaultForOwner(User $owner): void
    {
        $current = $this->repository->findDefaultByOwner($owner);
        if ($current !== null) {
            $current->setIsDefault(false);
        }
    }
}
