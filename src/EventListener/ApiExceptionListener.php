<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Exception\EntityNotFoundException;
use App\Exception\ForbiddenException;
use App\Exception\InvalidPriorityException;
use App\Exception\InvalidRecurrenceException;
use App\Exception\InvalidStatusException;
use App\Exception\UnauthorizedException;
use App\Exception\ValidationException;
use App\Service\ApiLogger;
use App\Service\ResponseFormatter;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException as SymfonyUnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Listener that catches all exceptions for API routes and returns consistent error responses.
 *
 * Maps exceptions to appropriate HTTP status codes and error codes:
 * - VALIDATION_ERROR (400/422)
 * - NOT_FOUND (404)
 * - UNAUTHORIZED (401)
 * - FORBIDDEN (403)
 * - RATE_LIMITED (429)
 * - INVALID_STATUS (400)
 * - INVALID_PRIORITY (400)
 * - CONFLICT (409)
 * - SERVER_ERROR (500)
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, method: 'onKernelException', priority: 100)]
final class ApiExceptionListener
{
    public function __construct(
        private readonly ResponseFormatter $responseFormatter,
        private readonly ApiLogger $apiLogger,
        private readonly string $environment,
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

        // Map exception to response
        [$errorCode, $message, $statusCode, $details] = $this->mapException($exception);

        // Create error response
        $response = $this->responseFormatter->error(
            $message,
            $errorCode,
            $statusCode,
            $details
        );

        // Add any additional headers from HTTP exceptions
        if ($exception instanceof HttpExceptionInterface) {
            foreach ($exception->getHeaders() as $name => $value) {
                $response->headers->set($name, $value);
            }
        }

        $event->setResponse($response);
    }

    /**
     * Maps an exception to error code, message, status code, and details.
     *
     * @param \Throwable $exception
     * @return array{0: string, 1: string, 2: int, 3: array<string, mixed>}
     */
    private function mapException(\Throwable $exception): array
    {
        // Handle Symfony validation exceptions
        if ($exception instanceof ValidationFailedException) {
            return $this->handleValidationException($exception);
        }

        // Handle custom domain exceptions with errorCode property
        if ($exception instanceof InvalidStatusException) {
            return [
                $exception->errorCode,
                $exception->getMessage(),
                $exception->getStatusCode(),
                [
                    'invalidStatus' => $exception->invalidStatus,
                    'validStatuses' => $exception->validStatuses,
                ],
            ];
        }

        if ($exception instanceof InvalidPriorityException) {
            return [
                $exception->errorCode,
                $exception->getMessage(),
                $exception->getStatusCode(),
                [
                    'invalidPriority' => $exception->invalidPriority,
                    'minPriority' => $exception->minPriority,
                    'maxPriority' => $exception->maxPriority,
                ],
            ];
        }

        if ($exception instanceof InvalidRecurrenceException) {
            $details = ['reason' => $exception->reason];
            if ($exception->invalidValue !== null) {
                $details['invalidValue'] = $exception->invalidValue;
            }
            if ($exception->validValues !== null) {
                $details['validValues'] = $exception->validValues;
            }
            return [
                $exception->errorCode,
                $exception->getMessage(),
                $exception->getStatusCode(),
                $details,
            ];
        }

        if ($exception instanceof ValidationException) {
            return [
                $exception->errorCode,
                $exception->getMessage(),
                $exception->getStatusCode(),
                ['errors' => $exception->getErrors()],
            ];
        }

        if ($exception instanceof EntityNotFoundException) {
            return [
                $exception->errorCode,
                $exception->getMessage(),
                $exception->getStatusCode(),
                [
                    'entityType' => $exception->entityType,
                    'entityId' => $exception->entityId,
                ],
            ];
        }

        if ($exception instanceof UnauthorizedException) {
            return [
                $exception->errorCode,
                $exception->getMessage(),
                $exception->getStatusCode(),
                [],
            ];
        }

        if ($exception instanceof ForbiddenException) {
            return [
                $exception->errorCode,
                $exception->getMessage(),
                $exception->getStatusCode(),
                ['reason' => $exception->reason],
            ];
        }

        // Handle Symfony Security exceptions (for unauthenticated/unauthorized requests)
        if ($exception instanceof AuthenticationException) {
            return [
                'UNAUTHORIZED',
                'Authentication required',
                Response::HTTP_UNAUTHORIZED,
                [],
            ];
        }

        if ($exception instanceof AccessDeniedException) {
            return [
                'UNAUTHORIZED',
                'Authentication required',
                Response::HTTP_UNAUTHORIZED,
                [],
            ];
        }

        // Handle HTTP exceptions
        if ($exception instanceof HttpExceptionInterface) {
            return $this->handleHttpException($exception);
        }

        // Handle all other exceptions as server errors
        return $this->handleServerError($exception);
    }

    /**
     * Handles validation exceptions.
     *
     * @return array{0: string, 1: string, 2: int, 3: array<string, mixed>}
     */
    private function handleValidationException(ValidationFailedException $exception): array
    {
        $violations = $exception->getViolations();
        $errors = [];

        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath();
            $errors[$propertyPath][] = $violation->getMessage();
        }

        return [
            'VALIDATION_ERROR',
            'Validation failed',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['errors' => $errors],
        ];
    }

    /**
     * Handles HTTP exceptions.
     *
     * @return array{0: string, 1: string, 2: int, 3: array<string, mixed>}
     */
    private function handleHttpException(HttpExceptionInterface $exception): array
    {
        $statusCode = $exception->getStatusCode();
        $message = $exception->getMessage();

        // Use default message if empty
        if (empty($message)) {
            $message = Response::$statusTexts[$statusCode] ?? 'An error occurred';
        }

        $errorCode = match (true) {
            $exception instanceof NotFoundHttpException => 'NOT_FOUND',
            $exception instanceof SymfonyUnauthorizedHttpException => 'UNAUTHORIZED',
            $exception instanceof AccessDeniedHttpException => 'FORBIDDEN',
            $exception instanceof TooManyRequestsHttpException => 'RATE_LIMIT_EXCEEDED',
            $exception instanceof BadRequestHttpException => 'BAD_REQUEST',
            $exception instanceof UnprocessableEntityHttpException => 'VALIDATION_ERROR',
            $exception instanceof ConflictHttpException => 'CONFLICT',
            default => $this->getErrorCodeFromStatusCode($statusCode),
        };

        return [
            $errorCode,
            $message,
            $statusCode,
            [],
        ];
    }

    /**
     * Handles server errors (500-level exceptions).
     *
     * @return array{0: string, 1: string, 2: int, 3: array<string, mixed>}
     */
    private function handleServerError(\Throwable $exception): array
    {
        // In development, show the actual error message
        // In production, show a generic message
        $message = $this->environment === 'dev'
            ? $exception->getMessage()
            : 'An internal server error occurred';

        $details = [];

        // In development, include additional debug information
        if ($this->environment === 'dev') {
            $details = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];
        }

        return [
            'SERVER_ERROR',
            $message,
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $details,
        ];
    }

    /**
     * Gets an error code based on HTTP status code.
     */
    private function getErrorCodeFromStatusCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'RATE_LIMIT_EXCEEDED',
            500 => 'SERVER_ERROR',
            501 => 'NOT_IMPLEMENTED',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'ERROR',
        };
    }
}
