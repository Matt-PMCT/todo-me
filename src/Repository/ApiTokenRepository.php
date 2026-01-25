<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ApiToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ApiToken>
 *
 * @method ApiToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method ApiToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method ApiToken[]    findAll()
 * @method ApiToken[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApiTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }

    /**
     * Find all tokens owned by a user.
     *
     * @return ApiToken[]
     */
    public function findByOwner(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.owner = :owner')
            ->setParameter('owner', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find a token by its hash.
     */
    public function findByTokenHash(string $tokenHash): ?ApiToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * Find tokens by their prefix (for identification purposes).
     *
     * @return ApiToken[]
     */
    public function findByTokenPrefix(string $prefix): array
    {
        return $this->findBy(['tokenPrefix' => $prefix]);
    }

    /**
     * Find a valid (non-expired) token by its hash.
     */
    public function findValidByTokenHash(string $tokenHash): ?ApiToken
    {
        $token = $this->findByTokenHash($tokenHash);

        if ($token === null) {
            return null;
        }

        if ($token->isExpired()) {
            return null;
        }

        return $token;
    }

    /**
     * Find a token by ID and owner.
     */
    public function findByIdAndOwner(string $id, User $owner): ?ApiToken
    {
        return $this->findOneBy(['id' => $id, 'owner' => $owner]);
    }

    public function save(ApiToken $token, bool $flush = false): void
    {
        $this->getEntityManager()->persist($token);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ApiToken $token, bool $flush = false): void
    {
        $this->getEntityManager()->remove($token);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
