<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSession>
 *
 * @method UserSession|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserSession|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserSession[]    findAll()
 * @method UserSession[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSession::class);
    }

    /**
     * Find all sessions for a user, ordered by last active date descending.
     *
     * @param User $user The user
     *
     * @return UserSession[]
     */
    public function findByOwner(User $user): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.owner = :owner')
            ->setParameter('owner', $user)
            ->orderBy('s.lastActiveAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a session by its token hash.
     *
     * @param string $tokenHash SHA256 hash of the API token
     */
    public function findByTokenHash(string $tokenHash): ?UserSession
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * Delete all sessions for a user.
     *
     * @param User $user The user
     *
     * @return int Number of deleted sessions
     */
    public function deleteByOwner(User $user): int
    {
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.owner = :owner')
            ->setParameter('owner', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Delete all sessions for a user except the one with the given token hash.
     *
     * @param User   $user             The user
     * @param string $currentTokenHash Token hash of the session to keep
     *
     * @return int Number of deleted sessions
     */
    public function deleteByOwnerExcept(User $user, string $currentTokenHash): int
    {
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.owner = :owner')
            ->andWhere('s.tokenHash != :tokenHash')
            ->setParameter('owner', $user)
            ->setParameter('tokenHash', $currentTokenHash)
            ->getQuery()
            ->execute();
    }

    /**
     * Update the last active timestamp for a session by token hash.
     *
     * @param string $tokenHash SHA256 hash of the API token
     */
    public function updateLastActive(string $tokenHash): void
    {
        $this->createQueryBuilder('s')
            ->update()
            ->set('s.lastActiveAt', ':now')
            ->where('s.tokenHash = :tokenHash')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('tokenHash', $tokenHash)
            ->getQuery()
            ->execute();
    }

    public function save(UserSession $session, bool $flush = false): void
    {
        $this->getEntityManager()->persist($session);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserSession $session, bool $flush = false): void
    {
        $this->getEntityManager()->remove($session);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
