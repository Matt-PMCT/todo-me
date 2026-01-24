<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\Tests\Unit\UnitTestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Base test case for DTO validation tests.
 */
abstract class DtoTestCase extends UnitTestCase
{
    protected ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    /**
     * Validates a DTO and returns the constraint violations.
     */
    protected function validate(object $dto): ConstraintViolationListInterface
    {
        return $this->validator->validate($dto);
    }

    /**
     * Asserts that the given property path has a violation.
     */
    protected function assertHasViolation(
        ConstraintViolationListInterface $violations,
        string $propertyPath,
    ): void {
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === $propertyPath) {
                $this->assertTrue(true);
                return;
            }
        }

        $this->fail(sprintf(
            'Expected a violation for property "%s", but none was found. Violations: %s',
            $propertyPath,
            $this->formatViolations($violations)
        ));
    }

    /**
     * Asserts that the given property path has no violations.
     */
    protected function assertNoViolationFor(
        ConstraintViolationListInterface $violations,
        string $propertyPath,
    ): void {
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === $propertyPath) {
                $this->fail(sprintf(
                    'Expected no violation for property "%s", but found: %s',
                    $propertyPath,
                    $violation->getMessage()
                ));
            }
        }

        $this->assertTrue(true);
    }

    /**
     * Formats violations for debugging output.
     */
    protected function formatViolations(ConstraintViolationListInterface $violations): string
    {
        if (count($violations) === 0) {
            return '(none)';
        }

        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = sprintf('%s: %s', $violation->getPropertyPath(), $violation->getMessage());
        }

        return implode(', ', $messages);
    }

    /**
     * Generates a string of specified length.
     */
    protected function generateString(int $length): string
    {
        return str_repeat('a', $length);
    }
}
