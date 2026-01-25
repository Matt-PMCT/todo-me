<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PushSubscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushSubscription>
 *
 * @method PushSubscription|null find($id, $lockMode = null, $lockVersion = null)
 * @method PushSubscription|null findOneBy(array $criteria, array $orderBy = null)
 * @method PushSubscription[]    findAll()
 * @method PushSubscription[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PushSubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSubscription::class);
    }

    /**
     * Find all subscriptions for a user.
     *
     * @return PushSubscription[]
     */
    public function findByOwner(User $owner): array
    {
        return $this->findBy(['owner' => $owner], ['createdAt' => 'DESC']);
    }

    /**
     * Find a subscription by endpoint hash.
     */
    public function findByEndpoint(string $endpoint): ?PushSubscription
    {
        $hash = hash('sha256', $endpoint);

        return $this->findOneBy(['endpointHash' => $hash]);
    }

    /**
     * Find a subscription by endpoint hash and owner.
     */
    public function findByEndpointAndOwner(string $endpoint, User $owner): ?PushSubscription
    {
        $hash = hash('sha256', $endpoint);

        return $this->findOneBy(['endpointHash' => $hash, 'owner' => $owner]);
    }

    /**
     * Count subscriptions for a user.
     */
    public function countByOwner(User $owner): int
    {
        $result = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Delete stale subscriptions (unused for specified days).
     *
     * @return int Number of deleted subscriptions
     */
    public function deleteStaleSubscriptions(int $daysUnused = 90): int
    {
        $cutoff = new \DateTimeImmutable("-{$daysUnused} days");

        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.lastUsedAt IS NOT NULL')
            ->andWhere('s.lastUsedAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }

    public function save(PushSubscription $subscription, bool $flush = false): void
    {
        $this->getEntityManager()->persist($subscription);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PushSubscription $subscription, bool $flush = false): void
    {
        $this->getEntityManager()->remove($subscription);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
