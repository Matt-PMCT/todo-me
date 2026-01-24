<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * OpenAPI schema definitions shared across the API.
 */
#[OA\Schema(
    schema: 'ApiResponse',
    type: 'object',
    required: ['success', 'data', 'error', 'meta'],
    properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', nullable: true),
        new OA\Property(property: 'error', ref: '#/components/schemas/ApiError', nullable: true),
        new OA\Property(property: 'meta', ref: '#/components/schemas/ApiMeta'),
    ]
)]
#[OA\Schema(
    schema: 'ApiError',
    type: 'object',
    required: ['code', 'message'],
    properties: [
        new OA\Property(property: 'code', type: 'string', example: 'VALIDATION_ERROR'),
        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
        new OA\Property(property: 'details', type: 'object', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'ApiMeta',
    type: 'object',
    properties: [
        new OA\Property(property: 'requestId', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time', example: '2026-01-24T10:30:00+00:00'),
    ]
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    type: 'object',
    properties: [
        new OA\Property(property: 'total', type: 'integer', example: 100),
        new OA\Property(property: 'page', type: 'integer', example: 1),
        new OA\Property(property: 'limit', type: 'integer', example: 20),
        new OA\Property(property: 'totalPages', type: 'integer', example: 5),
        new OA\Property(property: 'hasNextPage', type: 'boolean', example: true),
        new OA\Property(property: 'hasPreviousPage', type: 'boolean', example: false),
    ]
)]
#[OA\Schema(
    schema: 'Task',
    type: 'object',
    required: ['id', 'title', 'status', 'priority', 'position', 'createdAt', 'updatedAt'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
        new OA\Property(property: 'title', type: 'string', maxLength: 500, example: 'Complete project report'),
        new OA\Property(property: 'description', type: 'string', maxLength: 2000, nullable: true, example: 'Finish the quarterly report'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'completed'], example: 'pending'),
        new OA\Property(property: 'priority', type: 'integer', minimum: 1, maximum: 5, example: 3),
        new OA\Property(property: 'dueDate', type: 'string', format: 'date', nullable: true, example: '2026-01-30'),
        new OA\Property(property: 'position', type: 'integer', example: 0),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'completedAt', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(
            property: 'project',
            type: 'object',
            nullable: true,
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'name', type: 'string'),
            ]
        ),
        new OA\Property(
            property: 'tags',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/TagSummary')
        ),
        new OA\Property(property: 'isRecurring', type: 'boolean', example: false),
        new OA\Property(property: 'recurrenceRule', type: 'string', nullable: true, example: 'every Monday'),
        new OA\Property(property: 'recurrenceType', type: 'string', nullable: true, enum: ['fixed', 'relative']),
        new OA\Property(property: 'recurrenceEndDate', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'originalTaskId', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'undoToken', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'TaskInput',
    type: 'object',
    required: ['title'],
    properties: [
        new OA\Property(property: 'title', type: 'string', maxLength: 500, example: 'Complete project report'),
        new OA\Property(property: 'description', type: 'string', maxLength: 2000, nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'completed'], default: 'pending'),
        new OA\Property(property: 'priority', type: 'integer', minimum: 1, maximum: 5, default: 3),
        new OA\Property(property: 'dueDate', type: 'string', format: 'date', nullable: true, example: '2026-01-30'),
        new OA\Property(property: 'projectId', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'tagIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true),
        new OA\Property(property: 'isRecurring', type: 'boolean', default: false),
        new OA\Property(property: 'recurrenceRule', type: 'string', nullable: true, example: 'every Monday'),
    ]
)]
#[OA\Schema(
    schema: 'Project',
    type: 'object',
    required: ['id', 'name', 'isArchived', 'createdAt', 'updatedAt'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Work'),
        new OA\Property(property: 'description', type: 'string', maxLength: 1000, nullable: true),
        new OA\Property(property: 'isArchived', type: 'boolean', example: false),
        new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updatedAt', type: 'string', format: 'date-time'),
        new OA\Property(property: 'taskCount', type: 'integer', example: 10),
        new OA\Property(property: 'completedTaskCount', type: 'integer', example: 5),
        new OA\Property(property: 'pendingTaskCount', type: 'integer', example: 5),
        new OA\Property(property: 'parentId', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'depth', type: 'integer', example: 0),
        new OA\Property(property: 'color', type: 'string', nullable: true, example: '#FF5733'),
        new OA\Property(property: 'icon', type: 'string', nullable: true),
        new OA\Property(property: 'position', type: 'integer', example: 0),
    ]
)]
#[OA\Schema(
    schema: 'ProjectInput',
    type: 'object',
    required: ['name'],
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Work'),
        new OA\Property(property: 'description', type: 'string', maxLength: 1000, nullable: true),
        new OA\Property(property: 'parentId', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'color', type: 'string', nullable: true, example: '#FF5733'),
        new OA\Property(property: 'icon', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'TagSummary',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string', example: 'urgent'),
        new OA\Property(property: 'color', type: 'string', example: '#FF0000'),
    ]
)]
#[OA\Schema(
    schema: 'BatchOperation',
    type: 'object',
    required: ['action'],
    properties: [
        new OA\Property(property: 'action', type: 'string', enum: ['create', 'update', 'delete', 'complete', 'reschedule']),
        new OA\Property(property: 'taskId', type: 'string', format: 'uuid', description: 'Required for update, delete, complete, reschedule'),
        new OA\Property(property: 'data', type: 'object', description: 'Operation data (task fields for create/update, due_date for reschedule)'),
    ]
)]
#[OA\Schema(
    schema: 'BatchResult',
    type: 'object',
    properties: [
        new OA\Property(property: 'success', type: 'boolean'),
        new OA\Property(property: 'totalOperations', type: 'integer', example: 5),
        new OA\Property(property: 'successfulOperations', type: 'integer', example: 4),
        new OA\Property(property: 'failedOperations', type: 'integer', example: 1),
        new OA\Property(
            property: 'results',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/BatchOperationResult')
        ),
        new OA\Property(property: 'undoToken', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'BatchOperationResult',
    type: 'object',
    properties: [
        new OA\Property(property: 'index', type: 'integer', example: 0),
        new OA\Property(property: 'action', type: 'string', example: 'create'),
        new OA\Property(property: 'success', type: 'boolean'),
        new OA\Property(property: 'taskId', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'error', type: 'string', nullable: true),
        new OA\Property(property: 'errorCode', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'SearchResult',
    type: 'object',
    properties: [
        new OA\Property(
            property: 'tasks',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/TaskSearchResult')
        ),
        new OA\Property(
            property: 'projects',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/ProjectSearchResult')
        ),
        new OA\Property(
            property: 'tags',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/TagSearchResult')
        ),
        new OA\Property(property: 'meta', ref: '#/components/schemas/SearchMeta'),
    ]
)]
#[OA\Schema(
    schema: 'TaskSearchResult',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'type', type: 'string', example: 'task'),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string'),
        new OA\Property(property: 'priority', type: 'integer'),
        new OA\Property(property: 'dueDate', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'projectId', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'projectName', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'ProjectSearchResult',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'type', type: 'string', example: 'project'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'isArchived', type: 'boolean'),
        new OA\Property(property: 'color', type: 'string', nullable: true),
    ]
)]
#[OA\Schema(
    schema: 'TagSearchResult',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'type', type: 'string', example: 'tag'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'color', type: 'string'),
    ]
)]
#[OA\Schema(
    schema: 'SearchMeta',
    type: 'object',
    properties: [
        new OA\Property(property: 'total', type: 'integer'),
        new OA\Property(property: 'page', type: 'integer'),
        new OA\Property(property: 'limit', type: 'integer'),
        new OA\Property(property: 'totalPages', type: 'integer'),
        new OA\Property(property: 'hasNextPage', type: 'boolean'),
        new OA\Property(property: 'hasPreviousPage', type: 'boolean'),
        new OA\Property(
            property: 'counts',
            type: 'object',
            properties: [
                new OA\Property(property: 'tasks', type: 'integer'),
                new OA\Property(property: 'projects', type: 'integer'),
                new OA\Property(property: 'tags', type: 'integer'),
            ]
        ),
    ]
)]
#[OA\Schema(
    schema: 'AuthToken',
    type: 'object',
    properties: [
        new OA\Property(property: 'token', type: 'string', example: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'),
        new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time'),
    ]
)]
final class Schemas
{
    // This class exists only to hold OpenAPI schema attributes
}
