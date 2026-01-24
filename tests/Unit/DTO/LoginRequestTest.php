<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\LoginRequest;

class LoginRequestTest extends DtoTestCase
{
    public function testValidRequest(): void
    {
        $dto = new LoginRequest(
            email: 'user@example.com',
            password: 'password123',
        );

        $violations = $this->validate($dto);

        $this->assertCount(0, $violations, $this->formatViolations($violations));
    }

    public function testEmptyEmailViolation(): void
    {
        $dto = new LoginRequest(email: '', password: 'password123');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'email');
    }

    public function testBlankEmailViolation(): void
    {
        $dto = new LoginRequest(email: '   ', password: 'password123');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'email');
    }

    public function testInvalidEmailFormatViolation(): void
    {
        $dto = new LoginRequest(email: 'not-an-email', password: 'password123');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'email');
    }

    public function testInvalidEmailWithoutDomainViolation(): void
    {
        $dto = new LoginRequest(email: 'user@', password: 'password123');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'email');
    }

    public function testInvalidEmailWithoutAtViolation(): void
    {
        $dto = new LoginRequest(email: 'user.example.com', password: 'password123');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'email');
    }

    /**
     * @dataProvider validEmailsProvider
     */
    public function testValidEmails(string $email): void
    {
        $dto = new LoginRequest(email: $email, password: 'password123');

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
            'email with numbers' => ['user123@example456.com'],
        ];
    }

    public function testEmptyPasswordViolation(): void
    {
        $dto = new LoginRequest(email: 'user@example.com', password: '');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'password');
    }

    /**
     * Whitespace-only passwords are allowed.
     *
     * Unlike email (which uses trim normalizer), we intentionally do NOT trim passwords
     * because some users/password generators create passwords with spaces. A password
     * of "   " is unusual but technically valid - we preserve password integrity.
     *
     * See LoginRequest.php for the full decision documentation (2026-01-24).
     */
    public function testWhitespaceOnlyPasswordIsAllowed(): void
    {
        $dto = new LoginRequest(email: 'user@example.com', password: '   ');

        $violations = $this->validate($dto);

        // Whitespace-only password should pass validation - we don't trim passwords
        $this->assertNoViolationFor($violations, 'password');
    }

    public function testBothFieldsEmptyViolations(): void
    {
        $dto = new LoginRequest(email: '', password: '');

        $violations = $this->validate($dto);

        $this->assertHasViolation($violations, 'email');
        $this->assertHasViolation($violations, 'password');
    }

    public function testFromArrayWithValidData(): void
    {
        $dto = LoginRequest::fromArray([
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->assertSame('user@example.com', $dto->email);
        $this->assertSame('password123', $dto->password);
    }

    public function testFromArrayWithEmptyData(): void
    {
        $dto = LoginRequest::fromArray([]);

        $this->assertSame('', $dto->email);
        $this->assertSame('', $dto->password);
    }

    public function testFromArrayTypeCasting(): void
    {
        $dto = LoginRequest::fromArray([
            'email' => 123,
            'password' => 456,
        ]);

        $this->assertSame('123', $dto->email);
        $this->assertSame('456', $dto->password);
    }

    public function testFromArrayWithPartialData(): void
    {
        $dto = LoginRequest::fromArray(['email' => 'user@example.com']);

        $this->assertSame('user@example.com', $dto->email);
        $this->assertSame('', $dto->password);
    }
}
