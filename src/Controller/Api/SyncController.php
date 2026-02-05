<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Interface\SyncServiceInterface;
use App\Service\ResponseFormatter;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for real-time sync polling.
 */
#[OA\Tag(name: 'Sync', description: 'Real-time sync polling')]
#[Route('/api/v1/sync', name: 'api_sync_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SyncController extends AbstractController
{
    public function __construct(
        private readonly SyncServiceInterface $syncService,
        private readonly ResponseFormatter $responseFormatter,
    ) {
    }

    /**
     * Poll for changes since a given version.
     *
     * Returns changes made by other tabs/devices since the specified version.
     * Changes originating from the requesting tab (identified by X-Tab-Id header) are excluded.
     */
    #[Route('/poll', name: 'poll', methods: ['GET'])]
    #[OA\Get(
        summary: 'Poll for sync changes',
        description: 'Returns changes since the specified version. Changes from the requesting tab are excluded.',
        parameters: [
            new OA\Parameter(name: 'lastVersion', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sync changes',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'version', type: 'integer'),
                        new OA\Property(property: 'changes', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Missing lastVersion parameter'),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function poll(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $lastVersion = $request->query->get('lastVersion');
        if ($lastVersion === null) {
            return $this->responseFormatter->error(
                'The lastVersion query parameter is required',
                'VALIDATION_ERROR',
                400
            );
        }

        $lastVersionInt = max(0, (int) $lastVersion);
        $originTabId = $request->headers->get('X-Tab-Id');
        if ($originTabId !== null && strlen($originTabId) > 64) {
            $originTabId = substr($originTabId, 0, 64);
        }

        $result = $this->syncService->getChangesSince($user, $lastVersionInt);

        // Filter out changes from the requesting tab
        if ($originTabId !== null && !empty($result['changes'])) {
            $result['changes'] = array_values(array_filter(
                $result['changes'],
                fn (array $change) => ($change['originTabId'] ?? null) !== $originTabId
            ));
        }

        return $this->responseFormatter->success($result);
    }
}
