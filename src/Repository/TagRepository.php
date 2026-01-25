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
}
