<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Exception\ForbiddenException;
use App\Exception\InvalidPriorityException;
use App\Exception\InvalidRecurrenceException;
use App\Exception\InvalidStatusException;
use App\Exception\ValidationException;
use App\Interface\UserOwnedInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Helper service for validation operations.
 *
 * Provides methods for validating DTOs and common field formats.
 */
class ValidationHelper
{
    public const VALID_RECURRENCE_TYPES = ['absolute', 'relative'];

    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * Validates a DTO object and throws ValidationException if there are errors.
     *
     * @throws ValidationException If validation fails
     */
    public function validate(object $dto): void
    {
        $violations = $this->validator->validate($dto);

        if ($violations->count() > 0) {
            throw new ValidationException(
                $this->formatValidationErrors($violations),
            );
        }
    }

    /**
     * Validates that a status is one of the allowed task statuses.
     *
     * @param string $status The status to validate
     *
     * @throws InvalidStatusException If status is invalid
     */
    public function validateTaskStatus(string $status): void
    {
        if (!in_array($status, Task::STATUSES, true)) {
            throw InvalidStatusException::forTaskStatus($status);
        }
    }

    /**
     * Validates that a priority is within the allowed range (1-5).
     *
     * @param int $priority The priority to validate
     *
     * @throws InvalidPriorityException If priority is out of range
     */
    public function validateTaskPriority(int $priority): void
    {
        if ($priority < Task::PRIORITY_MIN || $priority > Task::PRIORITY_MAX) {
            throw InvalidPriorityException::forTaskPriority($priority);
        }
    }

    /**
     * Validates an email address format.
     *
     * @param string $email The email to validate
     *
     * @throws ValidationException If email format is invalid
     */
    public function validateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::invalidEmail($email);
        }
    }

    /**
     * Validates a password meets security requirements.
     *
     * Requirements:
     * - Minimum 8 characters
     * - At least one letter
     * - At least one number
     *
     * @param string $password The password to validate
     *
     * @throws ValidationException If password does not meet requirements
     */
    public function validatePassword(string $password): void
    {
        $hasMinLength = strlen($password) >= 8;
        $hasLetter = preg_match('/[a-zA-Z]/', $password) === 1;
        $hasNumber = preg_match('/[0-9]/', $password) === 1;

        if (!$hasMinLength || !$hasLetter || !$hasNumber) {
            throw ValidationException::invalidPassword();
        }
    }

    /**
     * Validates a UUID format.
     *
     * @param string $uuid The UUID to validate
     *
     * @return bool True if valid UUID format
     */
    public function validateUuid(string $uuid): bool
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        return preg_match($pattern, $uuid) === 1;
    }

    /**
     * Validates an ISO 8601 date format.
     *
     * Supports formats:
     * - YYYY-MM-DD
     * - YYYY-MM-DDTHH:MM:SS
     * - YYYY-MM-DDTHH:MM:SS.sss
     * - YYYY-MM-DDTHH:MM:SSZ
     * - YYYY-MM-DDTHH:MM:SS+HH:MM
     *
     * @param string $date The date string to validate
     *
     * @return bool True if valid ISO 8601 date format
     */
    public function validateDateFormat(string $date): bool
    {
        // Try common ISO 8601 formats
        $formats = [
            'Y-m-d',                    // 2024-01-15
            'Y-m-d\TH:i:s',             // 2024-01-15T10:30:00
            'Y-m-d\TH:i:s.u',           // 2024-01-15T10:30:00.000
            'Y-m-d\TH:i:sP',            // 2024-01-15T10:30:00+00:00
            'Y-m-d\TH:i:s.uP',          // 2024-01-15T10:30:00.000+00:00
            \DateTimeInterface::ATOM,    // 2024-01-15T10:30:00+00:00
        ];

        foreach ($formats as $format) {
            $dateTime = \DateTimeImmutable::createFromFormat($format, $date);

            if ($dateTime !== false && $dateTime->format($format) === $date) {
                return true;
            }
        }

        // Also check UTC 'Z' suffix format
        if (str_ends_with($date, 'Z')) {
            $dateWithoutZ = substr($date, 0, -1);
            $formatsWithZ = ['Y-m-d\TH:i:s', 'Y-m-d\TH:i:s.u'];

            foreach ($formatsWithZ as $format) {
                $dateTime = \DateTimeImmutable::createFromFormat($format, $dateWithoutZ);

                if ($dateTime !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Converts validation violations to a field => message array.
     *
     * @param ConstraintViolationListInterface $violations The constraint violations
     *
     * @return array<string, string> Field name to error message mapping
     */
    public function formatValidationErrors(ConstraintViolationListInterface $violations): array
    {
        $errors = [];

        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath();

            // Only keep the first error per field
            if (!isset($errors[$propertyPath])) {
                $errors[$propertyPath] = (string) $violation->getMessage();
            }
        }

        return $errors;
    }

    /**
     * Validates that a recurrence type is one of the allowed values.
     *
     * @param string|null $type The recurrence type to validate
     *
     * @throws InvalidRecurrenceException If the recurrence type is invalid
     */
    public function validateRecurrenceType(?string $type): void
    {
        if ($type === null) {
            return;
        }

        if (!in_array($type, self::VALID_RECURRENCE_TYPES, true)) {
            throw InvalidRecurrenceException::forRecurrenceType($type);
        }
    }

    /**
     * Validates that a user owns the given project.
     *
     * @param User $user The user to check
     * @param Project|null $project The project to validate
     *
     * @throws ForbiddenException If the user does not own the project
     */
    public function validateTaskProjectOwnership(User $user, ?Project $project): void
    {
        if ($project === null) {
            return;
        }

        if ($project->getOwner()?->getId() !== $user->getId()) {
            throw ForbiddenException::notOwner('project');
        }
    }

    /**
     * Validates that a user owns the given resource.
     *
     * @param User $user The user to check
     * @param UserOwnedInterface $resource The resource to validate
     *
     * @throws ForbiddenException If the user does not own the resource
     */
    public function validateOwnership(User $user, UserOwnedInterface $resource): void
    {
        $owner = $resource->getOwner();

        if ($owner === null || $owner->getId() !== $user->getId()) {
            throw ForbiddenException::resourceAccessDenied();
        }
    }
}
