<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper\Symfony;

use App\EventListener\ExceptionMapper\ExceptionMapperInterface;
use App\EventListener\ExceptionMapper\ExceptionMapping;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Maps Symfony ValidationFailedException to error response.
 */
#[AutoconfigureTag('app.exception_mapper')]
final class ValidationFailedExceptionMapper implements ExceptionMapperInterface
{
    public static function getPriority(): int
    {
        return 75;
    }

    public function canHandle(\Throwable $exception): bool
    {
        return $exception instanceof ValidationFailedException;
    }

    public function map(\Throwable $exception): ExceptionMapping
    {
        assert($exception instanceof ValidationFailedException);

        $violations = $exception->getViolations();
        $errors = [];

        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath();
            $errors[$propertyPath][] = $violation->getMessage();
        }

        return new ExceptionMapping(
            'VALIDATION_ERROR',
            'Validation failed',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['errors' => $errors],
        );
    }
}
