<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Entity\Task;
use App\Exception\ForbiddenException;
use App\Exception\InvalidPriorityException;
use App\Exception\InvalidStatusException;
use App\Exception\ValidationException;
use App\Service\ValidationHelper;
use App\Tests\Unit\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationHelperTest extends UnitTestCase
{
    private ValidatorInterface&MockObject $validator;
    private ValidationHelper $validationHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->validationHelper = new ValidationHelper($this->validator);
    }

    // ========================================
    // Validate DTO Tests
    // ========================================

    public function testValidatePassesWhenNoViolations(): void
    {
        $dto = new \stdClass();
        $violations = new ConstraintViolationList();

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn($violations);

        // Should not throw - mock expectation verifies the call was made
        $this->validationHelper->validate($dto);
    }

    public function testValidateThrowsExceptionWhenViolationsExist(): void
    {
        $dto = new \stdClass();

        $violation = new ConstraintViolation(
            'Title is required',
            null,
            [],
            null,
            'title',
            null
        );
        $violations = new ConstraintViolationList([$violation]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($dto)
            ->willReturn($violations);

        $this->expectException(ValidationException::class);
        $this->validationHelper->validate($dto);
    }

    // ========================================
    // Validate Task Status Tests
    // ========================================

    #[DataProvider('validStatusProvider')]
    public function testValidateTaskStatusWithValidStatuses(string $status): void
    {
        $this->expectNotToPerformAssertions();

        // Should not throw
        $this->validationHelper->validateTaskStatus($status);
    }

    public static function validStatusProvider(): array
    {
        return [
            'pending' => [Task::STATUS_PENDING],
            'in_progress' => [Task::STATUS_IN_PROGRESS],
            'completed' => [Task::STATUS_COMPLETED],
        ];
    }

    #[DataProvider('invalidStatusProvider')]
    public function testValidateTaskStatusWithInvalidStatusThrowsException(string $status): void
    {
        $this->expectException(InvalidStatusException::class);
        $this->validationHelper->validateTaskStatus($status);
    }

    public static function invalidStatusProvider(): array
    {
        return [
            'invalid' => ['invalid'],
            'empty' => [''],
            'uppercase' => ['PENDING'],
            'with_spaces' => ['in progress'],
            'typo' => ['completd'],
        ];
    }

    // ========================================
    // Validate Task Priority Tests
    // ========================================

    #[DataProvider('validPriorityProvider')]
    public function testValidateTaskPriorityWithValidPriorities(int $priority): void
    {
        $this->expectNotToPerformAssertions();

        // Should not throw
        $this->validationHelper->validateTaskPriority($priority);
    }

    public static function validPriorityProvider(): array
    {
        return [
            'min' => [Task::PRIORITY_MIN],
            'max' => [Task::PRIORITY_MAX],
            'middle' => [2],
        ];
    }

    #[DataProvider('invalidPriorityProvider')]
    public function testValidateTaskPriorityWithInvalidPriorityThrowsException(int $priority): void
    {
        $this->expectException(InvalidPriorityException::class);
        $this->validationHelper->validateTaskPriority($priority);
    }

    public static function invalidPriorityProvider(): array
    {
        return [
            'negative' => [-1],
            'too_high' => [5],
            'much_too_high' => [100],
        ];
    }

    // ========================================
    // Validate Email Tests
    // ========================================

    #[DataProvider('validEmailProvider')]
    public function testValidateEmailWithValidFormats(string $email): void
    {
        $this->expectNotToPerformAssertions();

        // Should not throw
        $this->validationHelper->validateEmail($email);
    }

    public static function validEmailProvider(): array
    {
        return [
            'simple' => ['test@example.com'],
            'with_subdomain' => ['test@mail.example.com'],
            'with_plus' => ['test+tag@example.com'],
            'with_dots' => ['first.last@example.com'],
            'with_numbers' => ['test123@example.com'],
            'long_tld' => ['test@example.museum'],
        ];
    }

    #[DataProvider('invalidEmailProvider')]
    public function testValidateEmailWithInvalidFormatsThrowsException(string $email): void
    {
        $this->expectException(ValidationException::class);
        $this->validationHelper->validateEmail($email);
    }

    public static function invalidEmailProvider(): array
    {
        return [
            'no_at' => ['testexample.com'],
            'no_domain' => ['test@'],
            'no_local' => ['@example.com'],
            'double_at' => ['test@@example.com'],
            'spaces' => ['test @example.com'],
            'no_tld' => ['test@example'],
        ];
    }

    // ========================================
    // Validate Password Tests
    // ========================================

    #[DataProvider('validPasswordProvider')]
    public function testValidatePasswordWithValidPasswords(string $password): void
    {
        $this->expectNotToPerformAssertions();

        // Should not throw
        $this->validationHelper->validatePassword($password);
    }

    public static function validPasswordProvider(): array
    {
        return [
            'simple' => ['password1'],
            'with_special' => ['P@ssw0rd!'],
            'long' => ['averylongpassword123'],
            'mixed_case' => ['Password1'],
            'exactly_8_chars' => ['pass1234'],
        ];
    }

    #[DataProvider('invalidPasswordProvider')]
    public function testValidatePasswordWithInvalidPasswordsThrowsException(string $password): void
    {
        $this->expectException(ValidationException::class);
        $this->validationHelper->validatePassword($password);
    }

    public static function invalidPasswordProvider(): array
    {
        return [
            'too_short' => ['pass1'],
            'no_number' => ['password'],
            'no_letter' => ['12345678'],
            'empty' => [''],
            'just_7_chars' => ['pass123'],
        ];
    }

    // ========================================
    // Validate UUID Tests
    // ========================================

    #[DataProvider('validUuidProvider')]
    public function testValidateUuidWithValidUuids(string $uuid): void
    {
        $result = $this->validationHelper->validateUuid($uuid);
        $this->assertTrue($result);
    }

    public static function validUuidProvider(): array
    {
        return [
            'v1' => ['550e8400-e29b-11d4-a716-446655440000'],
            'v4' => ['550e8400-e29b-41d4-a716-446655440000'],
            'v4_lowercase' => ['a0eebc99-9c0b-4ef8-bb6d-6bb9bd380a11'],
            'v4_uppercase' => ['A0EEBC99-9C0B-4EF8-BB6D-6BB9BD380A11'],
            'v4_mixed_case' => ['A0eeBC99-9c0B-4ef8-Bb6d-6BB9bd380A11'],
        ];
    }

    #[DataProvider('invalidUuidProvider')]
    public function testValidateUuidWithInvalidUuidsReturnsFalse(string $uuid): void
    {
        $result = $this->validationHelper->validateUuid($uuid);
        $this->assertFalse($result);
    }

    public static function invalidUuidProvider(): array
    {
        return [
            'empty' => [''],
            'too_short' => ['550e8400-e29b-41d4'],
            'no_dashes' => ['550e8400e29b41d4a716446655440000'],
            'wrong_format' => ['550e8400-e29b-41d4-a716'],
            'invalid_chars' => ['550e8400-e29b-41d4-a716-44665544000g'],
        ];
    }

    // ========================================
    // Validate Date Format Tests
    // ========================================

    #[DataProvider('validDateFormatProvider')]
    public function testValidateDateFormatWithValidFormats(string $date): void
    {
        $result = $this->validationHelper->validateDateFormat($date);
        $this->assertTrue($result);
    }

    public static function validDateFormatProvider(): array
    {
        return [
            'date_only' => ['2024-01-15'],
            'datetime' => ['2024-01-15T10:30:00'],
            'datetime_with_timezone' => ['2024-01-15T10:30:00+00:00'],
            'datetime_utc' => ['2024-01-15T10:30:00Z'],
            'datetime_negative_tz' => ['2024-01-15T10:30:00-05:00'],
        ];
    }

    #[DataProvider('invalidDateFormatProvider')]
    public function testValidateDateFormatWithInvalidFormatsReturnsFalse(string $date): void
    {
        $result = $this->validationHelper->validateDateFormat($date);
        $this->assertFalse($result);
    }

    public static function invalidDateFormatProvider(): array
    {
        return [
            'empty' => [''],
            'wrong_separator' => ['2024/01/15'],
            'american_format' => ['01-15-2024'],
            'no_leading_zeros' => ['2024-1-15'],
            'invalid_month' => ['2024-13-15'],
            'invalid_day' => ['2024-01-32'],
            'text' => ['January 15, 2024'],
        ];
    }

    // ========================================
    // Format Validation Errors Tests
    // ========================================

    public function testFormatValidationErrorsConvertsViolationsToArray(): void
    {
        $violation1 = new ConstraintViolation(
            'Title is required',
            null,
            [],
            null,
            'title',
            null
        );
        $violation2 = new ConstraintViolation(
            'Email is invalid',
            null,
            [],
            null,
            'email',
            null
        );
        $violations = new ConstraintViolationList([$violation1, $violation2]);

        $result = $this->validationHelper->formatValidationErrors($violations);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertEquals('Title is required', $result['title']);
        $this->assertEquals('Email is invalid', $result['email']);
    }

    public function testFormatValidationErrorsKeepsOnlyFirstErrorPerField(): void
    {
        $violation1 = new ConstraintViolation(
            'Title is required',
            null,
            [],
            null,
            'title',
            null
        );
        $violation2 = new ConstraintViolation(
            'Title must be at least 3 characters',
            null,
            [],
            null,
            'title',
            null
        );
        $violations = new ConstraintViolationList([$violation1, $violation2]);

        $result = $this->validationHelper->formatValidationErrors($violations);

        $this->assertCount(1, $result);
        $this->assertEquals('Title is required', $result['title']);
    }

    public function testFormatValidationErrorsWithEmptyList(): void
    {
        $violations = new ConstraintViolationList();

        $result = $this->validationHelper->formatValidationErrors($violations);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFormatValidationErrorsHandlesNestedPaths(): void
    {
        $violation = new ConstraintViolation(
            'Invalid tag',
            null,
            [],
            null,
            'tags[0].name',
            null
        );
        $violations = new ConstraintViolationList([$violation]);

        $result = $this->validationHelper->formatValidationErrors($violations);

        $this->assertArrayHasKey('tags[0].name', $result);
        $this->assertEquals('Invalid tag', $result['tags[0].name']);
    }

    // ========================================
    // Validate Task Project Ownership Tests
    // ========================================

    public function testValidateTaskProjectOwnershipAcceptsNullProject(): void
    {
        $this->expectNotToPerformAssertions();

        $user = $this->createUserWithId();

        // Should not throw for null project
        $this->validationHelper->validateTaskProjectOwnership($user, null);
    }

    public function testValidateTaskProjectOwnershipPassesForMatchingOwner(): void
    {
        $this->expectNotToPerformAssertions();

        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user);

        // Should not throw
        $this->validationHelper->validateTaskProjectOwnership($user, $project);
    }

    public function testValidateTaskProjectOwnershipThrowsForDifferentOwner(): void
    {
        $user1 = $this->createUserWithId('user-1');
        $user2 = $this->createUserWithId('user-2', 'other@example.com');
        $project = $this->createProjectWithId('project-123', $user2);

        $this->expectException(ForbiddenException::class);
        $this->validationHelper->validateTaskProjectOwnership($user1, $project);
    }

    // ========================================
    // Validate Ownership Tests
    // ========================================

    public function testValidateOwnershipPassesForMatchingOwner(): void
    {
        $this->expectNotToPerformAssertions();

        $user = $this->createUserWithId('user-123');
        $project = $this->createProjectWithId('project-123', $user);

        // Should not throw
        $this->validationHelper->validateOwnership($user, $project);
    }

    public function testValidateOwnershipThrowsForDifferentOwner(): void
    {
        $user1 = $this->createUserWithId('user-1');
        $user2 = $this->createUserWithId('user-2', 'other@example.com');
        $project = $this->createProjectWithId('project-123', $user2);

        $this->expectException(ForbiddenException::class);
        $this->validationHelper->validateOwnership($user1, $project);
    }

    public function testValidateOwnershipThrowsForNullOwner(): void
    {
        $user = $this->createUserWithId('user-123');
        $project = new Project();
        $project->setName('Orphan Project');

        $this->expectException(ForbiddenException::class);
        $this->validationHelper->validateOwnership($user, $project);
    }
}
