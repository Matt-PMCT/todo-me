<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 *
 * @method Project|null find($id, $lockMode = null, $lockVersion = null)
 * @method Project|null findOneBy(array $criteria, array $orderBy = null)
 * @method Project[]    findAll()
 * @method Project[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * @return Project[]
     */
    public function findByOwner(User $owner, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('p.name', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('p.isArchived = :archived')
                ->setParameter('archived', false);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find projects by owner with pagination support.
     *
     * @param User $owner The project owner
     * @param int $page The page number (1-indexed)
     * @param int $limit Items per page
     * @param bool $includeArchived Whether to include archived projects
     * @return array{projects: Project[], total: int}
     */
    public function findByOwnerPaginated(
        User $owner,
        int $page,
        int $limit,
        bool $includeArchived = false
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('p.name', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('p.isArchived = :archived')
                ->setParameter('archived', false);
        }

        // Get total count
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();

        // Get paginated results
        $offset = ($page - 1) * $limit;
        $projects = $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'projects' => $projects,
            'total' => $total,
        ];
    }

    /**
     * Find all non-archived projects for a user.
     *
     * @param User $owner The project owner
     * @return Project[]
     */
    public function findActiveByOwner(User $owner): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('p.isArchived = :archived')
            ->setParameter('owner', $owner)
            ->setParameter('archived', false)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Project[]
     */
    public function findArchivedByOwner(User $owner): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('p.isArchived = :archived')
            ->setParameter('owner', $owner)
            ->setParameter('archived', true)
            ->orderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByOwnerAndId(User $owner, string $id): ?Project
    {
        return $this->findOneBy(['owner' => $owner, 'id' => $id]);
    }

    /**
     * Count all tasks in a project.
     *
     * @param Project $project The project
     * @return int The total task count
     */
    public function countTasksByProject(Project $project): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Task::class, 't')
            ->where('t.project = :project')
            ->setParameter('project', $project)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count completed tasks in a project.
     *
     * @param Project $project The project
     * @return int The completed task count
     */
    public function countCompletedTasksByProject(Project $project): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(Task::class, 't')
            ->where('t.project = :project')
            ->andWhere('t.status = :status')
            ->setParameter('project', $project)
            ->setParameter('status', Task::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get task counts for multiple projects in a single query.
     *
     * @param Project[] $projects
     * @return array<string, array{total: int, completed: int}>
     */
    public function getTaskCountsForProjects(array $projects): array
    {
        if (empty($projects)) {
            return [];
        }

        $projectIds = array_map(fn(Project $p) => $p->getId(), $projects);

        // Get total counts
        $totalCounts = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(t.project) as projectId, COUNT(t.id) as taskCount')
            ->from(Task::class, 't')
            ->where('t.project IN (:projects)')
            ->setParameter('projects', $projectIds)
            ->groupBy('t.project')
            ->getQuery()
            ->getResult();

        // Get completed counts
        $completedCounts = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(t.project) as projectId, COUNT(t.id) as taskCount')
            ->from(Task::class, 't')
            ->where('t.project IN (:projects)')
            ->andWhere('t.status = :status')
            ->setParameter('projects', $projectIds)
            ->setParameter('status', Task::STATUS_COMPLETED)
            ->groupBy('t.project')
            ->getQuery()
            ->getResult();

        // Build result array
        $result = [];
        foreach ($projectIds as $projectId) {
            $result[$projectId] = ['total' => 0, 'completed' => 0];
        }

        foreach ($totalCounts as $row) {
            $result[$row['projectId']]['total'] = (int) $row['taskCount'];
        }

        foreach ($completedCounts as $row) {
            $result[$row['projectId']]['completed'] = (int) $row['taskCount'];
        }

        return $result;
    }

    public function save(Project $project, bool $flush = false): void
    {
        $this->getEntityManager()->persist($project);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Project $project, bool $flush = false): void
    {
        $this->getEntityManager()->remove($project);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
