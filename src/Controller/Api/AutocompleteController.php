<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\TagRepository;
use App\Service\ResponseFormatter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for autocomplete API endpoints.
 *
 * Provides search suggestions for projects and tags based on user input.
 */
#[Route('/api/v1/autocomplete', name: 'api_autocomplete_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class AutocompleteController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly TagRepository $tagRepository,
        private readonly ResponseFormatter $responseFormatter,
    ) {
    }

    /**
     * Search projects by name prefix.
     *
     * Query parameters:
     * - q: Search query (prefix to match against project names)
     * - limit: Maximum number of results (default: 10, max: 50)
     *
     * Returns:
     * - id: Project UUID
     * - name: Project name
     * - fullPath: Full path (for nested projects)
     * - color: Project color
     * - parent: Parent project info (if any)
     */
    #[Route('/projects', name: 'projects', methods: ['GET'])]
    public function projects(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $query = $request->query->get('q', '');
        $limit = min((int) $request->query->get('limit', '10'), 50);

        if ($limit < 1) {
            $limit = 10;
        }

        $projects = $this->projectRepository->searchByNamePrefix($user, $query, $limit);

        $results = array_map(function ($project) {
            $parent = $project->getParent();

            return [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'fullPath' => $this->getProjectFullPath($project),
                'color' => $project->getColor(),
                'parent' => $parent !== null ? [
                    'id' => $parent->getId(),
                    'name' => $parent->getName(),
                ] : null,
            ];
        }, $projects);

        return $this->responseFormatter->success([
            'items' => $results,
            'query' => $query,
            'count' => count($results),
        ]);
    }

    /**
     * Search tags by name prefix.
     *
     * Query parameters:
     * - q: Search query (prefix to match against tag names)
     * - limit: Maximum number of results (default: 10, max: 50)
     *
     * Returns:
     * - id: Tag UUID
     * - name: Tag name
     * - color: Tag color
     */
    #[Route('/tags', name: 'tags', methods: ['GET'])]
    public function tags(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $query = $request->query->get('q', '');
        $limit = min((int) $request->query->get('limit', '10'), 50);

        if ($limit < 1) {
            $limit = 10;
        }

        $tags = $this->tagRepository->searchByPrefix($user, $query, $limit);

        $results = array_map(fn($tag) => [
            'id' => $tag->getId(),
            'name' => $tag->getName(),
            'color' => $tag->getColor(),
        ], $tags);

        return $this->responseFormatter->success([
            'items' => $results,
            'query' => $query,
            'count' => count($results),
        ]);
    }

    /**
     * Get the full path of a project by traversing its parent chain.
     */
    private function getProjectFullPath($project): string
    {
        $parts = [];
        $current = $project;

        while ($current !== null) {
            array_unshift($parts, $current->getName());
            $current = $current->getParent();
        }

        return implode('/', $parts);
    }
}
