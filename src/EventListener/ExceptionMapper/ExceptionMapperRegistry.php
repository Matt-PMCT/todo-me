<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registry that aggregates exception mappers and finds the appropriate one for an exception.
 *
 * Mappers are sorted by priority (highest first) and the first mapper that can handle
 * the exception is used to create the mapping.
 */
final class ExceptionMapperRegistry
{
    /** @var ExceptionMapperInterface[] */
    private array $mappers;

    /**
     * @param iterable<ExceptionMapperInterface> $mappers
     */
    public function __construct(
        #[TaggedIterator('app.exception_mapper', defaultPriorityMethod: 'getPriority')]
        iterable $mappers,
    ) {
        $this->mappers = iterator_to_array($mappers);
    }

    /**
     * Finds the appropriate mapper for the exception and returns the mapping.
     *
     * Falls back to a generic server error if no mapper handles the exception.
     */
    public function map(\Throwable $exception): ExceptionMapping
    {
        foreach ($this->mappers as $mapper) {
            if ($mapper->canHandle($exception)) {
                return $mapper->map($exception);
            }
        }

        // Fallback if no mapper handles the exception (shouldn't happen with ServerErrorMapper)
        return new ExceptionMapping(
            'SERVER_ERROR',
            'An internal server error occurred',
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}
