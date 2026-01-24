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

        // Get total count (remove orderBy to avoid PostgreSQL grouping error)
        $countQb = clone $qb;
        $countQb->resetDQLPart('orderBy');
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

    /**
     * Find a project by name (case-insensitive).
     *
     * @param User $owner The project owner
     * @param string $name The project name to search for
     * @return Project|null The matching project or null if not found
     */
    public function findByNameInsensitive(User $owner, string $name): ?Project
    {
        return $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('LOWER(p.name) = LOWER(:name)')
            ->andWhere('p.isArchived = :archived')
            ->setParameter('owner', $owner)
            ->setParameter('name', $name)
            ->setParameter('archived', false)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find a project by full path (case-insensitive).
     *
     * Path format: "parent/child/grandchild"
     * This traverses the project hierarchy to find the exact project.
     *
     * @param User $owner The project owner
     * @param string $path The project path (e.g., "work/meetings/standup")
     * @return Project|null The matching project or null if not found
     */
    public function findByPathInsensitive(User $owner, string $path): ?Project
    {
        $parts = explode('/', $path);

        if (empty($parts)) {
            return null;
        }

        // Start by finding the root project (no parent)
        $currentProject = $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('LOWER(p.name) = LOWER(:name)')
            ->andWhere('p.parent IS NULL')
            ->andWhere('p.isArchived = :archived')
            ->setParameter('owner', $owner)
            ->setParameter('name', $parts[0])
            ->setParameter('archived', false)
            ->getQuery()
            ->getOneOrNullResult();

        if ($currentProject === null) {
            return null;
        }

        // Traverse the path
        for ($i = 1; $i < count($parts); $i++) {
            $currentProject = $this->createQueryBuilder('p')
                ->where('p.owner = :owner')
                ->andWhere('LOWER(p.name) = LOWER(:name)')
                ->andWhere('p.parent = :parent')
                ->andWhere('p.isArchived = :archived')
                ->setParameter('owner', $owner)
                ->setParameter('name', $parts[$i])
                ->setParameter('parent', $currentProject)
                ->setParameter('archived', false)
                ->getQuery()
                ->getOneOrNullResult();

            if ($currentProject === null) {
                return null;
            }
        }

        return $currentProject;
    }

    /**
     * Search projects by name prefix for autocomplete.
     *
     * @param User $owner The project owner
     * @param string $prefix The name prefix to search for
     * @param int $limit Maximum number of results
     * @return Project[] Matching projects
     */
    public function searchByNamePrefix(User $owner, string $prefix, int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('LOWER(p.name) LIKE LOWER(:prefix)')
            ->andWhere('p.isArchived = :archived')
            ->setParameter('owner', $owner)
            ->setParameter('prefix', $prefix . '%')
            ->setParameter('archived', false)
            ->orderBy('p.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
