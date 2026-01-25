<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\RedisService;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Health check endpoint for monitoring and load balancers.
 */
#[OA\Tag(name: 'Health', description: 'Health check endpoints')]
#[Route('/api/v1/health', name: 'api_health_')]
final class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RedisService $redisService,
    ) {
    }

    /**
     * Check the health status of the application and its dependencies.
     */
    #[Route('', name: 'check', methods: ['GET'])]
    #[OA\Get(
        summary: 'Health check',
        description: 'Returns the health status of the application and its dependencies (database, Redis)',
        responses: [
            new OA\Response(
                response: 200,
                description: 'All services healthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'healthy'),
                        new OA\Property(
                            property: 'services',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'database', type: 'string', example: 'healthy'),
                                new OA\Property(property: 'redis', type: 'string', example: 'healthy'),
                            ]
                        ),
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                    ]
                )
            ),
            new OA\Response(
                response: 503,
                description: 'One or more services unhealthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'unhealthy'),
                        new OA\Property(
                            property: 'services',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'database', type: 'string', example: 'healthy'),
                                new OA\Property(property: 'redis', type: 'string', example: 'unhealthy'),
                            ]
                        ),
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                    ]
                )
            ),
        ]
    )]
    public function check(): JsonResponse
    {
        $services = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
        ];

        $allHealthy = !in_array('unhealthy', $services, true);

        $response = [
            'status' => $allHealthy ? 'healthy' : 'unhealthy',
            'services' => $services,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ];

        return new JsonResponse(
            $response,
            $allHealthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE
        );
    }

    /**
     * Simple liveness probe for Kubernetes/container orchestration.
     */
    #[Route('/live', name: 'live', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liveness probe',
        description: 'Simple endpoint that returns OK if the application is running',
        responses: [
            new OA\Response(response: 200, description: 'Application is alive'),
        ]
    )]
    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    /**
     * Readiness probe - checks if the application is ready to receive traffic.
     */
    #[Route('/ready', name: 'ready', methods: ['GET'])]
    #[OA\Get(
        summary: 'Readiness probe',
        description: 'Checks if the application is ready to receive traffic (database connected)',
        responses: [
            new OA\Response(response: 200, description: 'Application is ready'),
            new OA\Response(response: 503, description: 'Application not ready'),
        ]
    )]
    public function ready(): JsonResponse
    {
        $dbHealthy = $this->checkDatabase() === 'healthy';

        if (!$dbHealthy) {
            return new JsonResponse(
                ['status' => 'not ready', 'reason' => 'database unavailable'],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        return new JsonResponse(['status' => 'ready']);
    }

    private function checkDatabase(): string
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return 'healthy';
        } catch (\Throwable) {
            return 'unhealthy';
        }
    }

    private function checkRedis(): string
    {
        try {
            return $this->redisService->ping() ? 'healthy' : 'unhealthy';
        } catch (\Throwable) {
            return 'unhealthy';
        }
    }
}
