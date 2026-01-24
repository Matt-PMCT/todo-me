<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SavedFilter;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SavedFilter>
 *
 * @method SavedFilter|null find($id, $lockMode = null, $lockVersion = null)
 * @method SavedFilter|null findOneBy(array $criteria, array $orderBy = null)
 * @method SavedFilter[]    findAll()
 * @method SavedFilter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SavedFilterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SavedFilter::class);
    }

    /**
     * Find all saved filters for the owner.
     *
     * @return SavedFilter[]
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('f.position', 'ASC')
            ->addOrderBy('f.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a single filter by owner and ID.
     */
    public function findOneByOwnerAndId(User $owner, string $id): ?SavedFilter
    {
        return $this->findOneBy(['owner' => $owner, 'id' => $id]);
    }

    /**
     * Find the default filter for the owner.
     */
    public function findDefaultByOwner(User $owner): ?SavedFilter
    {
        return $this->findOneBy(['owner' => $owner, 'isDefault' => true]);
    }

    /**
     * Get the maximum position value for the owner's filters.
     *
     * @return int The maximum position, or -1 if no filters exist
     */
    public function getMaxPosition(User $owner): int
    {
        $result = $this->createQueryBuilder('f')
            ->select('MAX(f.position)')
            ->where('f.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (int) $result : -1;
    }

    /**
     * Reorder filters by updating their positions based on the provided filter IDs.
     *
     * @param string[] $filterIds The filter IDs in the desired order
     */
    public function reorderFilters(User $owner, array $filterIds): void
    {
        if (empty($filterIds)) {
            return;
        }

        $em = $this->getEntityManager();

        // Single batch query to fetch all filters
        $filters = $this->findByOwnerAndIds($owner, $filterIds);

        // Index filters by ID for O(1) lookup
        $filterMap = [];
        foreach ($filters as $filter) {
            $filterMap[$filter->getId()] = $filter;
        }

        // Update positions based on the provided order
        foreach ($filterIds as $position => $filterId) {
            if (isset($filterMap[$filterId])) {
                $filterMap[$filterId]->setPosition($position);
            }
        }

        $em->flush();
    }

    /**
     * Find filters by owner and multiple IDs.
     *
     * @param string[] $ids The filter IDs
     * @return SavedFilter[]
     */
    public function findByOwnerAndIds(User $owner, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('f')
            ->where('f.owner = :owner')
            ->andWhere('f.id IN (:ids)')
            ->setParameter('owner', $owner)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function save(SavedFilter $filter, bool $flush = false): void
    {
        $this->getEntityManager()->persist($filter);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(SavedFilter $filter, bool $flush = false): void
    {
        $this->getEntityManager()->remove($filter);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
