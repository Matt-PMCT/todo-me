<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\BatchOperationsRequest;
use App\Entity\User;
use App\Exception\ValidationException;
use App\Service\BatchOperationService;
use App\Service\ResponseFormatter;
use App\Service\ValidationHelper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for batch task operations.
 */
#[OA\Tag(name: 'Batch Operations', description: 'Execute multiple task operations in a single request')]
#[Route('/api/v1/tasks', name: 'api_tasks_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class BatchController extends AbstractController
{
    public function __construct(
        private readonly BatchOperationService $batchOperationService,
        private readonly ResponseFormatter $responseFormatter,
        private readonly ValidationHelper $validationHelper,
    ) {
    }

    /**
     * Execute batch task operations.
     */
    #[Route('/batch', name: 'batch', methods: ['POST'])]
    #[OA\Post(
        summary: 'Execute batch operations',
        description: 'Execute multiple task operations in a single request. Supports create, update, delete, complete, and reschedule actions.',
        parameters: [
            new OA\Parameter(name: 'atomic', in: 'query', description: 'Rollback all changes on any failure', schema: new OA\Schema(type: 'boolean', default: false)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['operations'],
                properties: [
                    new OA\Property(
                        property: 'operations',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/BatchOperation'),
                        maxItems: 100,
                        description: 'Array of operations to execute'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'All operations successful',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiResponse'),
                        new OA\Schema(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/BatchResult')]),
                    ]
                )
            ),
            new OA\Response(response: 207, description: 'Partial success (some operations failed)'),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function batch(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);
        $atomic = $request->query->getBoolean('atomic', false);

        $batchRequest = BatchOperationsRequest::fromArray($data, $atomic);

        // Validate the batch request
        $this->validationHelper->validate($batchRequest);

        // Validate individual operations
        $operationErrors = $batchRequest->validateOperations();
        if (!empty($operationErrors)) {
            throw new ValidationException($this->formatOperationErrors($operationErrors));
        }

        $result = $this->batchOperationService->execute($user, $batchRequest);

        // Determine HTTP status code based on results
        $statusCode = $result->success ? 200 : 207; // 207 Multi-Status for partial success

        return $this->responseFormatter->success($result->toArray(), $statusCode);
    }

    /**
     * Undo a batch operation.
     */
    #[Route('/batch/undo/{token}', name: 'batch_undo', methods: ['POST'])]
    #[OA\Post(
        summary: 'Undo batch operation',
        description: 'Reverses all changes made by a batch operation using the undo token',
        parameters: [
            new OA\Parameter(name: 'token', in: 'path', required: true, description: 'Undo token from batch operation', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Batch undone successfully'),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 422, description: 'Invalid or expired undo token'),
        ]
    )]
    public function undoBatch(string $token): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $result = $this->batchOperationService->undoBatch($user, $token);

        return $this->responseFormatter->success($result);
    }

    /**
     * Formats operation validation errors into a flat array.
     *
     * @param array<int, array<string, string>> $operationErrors
     * @return array<string, string>
     */
    private function formatOperationErrors(array $operationErrors): array
    {
        $errors = [];
        foreach ($operationErrors as $index => $opErrors) {
            foreach ($opErrors as $field => $message) {
                $errors["operations[$index].$field"] = $message;
            }
        }
        return $errors;
    }
}
