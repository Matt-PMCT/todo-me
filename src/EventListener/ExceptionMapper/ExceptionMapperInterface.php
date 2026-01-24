<?php

declare(strict_types=1);

namespace App\EventListener\ExceptionMapper;

/**
 * Interface for exception mappers that convert exceptions to error responses.
 *
 * Mappers are checked in priority order (highest first). The first mapper
 * that can handle an exception will be used to create the mapping.
 *
 * Priority ranges:
 * - 100+: Domain exceptions (most specific)
 * - 50-99: Symfony validation/security exceptions
 * - 1-49: HTTP exceptions
 * - 0: Fallback/server error
 */
interface ExceptionMapperInterface
{
    /**
     * Returns the priority of this mapper.
     *
     * Higher priority mappers are checked first, allowing more specific
     * mappers to take precedence over generic ones.
     */
    public static function getPriority(): int;

    /**
     * Determines if this mapper can handle the given exception.
     */
    public function canHandle(\Throwable $exception): bool;

    /**
     * Maps the exception to an ExceptionMapping value object.
     *
     * Only called when canHandle() returns true.
     */
    public function map(\Throwable $exception): ExceptionMapping;
}
