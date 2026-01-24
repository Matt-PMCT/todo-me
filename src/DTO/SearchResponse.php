<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Task;

/**
 * Response DTO for global search results.
 */
final class SearchResponse
{
    /**
     * @param array<array<string, mixed>> $tasks
     * @param array<array<string, mixed>> $projects
     * @param array<array<string, mixed>> $tags
     * @param array{total: int, page: int, limit: int, totalPages: int, hasNextPage: bool, hasPreviousPage: bool} $meta
     */
    public function __construct(
        public readonly array $tasks,
        public readonly array $projects,
        public readonly array $tags,
        public readonly array $meta,
    ) {
    }

    /**
     * Creates a SearchResponse from entity arrays.
     *
     * @param Task[] $tasks
     * @param Project[] $projects
     * @param Tag[] $tags
     * @param int $totalTasks
     * @param int $totalProjects
     * @param int $totalTags
     * @param int $page
     * @param int $limit
     */
    public static function fromEntities(
        array $tasks,
        array $projects,
        array $tags,
        int $totalTasks,
        int $totalProjects,
        int $totalTags,
        int $page,
        int $limit,
    ): self {
        $taskResults = array_map(fn(Task $task) => self::serializeTask($task), $tasks);
        $projectResults = array_map(fn(Project $project) => self::serializeProject($project), $projects);
        $tagResults = array_map(fn(Tag $tag) => self::serializeTag($tag), $tags);

        $total = $totalTasks + $totalProjects + $totalTags;
        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 0;

        return new self(
            tasks: $taskResults,
            projects: $projectResults,
            tags: $tagResults,
            meta: [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => $totalPages,
                'hasNextPage' => $page < $totalPages,
                'hasPreviousPage' => $page > 1,
                'counts' => [
                    'tasks' => $totalTasks,
                    'projects' => $totalProjects,
                    'tags' => $totalTags,
                ],
            ],
        );
    }

    /**
     * Serializes a Task entity to a search result array.
     *
     * @return array<string, mixed>
     */
    private static function serializeTask(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'type' => 'task',
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'priority' => $task->getPriority(),
            'dueDate' => $task->getDueDate()?->format('Y-m-d'),
            'projectId' => $task->getProject()?->getId(),
            'projectName' => $task->getProject()?->getName(),
        ];
    }

    /**
     * Serializes a Project entity to a search result array.
     *
     * @return array<string, mixed>
     */
    private static function serializeProject(Project $project): array
    {
        return [
            'id' => $project->getId(),
            'type' => 'project',
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'isArchived' => $project->isArchived(),
            'color' => $project->getColor(),
        ];
    }

    /**
     * Serializes a Tag entity to a search result array.
     *
     * @return array<string, mixed>
     */
    private static function serializeTag(Tag $tag): array
    {
        return [
            'id' => $tag->getId(),
            'type' => 'tag',
            'name' => $tag->getName(),
            'color' => $tag->getColor(),
        ];
    }

    /**
     * Converts the response to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tasks' => $this->tasks,
            'projects' => $this->projects,
            'tags' => $this->tags,
            'meta' => $this->meta,
        ];
    }
}
