<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 *
 * @method Tag|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tag|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tag[]    findAll()
 * @method Tag[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * @return Tag[]
     */
    public function findByOwner(User $owner): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByOwnerAndName(User $owner, string $name): ?Tag
    {
        return $this->findOneBy(['owner' => $owner, 'name' => $name]);
    }

    public function findOneByOwnerAndId(User $owner, string $id): ?Tag
    {
        return $this->findOneBy(['owner' => $owner, 'id' => $id]);
    }

    /**
     * @param string[] $names
     *
     * @return Tag[]
     */
    public function findByOwnerAndNames(User $owner, array $names): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.owner = :owner')
            ->andWhere('t.name IN (:names)')
            ->setParameter('owner', $owner)
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();
    }

    public function save(Tag $tag, bool $flush = false): void
    {
        $this->getEntityManager()->persist($tag);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Tag $tag, bool $flush = false): void
    {
        $this->getEntityManager()->remove($tag);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find a tag by name (case-insensitive).
     *
     * @param User   $owner The tag owner
     * @param string $name  The tag name to search for
     *
     * @return Tag|null The tag if found, null otherwise
     */
    public function findByNameInsensitive(User $owner, string $name): ?Tag
    {
        return $this->createQueryBuilder('t')
            ->where('t.owner = :owner')
            ->andWhere('LOWER(t.name) = LOWER(:name)')
            ->setParameter('owner', $owner)
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Search tags by name prefix for autocomplete.
     *
     * @param User   $owner  The tag owner
     * @param string $prefix The name prefix to search for
     * @param int    $limit  Maximum number of results to return
     *
     * @return Tag[]
     */
    public function searchByPrefix(User $owner, string $prefix, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.owner = :owner')
            ->andWhere('LOWER(t.name) LIKE LOWER(:prefix)')
            ->setParameter('owner', $owner)
            ->setParameter('prefix', $prefix.'%')
            ->orderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search tags by name (case-insensitive ILIKE search).
     *
     * @param User   $owner The tag owner
     * @param string $query The search query
     * @param int    $limit Maximum number of results
     *
     * @return Tag[] Matching tags
     */
    public function searchByName(User $owner, string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.owner = :owner')
            ->andWhere('LOWER(t.name) LIKE LOWER(:query)')
            ->setParameter('owner', $owner)
            ->setParameter('query', '%'.$query.'%')
            ->orderBy('t.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tags ordered by most recently used (based on task update time).
     *
     * @param User $owner The tag owner
     * @param int  $limit Maximum number of tags to return
     *
     * @return Tag[] Tags ordered by recent usage
     */
    public function findRecentlyUsedByOwner(User $owner, int $limit = 10): array
    {
        return $this->createQueryBuilder('t')
            ->select('t', 'MAX(task.updatedAt) as HIDDEN lastUsed')
            ->leftJoin('t.tasks', 'task')
            ->where('t.owner = :owner')
            ->setParameter('owner', $owner)
            ->groupBy('t.id')
            ->orderBy('lastUsed', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total tags for a user.
     *
     * @param User $owner The tag owner
     *
     * @return int Total tag count
     */
    public function countByOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find tags with pagination.
     *
     * @param User   $owner  The tag owner
     * @param int    $page   Page number (1-based)
     * @param int    $limit  Items per page
     * @param string $search Optional search query
     *
     * @return array{tags: Tag[], total: int}
     */
    public function findByOwnerPaginated(User $owner, int $page, int $limit, string $search = ''): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.owner = :owner')
            ->setParameter('owner', $owner);

        if ($search !== '') {
            $qb->andWhere('LOWER(t.name) LIKE LOWER(:search)')
                ->setParameter('search', '%'.$search.'%');
        }

        // Get total count
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // Get paginated results
        $offset = ($page - 1) * $limit;
        $tags = $qb->orderBy('t.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'tags' => $tags,
            'total' => $total,
        ];
    }

    /**
     * Get task counts for multiple tags efficiently.
     *
     * @param Tag[] $tags Array of tags
     *
     * @return array<string, int> Map of tag ID to task count
     */
    public function getTaskCountsForTags(array $tags): array
    {
        if (empty($tags)) {
            return [];
        }

        $tagIds = array_map(fn (Tag $tag) => $tag->getId(), $tags);

        $results = $this->createQueryBuilder('t')
            ->select('t.id', 'COUNT(task.id) as taskCount')
            ->leftJoin('t.tasks', 'task')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $tagIds)
            ->groupBy('t.id')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[$row['id']] = (int) $row['taskCount'];
        }

        // Ensure all tags have a count (default 0)
        foreach ($tagIds as $id) {
            if (!isset($counts[$id])) {
                $counts[$id] = 0;
            }
        }

        return $counts;
    }
}
