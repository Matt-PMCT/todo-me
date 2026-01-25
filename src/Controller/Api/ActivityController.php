<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\ActivityLogService;
use App\Service\ResponseFormatter;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for activity log operations.
 */
#[OA\Tag(name: 'Activity', description: 'Activity log operations')]
#[Route('/api/v1/activity', name: 'api_activity_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ActivityController extends AbstractController
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
        private readonly ResponseFormatter $responseFormatter,
    ) {
    }

    /**
     * List recent activity.
     *
     * Query parameters:
     * - page: Page number (default: 1)
     * - limit: Items per page (default: 20, max: 100)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List activity',
        description: 'List recent activity with pagination',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Activity list'),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = max(1, (int) $request->query->get('page', '1'));
        $limit = min(100, max(1, (int) $request->query->get('limit', '20')));

        $result = $this->activityLogService->getActivity($user, $page, $limit);

        return $this->responseFormatter->success($result);
    }
}
