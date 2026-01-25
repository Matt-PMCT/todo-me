<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\ImportService;
use App\Service\ResponseFormatter;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for importing data from various formats.
 *
 * Supports:
 * - JSON (our native export format)
 * - Todoist export format
 * - CSV format
 */
#[OA\Tag(name: 'Import', description: 'Data import operations')]
#[Route('/api/v1/import', name: 'api_import_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ImportController extends AbstractController
{
    public function __construct(
        private readonly ImportService $importService,
        private readonly ResponseFormatter $responseFormatter,
    ) {
    }

    /**
     * Import data from JSON format (our native export format).
     *
     * Expected format:
     * {
     *   "projects": [{"name": "Work", "description": "...", "isArchived": false}],
     *   "tags": [{"name": "urgent", "color": "#FF0000"}],
     *   "tasks": [{"title": "...", "status": "pending", "priority": 3, "project": "Work", "tags": ["urgent"]}]
     * }
     */
    #[Route('/json', name: 'json', methods: ['POST'])]
    #[OA\Post(
        summary: 'Import data from JSON',
        description: 'Import tasks, projects, and tags from JSON format (our native export format)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'projects',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'isArchived', type: 'boolean'),
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'tags',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'color', type: 'string'),
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'tasks',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'title', type: 'string'),
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'completed']),
                                new OA\Property(property: 'priority', type: 'integer', minimum: 0, maximum: 4),
                                new OA\Property(property: 'dueDate', type: 'string', format: 'date'),
                                new OA\Property(property: 'project', type: 'string', description: 'Project name'),
                                new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string'), description: 'Tag names'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Import successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'message', type: 'string'),
                                new OA\Property(
                                    property: 'stats',
                                    properties: [
                                        new OA\Property(property: 'tasks', type: 'integer'),
                                        new OA\Property(property: 'projects', type: 'integer'),
                                        new OA\Property(property: 'tags', type: 'integer'),
                                    ],
                                    type: 'object'
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid JSON format or import failed'),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function importJson(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $content = $request->getContent();
        $data = json_decode($content, true);

        if ($data === null && $content !== 'null') {
            return $this->responseFormatter->error(
                'Invalid JSON format',
                'INVALID_FORMAT',
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!is_array($data)) {
            return $this->responseFormatter->error(
                'JSON content must be an object',
                'INVALID_FORMAT',
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $stats = $this->importService->importJson($user, $data);

            return $this->responseFormatter->success([
                'message' => sprintf(
                    'Imported %d tasks, %d projects, %d tags',
                    $stats['tasks'],
                    $stats['projects'],
                    $stats['tags']
                ),
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return $this->responseFormatter->error(
                'Import failed: '.$e->getMessage(),
                'IMPORT_FAILED',
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Import data from Todoist export format.
     *
     * Expected format:
     * {
     *   "projects": [{"id": 123, "name": "Work", "color": "red"}],
     *   "labels": [{"id": 456, "name": "urgent", "color": "red"}],
     *   "items": [{"id": 789, "content": "Task title", "project_id": 123, "labels": ["urgent"], "priority": 4, "due": {"date": "2026-01-30"}}]
     * }
     */
    #[Route('/todoist', name: 'todoist', methods: ['POST'])]
    #[OA\Post(
        summary: 'Import data from Todoist',
        description: 'Import tasks, projects, and labels from Todoist export format',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'projects',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'color', type: 'string'),
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'labels',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'color', type: 'string'),
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'content', type: 'string'),
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'project_id', type: 'integer'),
                                new OA\Property(property: 'labels', type: 'array', items: new OA\Items(type: 'string')),
                                new OA\Property(property: 'priority', type: 'integer', minimum: 1, maximum: 4),
                                new OA\Property(
                                    property: 'due',
                                    properties: [
                                        new OA\Property(property: 'date', type: 'string', format: 'date'),
                                    ],
                                    type: 'object'
                                ),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Import successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'message', type: 'string'),
                                new OA\Property(
                                    property: 'stats',
                                    properties: [
                                        new OA\Property(property: 'tasks', type: 'integer'),
                                        new OA\Property(property: 'projects', type: 'integer'),
                                        new OA\Property(property: 'tags', type: 'integer'),
                                    ],
                                    type: 'object'
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid format or import failed'),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function importTodoist(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $content = $request->getContent();
        $data = json_decode($content, true);

        if ($data === null && $content !== 'null') {
            return $this->responseFormatter->error(
                'Invalid JSON format',
                'INVALID_FORMAT',
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!is_array($data)) {
            return $this->responseFormatter->error(
                'JSON content must be an object',
                'INVALID_FORMAT',
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $stats = $this->importService->importTodoist($user, $data);

            return $this->responseFormatter->success([
                'message' => sprintf(
                    'Imported %d tasks, %d projects, %d tags',
                    $stats['tasks'],
                    $stats['projects'],
                    $stats['tags']
                ),
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return $this->responseFormatter->error(
                'Import failed: '.$e->getMessage(),
                'IMPORT_FAILED',
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Import data from CSV format.
     *
     * Expected headers: title,description,status,priority,dueDate,project,tags
     * Tags should be comma-separated within the tags column.
     */
    #[Route('/csv', name: 'csv', methods: ['POST'])]
    #[OA\Post(
        summary: 'Import data from CSV',
        description: 'Import tasks from CSV format. Expected headers: title,description,status,priority,dueDate,project,tags',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'text/csv',
                schema: new OA\Schema(type: 'string', example: "title,description,status,priority,dueDate,project,tags\nComplete report,Quarterly report,pending,3,2026-01-30,Work,\"urgent,important\"")
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Import successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'message', type: 'string'),
                                new OA\Property(
                                    property: 'stats',
                                    properties: [
                                        new OA\Property(property: 'tasks', type: 'integer'),
                                        new OA\Property(property: 'projects', type: 'integer'),
                                        new OA\Property(property: 'tags', type: 'integer'),
                                    ],
                                    type: 'object'
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Empty content or import failed'),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function importCsv(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $content = $request->getContent();

        if (empty($content)) {
            return $this->responseFormatter->error(
                'No CSV content provided',
                'EMPTY_CONTENT',
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $stats = $this->importService->importCsv($user, $content);

            return $this->responseFormatter->success([
                'message' => sprintf(
                    'Imported %d tasks, %d projects, %d tags',
                    $stats['tasks'],
                    $stats['projects'],
                    $stats['tags']
                ),
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return $this->responseFormatter->error(
                'Import failed: '.$e->getMessage(),
                'IMPORT_FAILED',
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
