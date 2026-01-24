<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\RegisterRequest;

class RegisterRequestTest extends DtoTestCase
{
    public function testValidRequest(): void
    {
        $dto = new RegisterRequest(
            email: 'user@example.com',
            password: 'password123',
        );

        $violations = $this->validate($dto);

        $this->assertCount(0, $violations, $this->formatViolations($violations));
    }

    public function testEmptyEmailViolation(): void
    {
        $dto = new RegisterRequest(email: '', password: 'password123');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'email');
    }

    public function testBlankEmailViolation(): void
    {
        $dto = new RegisterRequest(email: '   ', password: 'password123');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'email');
    }

    public function testInvalidEmailFormatViolation(): void
    {
        $dto = new RegisterRequest(email: 'not-an-email', password: 'password123');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'email');
    }

    public function testEmailTooLongViolation(): void
    {
        // Email max length is 180 characters
        $longEmail = $this->generateString(170) . '@example.com';
        $dto = new RegisterRequest(email: $longEmail, password: 'password123');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'email');
    }

    public function testEmailAtMaxLengthIsValid(): void
    {
        // Email max length is 180 characters
        $email = $this->generateString(167) . '@example.com'; // 167 + 12 = 179
        $dto = new RegisterRequest(email: $email, password: 'password123');

        $violations = $this->validate($dto);

        // Check if there's specifically an email format violation (not length)
        $hasLengthViolation = false;
        foreach ($violations as $violation) {
            if ($violation->getPropertyPath() === 'email' && str_contains($violation->getMessage(), 'characters')) {
                $hasLengthViolation = true;
            }
        }
        $this->assertFalse($hasLengthViolation);
    }

    /**
     * @dataProvider validEmailsProvider
     */
    public function testValidEmails(string $email): void
    {
        $dto = new RegisterRequest(email: $email, password: 'password123');

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'email');
    }

    public static function validEmailsProvider(): array
    {
        return [
            'simple email' => ['user@example.com'],
            'email with subdomain' => ['user@mail.example.com'],
            'email with plus' => ['user+tag@example.com'],
            'email with dots' => ['first.last@example.com'],
        ];
    }

    public function testEmptyPasswordViolation(): void
    {
        $dto = new RegisterRequest(email: 'user@example.com', password: '');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'password');
    }

    public function testBlankPasswordViolation(): void
    {
        $dto = new RegisterRequest(email: 'user@example.com', password: '   ');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'password');
    }

    public function testPasswordTooShortViolation(): void
    {
        // Password min length is 8 characters
        $dto = new RegisterRequest(email: 'user@example.com', password: '1234567');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'password');
    }

    public function testPasswordAtMinLengthIsValid(): void
    {
        // Password min length is 8 characters
        $dto = new RegisterRequest(email: 'user@example.com', password: '12345678');

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'password');
    }

    public function testLongPasswordIsValid(): void
    {
        $dto = new RegisterRequest(
            email: 'user@example.com',
            password: $this->generateString(100),
        );

        $violations = $this->validate($dto);

        $this->assertNoViolationFor($violations, 'password');
    }

    public function testBothFieldsEmptyViolations(): void
    {
        $dto = new RegisterRequest(email: '', password: '');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'email');
        $this->assertHasViolation($violations, 'password');
    }

    public function testFromArrayWithValidData(): void
    {
        $dto = RegisterRequest::fromArray([
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->assertSame('user@example.com', $dto->email);
        $this->assertSame('password123', $dto->password);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $dto = RegisterRequest::fromArray([]);

        $this->assertSame('', $dto->email);
        $this->assertSame('', $dto->password);
    }

    public function testFromArrayTypeCasting(): void
    {
        $dto = RegisterRequest::fromArray([
            'email' => 123,
            'password' => 456,
        ]);

        $this->assertSame('123', $dto->email);
        $this->assertSame('456', $dto->password);
    }
}
