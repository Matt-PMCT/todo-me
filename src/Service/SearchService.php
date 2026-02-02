<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\SearchRequest;
use App\DTO\SearchResponse;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;

/**
 * Service for global search operations.
 *
 * Orchestrates search across tasks, projects, and tags.
 * Tasks use PostgreSQL full-text search, projects and tags use ILIKE.
 */
final class SearchService
{
    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly TagRepository $tagRepository,
    ) {
    }

    /**
     * Performs a global search across tasks, projects, and tags.
     *
     * @param User          $user    The user performing the search
     * @param SearchRequest $request The search request
     *
     * @return SearchResponse The search results
     */
    public function search(User $user, SearchRequest $request): SearchResponse
    {
        $startTime = hrtime(true);

        $tasks = [];
        $tasksWithHighlights = [];
        $projects = [];
        $tags = [];
        $totalTasks = 0;
        $totalProjects = 0;
        $totalTags = 0;

        $searchType = $request->type;
        $limit = $request->limit;
        $useHighlights = $request->highlight && ($searchType === SearchRequest::TYPE_ALL || $searchType === SearchRequest::TYPE_TASKS);

        // Search tasks
        if ($searchType === SearchRequest::TYPE_ALL || $searchType === SearchRequest::TYPE_TASKS) {
            $totalTasks = $this->taskRepository->searchCount($user, $request->query);
            $offset = $searchType === SearchRequest::TYPE_ALL ? 0 : ($request->page - 1) * $limit;

            if ($useHighlights) {
                $tasksWithHighlights = $this->taskRepository->searchWithHighlights($user, $request->query, $limit, $offset);
            } else {
                $tasks = $this->taskRepository->search($user, $request->query, $limit, $offset);
            }
        }

        // Search projects
        if ($searchType === SearchRequest::TYPE_ALL || $searchType === SearchRequest::TYPE_PROJECTS) {
            $projectResults = $this->projectRepository->searchByName($user, $request->query, 100);
            $totalProjects = count($projectResults);

            if ($searchType === SearchRequest::TYPE_ALL) {
                $projects = array_slice($projectResults, 0, $limit);
            } else {
                $offset = ($request->page - 1) * $limit;
                $projects = array_slice($projectResults, $offset, $limit);
            }
        }

        // Search tags
        if ($searchType === SearchRequest::TYPE_ALL || $searchType === SearchRequest::TYPE_TAGS) {
            $tagResults = $this->tagRepository->searchByName($user, $request->query, 100);
            $totalTags = count($tagResults);

            if ($searchType === SearchRequest::TYPE_ALL) {
                $tags = array_slice($tagResults, 0, $limit);
            } else {
                $offset = ($request->page - 1) * $limit;
                $tags = array_slice($tagResults, $offset, $limit);
            }
        }

        $searchTimeMs = (hrtime(true) - $startTime) / 1_000_000;

        if ($useHighlights) {
            return SearchResponse::fromEntitiesWithHighlights(
                tasksWithHighlights: $tasksWithHighlights,
                projects: $projects,
                tags: $tags,
                totalTasks: $totalTasks,
                totalProjects: $totalProjects,
                totalTags: $totalTags,
                page: $request->page,
                limit: $request->limit,
                searchTimeMs: $searchTimeMs,
            );
        }

        return SearchResponse::fromEntities(
            tasks: $tasks,
            projects: $projects,
            tags: $tags,
            totalTasks: $totalTasks,
            totalProjects: $totalProjects,
            totalTags: $totalTags,
            page: $request->page,
            limit: $request->limit,
            searchTimeMs: $searchTimeMs,
        );
    }
}
