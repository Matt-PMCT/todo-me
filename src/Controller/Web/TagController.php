<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\User;
use App\Repository\TagRepository;
use App\Service\ProjectService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for web tag management.
 */
#[IsGranted('ROLE_USER')]
class TagController extends AbstractController
{
    private const int PAGE_LIMIT = 20;

    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly ProjectService $projectService,
    ) {
    }

    #[Route('/tags', name: 'app_tags', methods: ['GET'])]
    public function list(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, $request->query->getInt('page', 1));
        $search = $request->query->getString('search', '');

        // Get paginated tags
        $result = $this->tagRepository->findByOwnerPaginated($user, $page, self::PAGE_LIMIT, $search);
        $tags = $result['tags'];
        $total = $result['total'];
        $totalPages = (int) ceil($total / self::PAGE_LIMIT);

        // Get task counts for tags
        $taskCounts = $this->tagRepository->getTaskCountsForTags($tags);

        // Build tag data for template
        $tagData = [];
        foreach ($tags as $tag) {
            $tagId = $tag->getId() ?? '';
            $tagData[] = [
                'id' => $tagId,
                'name' => $tag->getName(),
                'color' => $tag->getColor(),
                'createdAt' => $tag->getCreatedAt(),
                'taskCount' => $taskCounts[$tagId] ?? 0,
            ];
        }

        // Get sidebar data
        $sidebarProjects = $this->projectService->getTree($user);
        $sidebarTags = $this->tagRepository->findRecentlyUsedByOwner($user, 10);
        $sidebarTagsTotal = $this->tagRepository->countByOwner($user);

        return $this->render('tag/list.html.twig', [
            'tags' => $tagData,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'hasNextPage' => $page < $totalPages,
            'hasPreviousPage' => $page > 1,
            'search' => $search,
            'sidebar_projects' => $sidebarProjects,
            'sidebar_tags' => $sidebarTags,
            'sidebar_tags_total' => $sidebarTagsTotal,
        ]);
    }
}
