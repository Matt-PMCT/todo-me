<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Exception\ValidationException;
use App\Service\ResponseFormatter;
use App\Service\SessionService;
use App\Service\TokenHelper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for managing user sessions.
 */
#[OA\Tag(name: 'Sessions', description: 'User session management')]
#[Route('/api/v1/sessions', name: 'api_session_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class SessionController extends AbstractController
{
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly ResponseFormatter $responseFormatter,
        private readonly TokenHelper $tokenHelper,
    ) {
    }

    /**
     * List all active sessions for the current user.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List user sessions',
        description: 'Returns all active sessions for the authenticated user',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of sessions',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $sessions = $this->sessionService->listSessions($user);

        // Get current token hash to identify current session
        $currentToken = $this->tokenHelper->extractFromRequest($request);
        $currentTokenHash = $currentToken !== null ? hash('sha256', $currentToken) : null;

        $sessionData = array_map(function ($session) use ($currentTokenHash) {
            return [
                'id' => $session->getId(),
                'device' => $session->getDevice(),
                'browser' => $session->getBrowser(),
                'ipAddress' => $session->getIpAddress(),
                'createdAt' => $session->getCreatedAt()->format(\DateTimeInterface::RFC3339),
                'lastActiveAt' => $session->getLastActiveAt()->format(\DateTimeInterface::RFC3339),
                'isCurrent' => $session->getTokenHash() === $currentTokenHash,
            ];
        }, $sessions);

        return $this->responseFormatter->success([
            'sessions' => $sessionData,
            'total' => count($sessionData),
        ]);
    }

    /**
     * Revoke a specific session.
     */
    #[Route('/{id}', name: 'revoke', methods: ['DELETE'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    #[OA\Delete(
        summary: 'Revoke a session',
        description: 'Revokes a specific session by ID',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Session revoked'),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Session not found'),
        ]
    )]
    public function revoke(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->sessionService->revokeSession($user, $id);

        return $this->responseFormatter->success([
            'message' => 'Session revoked successfully',
        ]);
    }

    /**
     * Revoke all sessions except the current one.
     */
    #[Route('/revoke-others', name: 'revoke_others', methods: ['POST'])]
    #[OA\Post(
        summary: 'Revoke all other sessions',
        description: 'Revokes all sessions except the current one',
        responses: [
            new OA\Response(response: 200, description: 'Sessions revoked'),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function revokeOthers(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $currentToken = $this->tokenHelper->extractFromRequest($request);

        if ($currentToken === null) {
            throw ValidationException::forField('token', 'Could not identify current session');
        }

        $revokedCount = $this->sessionService->revokeOtherSessions($user, $currentToken);

        return $this->responseFormatter->success([
            'message' => 'Other sessions revoked successfully',
            'revokedCount' => $revokedCount,
        ]);
    }
}
