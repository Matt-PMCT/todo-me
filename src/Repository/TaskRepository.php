<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 *
 * @method Task|null find($id, $lockMode = null, $lockVersion = null)
 * @method Task|null findOneBy(array $criteria, array $orderBy = null)
 * @method Task[]    findAll()
 * @method Task[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly string $searchLocale = 'english',
    ) {
        parent::__construct($registry, Task::class);
    }

    /**
     * @return Task[]
     */
    public function findByOwner(User $owner, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('t.position', 'ASC')
            ->addOrderBy('t.priority', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Creates a QueryBuilder for paginated task queries with filters.
     *
     * @param User $owner The task owner
     * @param array{
     *     status?: string,
     *     priority?: int,
     *     projectId?: string,
     *     search?: string,
     *     dueBefore?: string,
     *     dueAfter?: string,
     *     tagIds?: string[]
     * } $filters
     */
    public function createFilteredQueryBuilder(User $owner, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.project', 'p')
            ->leftJoin('t.tags', 'tag')
            ->where('t.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('t.position', 'ASC')
            ->addOrderBy('t.priority', 'DESC')
            ->addOrderBy('t.createdAt', 'DESC');

        // Apply status filter
        if (isset($filters['status']) && $filters['status'] !== '') {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $filters['status']);
        }

        // Apply priority filter
        if (isset($filters['priority']) && $filters['priority'] !== null) {
            $qb->andWhere('t.priority = :priority')
                ->setParameter('priority', $filters['priority']);
        }

        // Apply project filter
        if (isset($filters['projectId']) && $filters['projectId'] !== '') {
            $qb->andWhere('t.project = :projectId')
                ->setParameter('projectId', $filters['projectId']);
        }

        // Apply due date filters
        if (isset($filters['dueBefore']) && $filters['dueBefore'] !== '') {
            $dueBefore = new \DateTimeImmutable($filters['dueBefore']);
            $qb->andWhere('t.dueDate <= :dueBefore')
                ->setParameter('dueBefore', $dueBefore);
        }

        if (isset($filters['dueAfter']) && $filters['dueAfter'] !== '') {
            $dueAfter = new \DateTimeImmutable($filters['dueAfter']);
            $qb->andWhere('t.dueDate >= :dueAfter')
                ->setParameter('dueAfter', $dueAfter);
        }

        // Apply tag filter
        if (isset($filters['tagIds']) && !empty($filters['tagIds'])) {
            $qb->andWhere('tag.id IN (:tagIds)')
                ->setParameter('tagIds', $filters['tagIds']);
        }

        // Apply search filter (simple LIKE search on title and description)
        if (isset($filters['search']) && $filters['search'] !== '') {
            $searchTerm = '%' . $filters['search'] . '%';
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('LOWER(t.title)', 'LOWER(:search)'),
                $qb->expr()->like('LOWER(t.description)', 'LOWER(:search)')
            ))
                ->setParameter('search', $searchTerm);
        }

        // Make sure we get distinct results when joining tags
        $qb->distinct();

        return $qb;
    }

    /**
     * Find tasks by owner with pagination and filters.
     *
     * @param User $owner The task owner
     * @param int $page The page number (1-indexed)
     * @param int $limit The number of items per page
     * @param array{
     *     status?: string,
     *     priority?: int,
     *     projectId?: string,
     *     search?: string,
     *     dueBefore?: string,
     *     dueAfter?: string,
     *     tagIds?: string[]
     * } $filters
     * @return QueryBuilder
     */
    public function findByOwnerPaginatedQueryBuilder(User $owner, array $filters = []): QueryBuilder
    {
        return $this->createFilteredQueryBuilder($owner, $filters);
    }

    /**
     * Creates a QueryBuilder for paginated project task queries.
     *
     * @param Project $project The project
     * @return QueryBuilder
     */
    public function findByProjectPaginatedQueryBuilder(Project $project): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.tags', 'tag')
            ->where('t.project = :project')
            ->setParameter('project', $project)
            ->orderBy('t.position', 'ASC')
            ->addOrderBy('t.priority', 'DESC');
    }

    /**
     * @return Task[]
     */
    public function findByProject(Project $project, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.project = :project')
            ->setParameter('project', $project)
            ->orderBy('t.position', 'ASC')
            ->addOrderBy('t.priority', 'DESC');

        if ($status !== null) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Task[]
     */
    public function findPendingByOwner(User $owner): array
    {
        return $this->findByOwner($owner, Task::STATUS_PENDING);
    }

    /**
     * @return Task[]
     */
    public function findInProgressByOwner(User $owner): array
    {
        return $this->findByOwner($owner, Task::STATUS_IN_PROGRESS);
    }

    /**
     * @return Task[]
     */
    public function findCompletedByOwner(User $owner): array
    {
        return $this->findByOwner($owner, Task::STATUS_COMPLETED);
    }

    /**
     * @return Task[]
     */
    public function findOverdueByOwner(User $owner): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.owner = :owner')
            ->andWhere('t.dueDate < :today')
            ->andWhere('t.status != :completed')
            ->setParameter('owner', $owner)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->setParameter('completed', Task::STATUS_COMPLETED)
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Task[]
     */
    public function findDueSoonByOwner(User $owner, int $days = 7): array
    {
        $today = new \DateTimeImmutable('today');
        $endDate = $today->modify("+{$days} days");

        return $this->createQueryBuilder('t')
            ->where('t.owner = :owner')
            ->andWhere('t.dueDate >= :today')
            ->andWhere('t.dueDate <= :endDate')
            ->andWhere('t.status != :completed')
            ->setParameter('owner', $owner)
            ->setParameter('today', $today)
            ->setParameter('endDate', $endDate)
            ->setParameter('completed', Task::STATUS_COMPLETED)
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Full-text search using PostgreSQL tsvector
     *
     * @return Task[]
     */
    public function search(User $owner, string $query): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT t.id
            FROM tasks t
            WHERE t.owner_id = :owner_id
            AND t.search_vector @@ plainto_tsquery(:locale, :query)
            ORDER BY ts_rank(t.search_vector, plainto_tsquery(:locale, :query)) DESC
        ";

        $result = $conn->executeQuery($sql, [
            'owner_id' => $owner->getId(),
            'query' => $query,
            'locale' => $this->searchLocale,
        ]);

        $ids = array_column($result->fetchAllAssociative(), 'id');

        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function findOneByOwnerAndId(User $owner, string $id): ?Task
    {
        return $this->findOneBy(['owner' => $owner, 'id' => $id]);
    }

    /**
     * Gets the maximum position for tasks of a given owner and optionally project.
     *
     * @param User $owner The task owner
     * @param Project|null $project Optional project filter
     * @return int The maximum position (or -1 if no tasks exist)
     */
    public function getMaxPosition(User $owner, ?Project $project = null): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->where('t.owner = :owner')
            ->setParameter('owner', $owner);

        if ($project !== null) {
            $qb->andWhere('t.project = :project')
                ->setParameter('project', $project);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result !== null ? (int) $result : -1;
    }

    public function getNextPosition(User $owner, ?Project $project = null): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('MAX(t.position)')
            ->where('t.owner = :owner')
            ->setParameter('owner', $owner);

        if ($project !== null) {
            $qb->andWhere('t.project = :project')
                ->setParameter('project', $project);
        } else {
            $qb->andWhere('t.project IS NULL');
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result !== null ? ((int) $result) + 1 : 0;
    }

    /**
     * Reorders tasks by updating their positions based on the provided task IDs.
     *
     * @param User $owner The task owner
     * @param string[] $taskIds The task IDs in the desired order
     */
    public function reorderTasks(User $owner, array $taskIds): void
    {
        if (empty($taskIds)) {
            return;
        }

        $em = $this->getEntityManager();

        // Single batch query to fetch all tasks
        $tasks = $this->findByOwnerAndIds($owner, $taskIds);

        // Index tasks by ID for O(1) lookup
        $taskMap = [];
        foreach ($tasks as $task) {
            $taskMap[$task->getId()] = $task;
        }

        // Update positions based on the provided order
        foreach ($taskIds as $position => $taskId) {
            if (isset($taskMap[$taskId])) {
                $taskMap[$taskId]->setPosition($position);
            }
        }

        $em->flush();
    }

    /**
     * Finds tasks by their IDs that belong to a specific owner.
     *
     * @param User $owner The task owner
     * @param string[] $ids The task IDs
     * @return Task[]
     */
    public function findByOwnerAndIds(User $owner, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->where('t.owner = :owner')
            ->andWhere('t.id IN (:ids)')
            ->setParameter('owner', $owner)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    public function save(Task $task, bool $flush = false): void
    {
        $this->getEntityManager()->persist($task);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Task $task, bool $flush = false): void
    {
        $this->getEntityManager()->remove($task);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find tasks by project, optionally including tasks from child projects.
     *
     * @param Project $project The parent project
     * @param bool $includeChildren Whether to include tasks from child projects
     * @param bool $includeArchivedProjects Whether to include tasks from archived child projects
     * @param string|null $status Optional status filter
     * @return Task[]
     */
    public function findByProjectWithChildren(
        Project $project,
        bool $includeChildren = false,
        bool $includeArchivedProjects = false,
        ?string $status = null
    ): array {
        if (!$includeChildren) {
            return $this->findByProject($project, $status);
        }

        // Get descendant project IDs using the ProjectRepository
        /** @var ProjectRepository $projectRepository */
        $projectRepository = $this->getEntityManager()->getRepository(Project::class);
        $descendantIds = $projectRepository->getDescendantIds($project);

        // Include the parent project itself
        $projectIds = array_merge([$project->getId()], $descendantIds);

        // Build query
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.project', 'p')
            ->where('t.project IN (:projectIds)')
            ->setParameter('projectIds', $projectIds)
            ->orderBy('t.position', 'ASC')
            ->addOrderBy('t.priority', 'DESC');

        if ($status !== null) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        if (!$includeArchivedProjects) {
            $qb->andWhere('p.isArchived = :archived')
                ->setParameter('archived', false);
        }

        return $qb->getQuery()->getResult();
    }
}
