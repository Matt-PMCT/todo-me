<?php

declare(strict_types=1);

namespace App\EventListener;

use App\EventListener\ExceptionMapper\ExceptionMapperRegistry;
use App\Service\ApiLogger;
use App\Service\ResponseFormatter;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Listener that catches all exceptions for API routes and returns consistent error responses.
 *
 * Delegates exception mapping to specialized ExceptionMapperInterface implementations
 * via the ExceptionMapperRegistry.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, method: 'onKernelException', priority: 100)]
final class ApiExceptionListener
{
    public function __construct(
        private readonly ResponseFormatter $responseFormatter,
        private readonly ApiLogger $apiLogger,
        private readonly ExceptionMapperRegistry $mapperRegistry,
    ) {
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Only handle exceptions for /api/* routes
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        $exception = $event->getThrowable();

        // Log the exception
        $this->apiLogger->logError($exception, [
            'uri' => $request->getRequestUri(),
            'method' => $request->getMethod(),
        ]);

        // Map exception to response using the registry
        $mapping = $this->mapperRegistry->map($exception);

        // Create error response
        $response = $this->responseFormatter->error(
            $mapping->message,
            $mapping->errorCode,
            $mapping->statusCode,
            $mapping->details,
        );

        // Add any additional headers from HTTP exceptions
        if ($exception instanceof HttpExceptionInterface) {
            foreach ($exception->getHeaders() as $name => $value) {
                $response->headers->set($name, $value);
            }
        }

        $event->setResponse($response);
    }
}
