<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\CreateApiTokenRequest;
use App\Entity\User;
use App\Service\ApiTokenService;
use App\Service\ResponseFormatter;
use App\Service\ValidationHelper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for API token management.
 */
#[OA\Tag(name: 'API Tokens', description: 'API token management operations')]
#[Route('/api/v1/users/me/tokens', name: 'api_tokens_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ApiTokenController extends AbstractController
{
    public function __construct(
        private readonly ApiTokenService $tokenService,
        private readonly ResponseFormatter $responseFormatter,
        private readonly ValidationHelper $validationHelper,
    ) {
    }

    /**
     * List all API tokens for the current user.
     *
     * Returns token metadata only - never exposes token values or hashes.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        summary: 'List API tokens',
        description: 'Returns all API tokens for the current user (metadata only, no token values)',
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of tokens',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'tokens', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $tokens = $this->tokenService->listTokens($user);

        $tokenData = array_map(fn ($token) => [
            'id' => $token->getId(),
            'name' => $token->getName(),
            'tokenPrefix' => $token->getTokenPrefix(),
            'scopes' => $token->getScopes(),
            'expiresAt' => $token->getExpiresAt()?->format(\DateTimeInterface::RFC3339),
            'lastUsedAt' => $token->getLastUsedAt()?->format(\DateTimeInterface::RFC3339),
            'createdAt' => $token->getCreatedAt()->format(\DateTimeInterface::RFC3339),
            'isExpired' => $token->isExpired(),
        ], $tokens);

        return $this->responseFormatter->success($tokenData);
    }

    /**
     * Create a new API token.
     *
     * The plain token is returned ONLY in this response.
     * It cannot be retrieved again - if lost, the token must be revoked and a new one created.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Create API token',
        description: 'Creates a new API token. The plain token is returned ONLY in this response.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 100, example: 'My CI Token'),
                    new OA\Property(
                        property: 'scopes',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        nullable: true,
                        example: ['*'],
                        description: 'Token scopes (null or omitted means all access)'
                    ),
                    new OA\Property(
                        property: 'expiresAt',
                        type: 'string',
                        format: 'date-time',
                        nullable: true,
                        example: '2027-01-25T00:00:00+00:00',
                        description: 'Token expiration date (optional)'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Token created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'object'),
                        new OA\Property(property: 'plainToken', type: 'string', description: 'The actual token value - only shown once!'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = CreateApiTokenRequest::fromArray($data);

        $this->validationHelper->validate($dto);

        $result = $this->tokenService->createToken(
            $user,
            $dto->name,
            $dto->getScopes(),
            $dto->getExpiresAtDateTime()
        );

        $token = $result['token'];

        return $this->responseFormatter->created([
            'token' => [
                'id' => $token->getId(),
                'name' => $token->getName(),
                'tokenPrefix' => $token->getTokenPrefix(),
                'scopes' => $token->getScopes(),
                'expiresAt' => $token->getExpiresAt()?->format(\DateTimeInterface::RFC3339),
                'createdAt' => $token->getCreatedAt()->format(\DateTimeInterface::RFC3339),
            ],
            'plainToken' => $result['plainToken'],
        ]);
    }

    /**
     * Revoke (delete) an API token.
     */
    #[Route('/{id}', name: 'revoke', methods: ['DELETE'], requirements: ['id' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'])]
    #[OA\Delete(
        summary: 'Revoke API token',
        description: 'Permanently revokes (deletes) an API token',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Token revoked'),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 404, description: 'Token not found'),
        ]
    )]
    public function revoke(string $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $this->tokenService->revokeToken($user, $id);

        return $this->responseFormatter->success([
            'message' => 'Token revoked successfully',
        ]);
    }
}
