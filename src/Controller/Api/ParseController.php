<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\ParseRequest;
use App\DTO\ParseResponse;
use App\Entity\User;
use App\Service\Parser\NaturalLanguageParserService;
use App\Service\ResponseFormatter;
use App\Service\ValidationHelper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for parsing natural language task input.
 */
#[OA\Tag(name: 'Parse', description: 'Natural language parsing')]
#[Route('/api/v1', name: 'api_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ParseController extends AbstractController
{
    public function __construct(
        private readonly NaturalLanguageParserService $parserService,
        private readonly ResponseFormatter $responseFormatter,
        private readonly ValidationHelper $validationHelper,
    ) {
    }

    /**
     * Parse natural language input for task creation preview.
     *
     * POST /api/v1/parse
     * Body: { "input": "Review proposal #work @urgent tomorrow p3" }
     * Query: ?preview=true (default) - don't create new tags during parsing
     * Response: { title, due_date, due_time, project, tags, priority, highlights, warnings }
     */
    #[Route('/parse', name: 'parse', methods: ['POST'])]
    #[OA\Post(
        summary: 'Parse natural language',
        description: 'Parse natural language input for task creation preview. Does not create a task.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['input'],
                properties: [
                    new OA\Property(property: 'input', type: 'string', description: 'Natural language task description', example: 'Review proposal tomorrow at 3pm #work'),
                ]
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'preview',
                in: 'query',
                description: 'If true (default), tags are not created during parsing',
                schema: new OA\Schema(type: 'boolean', default: true)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Parse result with extracted fields'),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 400, description: 'Invalid input'),
        ]
    )]
    public function parse(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);

        try {
            $dto = ParseRequest::fromArray($data);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        // Default to preview mode (don't create tags while typing)
        $preview = $request->query->getBoolean('preview', true);

        $result = $this->parserService
            ->configure($user)
            ->parse($dto->input, $user, $preview);

        $response = ParseResponse::fromTaskParseResult($result);

        return $this->responseFormatter->success($response->toArray());
    }
}
