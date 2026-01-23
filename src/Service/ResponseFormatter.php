<?php

declare(strict_types=1);

namespace App\Service;

use App\EventListener\RequestIdListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service for formatting consistent API responses.
 *
 * All API responses follow this structure:
 * {
 *   "success": true/false,
 *   "data": {...} or null,
 *   "error": {"code": "ERROR_CODE", "message": "...", "details": {...}} or null,
 *   "meta": {"requestId": "...", "timestamp": "...", ...}
 * }
 */
final class ResponseFormatter
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Creates a successful response.
     *
     * @param mixed $data The response data
     * @param int $statusCode HTTP status code (default: 200)
     * @param array<string, mixed> $meta Additional metadata
     */
    public function success(mixed $data = null, int $statusCode = 200, array $meta = []): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $data,
            'error' => null,
            'meta' => $this->buildMeta($meta),
        ];

        return new JsonResponse($response, $statusCode);
    }

    /**
     * Creates an error response.
     *
     * @param string $message Human-readable error message
     * @param string $errorCode Machine-readable error code
     * @param int $statusCode HTTP status code
     * @param array<string, mixed> $details Additional error details
     */
    public function error(
        string $message,
        string $errorCode,
        int $statusCode = 400,
        array $details = []
    ): JsonResponse {
        $error = [
            'code' => $errorCode,
            'message' => $message,
        ];

        if (!empty($details)) {
            $error['details'] = $details;
        }

        $response = [
            'success' => false,
            'data' => null,
            'error' => $error,
            'meta' => $this->buildMeta(),
        ];

        return new JsonResponse($response, $statusCode);
    }

    /**
     * Creates a paginated response.
     *
     * @param array<int, mixed> $items The items for the current page
     * @param int $total Total number of items across all pages
     * @param int $page Current page number (1-indexed)
     * @param int $limit Items per page
     */
    public function paginated(array $items, int $total, int $page, int $limit): JsonResponse
    {
        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 0;

        $paginationMeta = [
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'totalPages' => $totalPages,
                'hasNextPage' => $page < $totalPages,
                'hasPreviousPage' => $page > 1,
            ],
        ];

        return $this->success($items, 200, $paginationMeta);
    }

    /**
     * Creates a "created" response (HTTP 201).
     *
     * @param mixed $data The created resource data
     * @param array<string, mixed> $meta Additional metadata
     */
    public function created(mixed $data = null, array $meta = []): JsonResponse
    {
        return $this->success($data, 201, $meta);
    }

    /**
     * Creates a "no content" response (HTTP 204).
     * Note: Returns empty body as per HTTP specification.
     */
    public function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }

    /**
     * Builds the meta object with standard fields.
     *
     * @param array<string, mixed> $additional Additional metadata to include
     * @return array<string, mixed>
     */
    private function buildMeta(array $additional = []): array
    {
        $meta = [
            'requestId' => $this->getRequestId(),
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339_EXTENDED),
        ];

        return array_merge($meta, $additional);
    }

    /**
     * Gets the current request ID from request attributes.
     */
    private function getRequestId(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return null;
        }

        return $request->attributes->get(RequestIdListener::REQUEST_ID_ATTRIBUTE);
    }
}
