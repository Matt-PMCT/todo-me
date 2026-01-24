<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\ParseRequest;
use App\DTO\ParseResponse;
use App\Entity\User;
use App\Service\Parser\NaturalLanguageParserService;
use App\Service\ResponseFormatter;
use App\Service\ValidationHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for parsing natural language task input.
 */
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
     * Response: { title, due_date, due_time, project, tags, priority, highlights, warnings }
     */
    #[Route('/parse', name: 'parse', methods: ['POST'])]
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

        $result = $this->parserService
            ->configure($user)
            ->parse($dto->input, $user);

        $response = ParseResponse::fromTaskParseResult($result);

        return $this->responseFormatter->success($response->toArray());
    }
}
