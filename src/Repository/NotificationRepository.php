<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 *
 * @method Notification|null find($id, $lockMode = null, $lockVersion = null)
 * @method Notification|null findOneBy(array $criteria, array $orderBy = null)
 * @method Notification[]    findAll()
 * @method Notification[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Find notifications for a user, ordered by creation date (newest first).
     *
     * @return Notification[]
     */
    public function findByOwner(User $owner, int $limit = 50, int $offset = 0): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find unread notifications for a user, ordered by creation date (newest first).
     *
     * @return Notification[]
     */
    public function findUnreadByOwner(User $owner, int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.owner = :owner')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('owner', $owner)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count unread notifications for a user.
     */
    public function countUnreadByOwner(User $owner): int
    {
        $result = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.owner = :owner')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Mark all notifications as read for a user.
     *
     * @return int Number of notifications marked as read
     */
    public function markAllAsReadByOwner(User $owner): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.readAt', ':now')
            ->where('n.owner = :owner')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('owner', $owner)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Find a notification by ID and owner.
     */
    public function findOneByOwnerAndId(User $owner, string $id): ?Notification
    {
        return $this->findOneBy(['owner' => $owner, 'id' => $id]);
    }

    /**
     * Delete old read notifications (older than specified days).
     *
     * @return int Number of deleted notifications
     */
    public function deleteOldReadNotifications(int $daysOld = 30): int
    {
        $cutoff = new \DateTimeImmutable("-{$daysOld} days");

        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.readAt IS NOT NULL')
            ->andWhere('n.readAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }

    public function save(Notification $notification, bool $flush = false): void
    {
        $this->getEntityManager()->persist($notification);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Notification $notification, bool $flush = false): void
    {
        $this->getEntityManager()->remove($notification);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
