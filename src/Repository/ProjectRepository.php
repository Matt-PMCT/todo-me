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
    public function findByOwner(User $owner, bool $includeArchived = false, bool $includeDeleted = false): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('p.name', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('p.isArchived = :archived')
                ->setParameter('archived', false);
        }

        if (!$includeDeleted) {
            $qb->andWhere('p.deletedAt IS NULL');
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
     * @param bool $includeDeleted Whether to include soft-deleted projects
     * @return array{projects: Project[], total: int}
     */
    public function findByOwnerPaginated(
        User $owner,
        int $page,
        int $limit,
        bool $includeArchived = false,
        bool $includeDeleted = false
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('p.name', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('p.isArchived = :archived')
                ->setParameter('archived', false);
        }

        if (!$includeDeleted) {
            $qb->andWhere('p.deletedAt IS NULL');
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
            ->andWhere('p.deletedAt IS NULL')
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
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('owner', $owner)
            ->setParameter('archived', true)
            ->orderBy('p.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find archived projects by owner with pagination support.
     *
     * @param User $owner The project owner
     * @param int $page The page number (1-indexed)
     * @param int $limit Items per page
     * @return array{projects: Project[], total: int}
     */
    public function findArchivedByOwnerPaginated(
        User $owner,
        int $page,
        int $limit
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('p.isArchived = :archived')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('owner', $owner)
            ->setParameter('archived', true)
            ->orderBy('p.updatedAt', 'DESC');

        // Get total count
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
     * Find a project by owner and ID.
     *
     * @param User $owner The project owner
     * @param string $id The project ID
     * @param bool $includeDeleted Whether to include soft-deleted projects
     * @return Project|null
     */
    public function findOneByOwnerAndId(User $owner, string $id, bool $includeDeleted = false): ?Project
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('p.id = :id')
            ->setParameter('owner', $owner)
            ->setParameter('id', $id);

        if (!$includeDeleted) {
            $qb->andWhere('p.deletedAt IS NULL');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Find soft-deleted projects for a user.
     *
     * @param User $owner The project owner
     * @return Project[]
     */
    public function findDeletedByOwner(User $owner): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('p.deletedAt IS NOT NULL')
            ->setParameter('owner', $owner)
            ->orderBy('p.deletedAt', 'DESC')
            ->getQuery()
            ->getResult();
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
     * Get task counts for multiple projects in a single optimized query.
     *
     * Uses conditional aggregation to get both total and completed counts
     * in a single database query, reducing the number of queries from 2 to 1.
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

        // Single query with conditional aggregation
        $results = $this->getEntityManager()
            ->createQueryBuilder()
            ->select(
                'IDENTITY(t.project) as projectId',
                'COUNT(t.id) as total',
                'SUM(CASE WHEN t.status = :completed THEN 1 ELSE 0 END) as completedCount'
            )
            ->from(Task::class, 't')
            ->where('t.project IN (:projects)')
            ->setParameter('projects', $projectIds)
            ->setParameter('completed', Task::STATUS_COMPLETED)
            ->groupBy('t.project')
            ->getQuery()
            ->getResult();

        // Build result array with defaults
        $result = [];
        foreach ($projectIds as $projectId) {
            $result[$projectId] = ['total' => 0, 'completed' => 0];
        }

        // Populate from query results
        foreach ($results as $row) {
            $result[$row['projectId']] = [
                'total' => (int) $row['total'],
                'completed' => (int) $row['completedCount'],
            ];
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
            ->andWhere('p.deletedAt IS NULL')
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
            ->andWhere('p.deletedAt IS NULL')
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
                ->andWhere('p.deletedAt IS NULL')
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
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('owner', $owner)
            ->setParameter('prefix', $prefix . '%')
            ->setParameter('archived', false)
            ->orderBy('p.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search projects by name (case-insensitive ILIKE search).
     *
     * @param User $owner The project owner
     * @param string $query The search query
     * @param int $limit Maximum number of results
     * @return Project[] Matching projects
     */
    public function searchByName(User $owner, string $query, int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('LOWER(p.name) LIKE LOWER(:query) OR LOWER(p.description) LIKE LOWER(:query)')
            ->andWhere('p.isArchived = :archived')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('owner', $owner)
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('archived', false)
            ->orderBy('p.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the project tree for a user.
     *
     * @param User $user The project owner
     * @param bool $includeArchived Whether to include archived projects
     * @return Project[] All projects for the user (flat array)
     */
    public function getTreeByUser(User $user, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('owner', $user)
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('p.isArchived = :archived')
                ->setParameter('archived', false);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all descendant IDs of a project using a recursive CTE.
     *
     * @param Project $project The parent project
     * @return string[] Array of descendant project IDs
     */
    public function getDescendantIds(Project $project): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // Set statement timeout to prevent runaway recursive queries (5 seconds)
        $conn->executeStatement('SET LOCAL statement_timeout = 5000');

        $sql = "WITH RECURSIVE descendants AS (
            SELECT id FROM projects WHERE parent_id = :projectId AND owner_id = :ownerId AND deleted_at IS NULL
            UNION ALL
            SELECT p.id FROM projects p
            INNER JOIN descendants d ON p.parent_id = d.id WHERE p.owner_id = :ownerId AND p.deleted_at IS NULL
        ) SELECT id FROM descendants";

        $result = $conn->executeQuery($sql, [
            'projectId' => $project->getId(),
            'ownerId' => $project->getOwner()?->getId(),
        ]);

        return array_column($result->fetchAllAssociative(), 'id');
    }

    /**
     * Get all ancestor IDs of a project using a recursive CTE.
     *
     * @param Project $project The project
     * @return string[] Array of ancestor project IDs (from root to immediate parent)
     */
    public function getAncestorIds(Project $project): array
    {
        if ($project->getParent() === null) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();

        // Set statement timeout to prevent runaway recursive queries (5 seconds)
        $conn->executeStatement('SET LOCAL statement_timeout = 5000');

        $sql = "WITH RECURSIVE ancestors AS (
            SELECT id, parent_id FROM projects WHERE id = :projectId AND deleted_at IS NULL
            UNION ALL
            SELECT p.id, p.parent_id FROM projects p
            INNER JOIN ancestors a ON p.id = a.parent_id WHERE p.deleted_at IS NULL
        ) SELECT id FROM ancestors WHERE id != :projectId ORDER BY id";

        $result = $conn->executeQuery($sql, ['projectId' => $project->getId()]);

        return array_column($result->fetchAllAssociative(), 'id');
    }

    /**
     * Get the project tree with task counts for a user.
     *
     * @param User $user The project owner
     * @param bool $includeArchived Whether to include archived projects
     * @return array<string, array{total: int, completed: int}> Task counts indexed by project ID
     */
    public function getTreeWithTaskCounts(User $user, bool $includeArchived = false): array
    {
        $projects = $this->getTreeByUser($user, $includeArchived);

        return $this->getTaskCountsForProjects($projects);
    }

    /**
     * Normalize positions for projects within a parent (make them sequential 0, 1, 2...).
     *
     * @param User $user The project owner
     * @param string|null $parentId The parent project ID (null for root projects)
     */
    public function normalizePositions(User $user, ?string $parentId): void
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('owner', $user)
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC');

        if ($parentId === null) {
            $qb->andWhere('p.parent IS NULL');
        } else {
            $qb->andWhere('p.parent = :parentId')
                ->setParameter('parentId', $parentId);
        }

        $projects = $qb->getQuery()->getResult();

        $position = 0;
        foreach ($projects as $project) {
            if ($project->getPosition() !== $position) {
                $project->setPosition($position);
            }
            $position++;
        }

        $this->getEntityManager()->flush();
    }

    /**
     * Find root projects (no parent) for a user.
     *
     * @param User $user The project owner
     * @param bool $includeArchived Whether to include archived projects
     * @return Project[]
     */
    public function findRootsByOwner(User $user, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.owner = :owner')
            ->andWhere('p.parent IS NULL')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('owner', $user)
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('p.isArchived = :archived')
                ->setParameter('archived', false);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get the maximum position value for projects within a parent.
     *
     * @param User $user The project owner
     * @param string|null $parentId The parent project ID (null for root projects)
     * @return int The maximum position, or -1 if no projects exist
     */
    public function getMaxPositionInParent(User $user, ?string $parentId): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('MAX(p.position)')
            ->where('p.owner = :owner')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('owner', $user);

        if ($parentId === null) {
            $qb->andWhere('p.parent IS NULL');
        } else {
            $qb->andWhere('p.parent = :parentId')
                ->setParameter('parentId', $parentId);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result !== null ? (int) $result : -1;
    }

    /**
     * Find children of a project.
     *
     * @param Project $parent The parent project
     * @param bool $includeArchived Whether to include archived projects
     * @return Project[]
     */
    public function findChildrenByParent(Project $parent, bool $includeArchived = false): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.parent = :parent')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('parent', $parent)
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('p.isArchived = :archived')
                ->setParameter('archived', false);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get all descendants as Project entities.
     *
     * @param Project $project The parent project
     * @return Project[]
     */
    public function findAllDescendants(Project $project): array
    {
        $descendantIds = $this->getDescendantIds($project);

        if (empty($descendantIds)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->andWhere('p.owner = :owner')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('ids', $descendantIds)
            ->setParameter('owner', $project->getOwner())
            ->orderBy('p.position', 'ASC')
            ->addOrderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
