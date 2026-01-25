<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 *
 * @method ActivityLog|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActivityLog|null findOneBy(array $criteria, array $orderBy = null)
 * @method ActivityLog[]    findAll()
 * @method ActivityLog[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Find activity logs for a user with pagination.
     *
     * @param User $owner The owner
     * @param int  $page  Page number (1-indexed)
     * @param int  $limit Items per page
     *
     * @return ActivityLog[]
     */
    public function findByOwnerPaginated(User $owner, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('a')
            ->where('a.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total activity logs for a user.
     *
     * @param User $owner The owner
     */
    public function countByOwner(User $owner): int
    {
        $result = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    public function save(ActivityLog $activityLog, bool $flush = false): void
    {
        $this->getEntityManager()->persist($activityLog);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
