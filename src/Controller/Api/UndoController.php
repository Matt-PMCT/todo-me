<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\InvalidUndoTokenException;
use App\Service\ProjectUndoService;
use App\Service\ResponseFormatter;
use App\Service\TaskUndoService;
use App\Service\UndoService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Generic undo controller that routes to appropriate entity undo service.
 */
#[OA\Tag(name: 'Undo', description: 'Generic undo operations')]
#[Route('/api/v1', name: 'api_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class UndoController extends AbstractController
{
    public function __construct(
        private readonly UndoService $undoService,
        private readonly TaskUndoService $taskUndoService,
        private readonly ProjectUndoService $projectUndoService,
        private readonly ResponseFormatter $responseFormatter,
    ) {
    }

    /**
     * Generic undo endpoint.
     *
     * Routes to the appropriate entity undo service based on the token's entityType.
     */
    #[Route('/undo', name: 'undo', methods: ['POST'])]
    #[OA\Post(
        summary: 'Undo an operation',
        description: 'Generic undo endpoint that routes to the appropriate entity undo service.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', description: 'The undo token'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Operation undone successfully'),
            new OA\Response(response: 400, description: 'Invalid token or expired'),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function undo(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;

        if (empty($token)) {
            throw InvalidUndoTokenException::expired();
        }

        // Peek at the token to determine entity type (without consuming it)
        $undoToken = $this->undoService->getUndoToken($user->getId() ?? '', $token);

        if ($undoToken === null) {
            throw InvalidUndoTokenException::expired();
        }

        // Route to appropriate service based on entity type
        $result = match ($undoToken->entityType) {
            'task' => $this->handleTaskUndo($user, $token),
            'project' => $this->handleProjectUndo($user, $token),
            default => throw InvalidUndoTokenException::wrongEntityType('task or project', $undoToken->entityType),
        };

        return $this->responseFormatter->success($result);
    }

    private function handleTaskUndo(User $user, string $token): array
    {
        $task = $this->taskUndoService->undo($user, $token);

        return [
            'entityType' => 'task',
            'entityId' => $task->getId(),
            'message' => 'Task operation undone successfully',
            'entity' => [
                'id' => $task->getId(),
                'title' => $task->getTitle(),
                'status' => $task->getStatus(),
            ],
        ];
    }

    private function handleProjectUndo(User $user, string $token): array
    {
        $result = $this->projectUndoService->undo($user, $token);
        $project = $result['project'];

        return [
            'entityType' => 'project',
            'entityId' => $project->getId(),
            'message' => $result['message'],
            'entity' => [
                'id' => $project->getId(),
                'name' => $project->getName(),
            ],
        ];
    }
}
