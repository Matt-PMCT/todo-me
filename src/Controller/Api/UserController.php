<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\UserSettingsRequest;
use App\Entity\User;
use App\Repository\ProjectRepository;
use App\Repository\SavedFilterRepository;
use App\Repository\TagRepository;
use App\Repository\TaskRepository;
use App\Service\ResponseFormatter;
use App\Service\UserService;
use App\Service\ValidationHelper;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * User account management endpoints (GDPR compliance).
 */
#[OA\Tag(name: 'User', description: 'User account management and GDPR compliance')]
#[Route('/api/v1/users', name: 'api_users_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly TaskRepository $taskRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly TagRepository $tagRepository,
        private readonly SavedFilterRepository $savedFilterRepository,
        private readonly ResponseFormatter $responseFormatter,
        private readonly ValidationHelper $validationHelper,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Get the current user's settings.
     */
    #[Route('/me/settings', name: 'get_settings', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get user settings',
        description: 'Returns the current user\'s settings with defaults applied',
        responses: [
            new OA\Response(
                response: 200,
                description: 'User settings',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function getSettings(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->responseFormatter->success([
            'settings' => $user->getSettingsWithDefaults(),
        ]);
    }

    /**
     * Update the current user's settings.
     */
    #[Route('/me/settings', name: 'update_settings', methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Update user settings',
        description: 'Updates the current user\'s settings (timezone, date format, start of week, task spacing, theme)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'timezone', type: 'string', example: 'America/New_York'),
                    new OA\Property(property: 'dateFormat', type: 'string', enum: ['MDY', 'DMY', 'YMD'], example: 'MDY'),
                    new OA\Property(property: 'startOfWeek', type: 'integer', enum: [0, 1], example: 0),
                    new OA\Property(property: 'taskSpacing', type: 'string', enum: ['comfortable', 'compact'], example: 'comfortable'),
                    new OA\Property(property: 'theme', type: 'string', enum: ['light', 'dark', 'system'], example: 'system'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Settings updated successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse')
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateSettings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);
        $dto = UserSettingsRequest::fromArray($data);

        $this->validationHelper->validate($dto);

        // Get current settings and merge with new values
        $currentSettings = $user->getSettings();
        $newSettings = array_merge($currentSettings, $dto->toSettingsArray());

        $user->setSettings($newSettings);
        $this->entityManager->flush();

        return $this->responseFormatter->success([
            'settings' => $user->getSettingsWithDefaults(),
        ]);
    }

    /**
     * Export all user data (GDPR data portability).
     */
    #[Route('/me/export', name: 'export', methods: ['GET'])]
    #[OA\Get(
        summary: 'Export user data',
        description: 'Exports all user data including tasks, projects, tags, and saved filters. Supports GDPR data portability requirements.',
        responses: [
            new OA\Response(
                response: 200,
                description: 'User data export',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', type: 'object'),
                        new OA\Property(property: 'projects', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'tasks', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'savedFilters', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'exportedAt', type: 'string', format: 'date-time'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    public function exportData(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Gather all user data
        $projects = $this->projectRepository->findByOwner($user);
        $tasks = $this->taskRepository->findByOwner($user);
        $tags = $this->tagRepository->findByOwner($user);
        $savedFilters = $this->savedFilterRepository->findByOwner($user);

        $exportData = [
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::RFC3339),
                'updatedAt' => $user->getUpdatedAt()?->format(\DateTimeInterface::RFC3339),
            ],
            'projects' => array_map(fn ($p) => $this->serializeProject($p), $projects),
            'tasks' => array_map(fn ($t) => $this->serializeTask($t), $tasks),
            'tags' => array_map(fn ($t) => $this->serializeTag($t), $tags),
            'savedFilters' => array_map(fn ($f) => $this->serializeSavedFilter($f), $savedFilters),
            'exportedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ];

        return $this->responseFormatter->success($exportData);
    }

    /**
     * Delete user account and all associated data (GDPR right to erasure).
     */
    #[Route('/me', name: 'delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Delete user account',
        description: 'Permanently deletes the user account and all associated data. Requires password confirmation. This action cannot be undone.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password'],
                properties: [
                    new OA\Property(property: 'password', type: 'string', description: 'Current password for confirmation'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Account deleted successfully'),
            new OA\Response(response: 401, description: 'Not authenticated or invalid password'),
            new OA\Response(response: 422, description: 'Password not provided'),
        ]
    )]
    public function deleteAccount(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = $this->validationHelper->decodeJsonBody($request);

        if (!isset($data['password']) || $data['password'] === '') {
            return $this->responseFormatter->error(
                'Password is required to confirm account deletion',
                'VALIDATION_ERROR',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['fields' => ['password' => ['This field is required']]]
            );
        }

        // Verify password
        if (!$this->userService->validatePassword($user, $data['password'])) {
            return $this->responseFormatter->error(
                'Invalid password',
                'INVALID_PASSWORD',
                Response::HTTP_UNAUTHORIZED
            );
        }

        // Delete the user and all associated data
        $this->userService->deleteUser($user);

        return $this->responseFormatter->noContent();
    }

    private function serializeProject(object $project): array
    {
        return [
            'id' => $project->getId(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'isArchived' => $project->isArchived(),
            'parentId' => $project->getParent()?->getId(),
            'createdAt' => $project->getCreatedAt()?->format(\DateTimeInterface::RFC3339),
            'updatedAt' => $project->getUpdatedAt()?->format(\DateTimeInterface::RFC3339),
        ];
    }

    private function serializeTask(object $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription(),
            'status' => $task->getStatus(),
            'priority' => $task->getPriority(),
            'dueDate' => $task->getDueDate()?->format(\DateTimeInterface::RFC3339),
            'projectId' => $task->getProject()?->getId(),
            'parentTaskId' => $task->getParentTask()?->getId(),
            'tagIds' => array_map(fn ($t) => $t->getId(), $task->getTags()->toArray()),
            'recurrenceRule' => $task->getRecurrenceRule(),
            'recurrenceType' => $task->getRecurrenceType(),
            'completedAt' => $task->getCompletedAt()?->format(\DateTimeInterface::RFC3339),
            'createdAt' => $task->getCreatedAt()?->format(\DateTimeInterface::RFC3339),
            'updatedAt' => $task->getUpdatedAt()?->format(\DateTimeInterface::RFC3339),
        ];
    }

    private function serializeTag(object $tag): array
    {
        return [
            'id' => $tag->getId(),
            'name' => $tag->getName(),
            'color' => $tag->getColor(),
            'createdAt' => $tag->getCreatedAt()?->format(\DateTimeInterface::RFC3339),
        ];
    }

    private function serializeSavedFilter(object $filter): array
    {
        return [
            'id' => $filter->getId(),
            'name' => $filter->getName(),
            'filters' => $filter->getFilters(),
            'createdAt' => $filter->getCreatedAt()?->format(\DateTimeInterface::RFC3339),
            'updatedAt' => $filter->getUpdatedAt()?->format(\DateTimeInterface::RFC3339),
        ];
    }
}
