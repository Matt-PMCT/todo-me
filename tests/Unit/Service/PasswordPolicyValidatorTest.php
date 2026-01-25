<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\PasswordPolicyValidator;
use App\Tests\Unit\UnitTestCase;

final class PasswordPolicyValidatorTest extends UnitTestCase
{
    private PasswordPolicyValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PasswordPolicyValidator();
    }

    public function testValidPasswordPasses(): void
    {
        $errors = $this->validator->validate('SecurePass123!');
        $this->assertEmpty($errors);
    }

    public function testPasswordTooShortFails(): void
    {
        $errors = $this->validator->validate('Short1A');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('12 characters', $errors[0]);
    }

    public function testPasswordMissingUppercaseFails(): void
    {
        $errors = $this->validator->validate('lowercase12345');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('uppercase', $errors[0]);
    }

    public function testPasswordMissingLowercaseFails(): void
    {
        $errors = $this->validator->validate('UPPERCASE12345');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('lowercase', $errors[0]);
    }

    public function testPasswordMissingNumberFails(): void
    {
        $errors = $this->validator->validate('NoNumbersHere!');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('number', $errors[0]);
    }

    public function testCommonPasswordFails(): void
    {
        $errors = $this->validator->validate('Password1234');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('common', $errors[0]);
    }

    public function testPasswordContainingEmailFails(): void
    {
        $errors = $this->validator->validate('Testuser123456', 'testuser@example.com');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('email', $errors[0]);
    }

    public function testPasswordContainingUsernameFails(): void
    {
        $errors = $this->validator->validate('Johndoe123456', null, 'johndoe');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('username', $errors[0]);
    }

    public function testGetRequirementsReturnsExpectedKeys(): void
    {
        $requirements = $this->validator->getRequirements();

        $this->assertArrayHasKey('minLength', $requirements);
        $this->assertArrayHasKey('requireUppercase', $requirements);
        $this->assertArrayHasKey('requireLowercase', $requirements);
        $this->assertArrayHasKey('requireNumber', $requirements);
        $this->assertEquals(12, $requirements['minLength']);
        $this->assertTrue($requirements['requireUppercase']);
    }

    public function testMultipleErrorsReturned(): void
    {
        // Short, no uppercase, no number
        $errors = $this->validator->validate('short');
        $this->assertGreaterThan(1, count($errors));
    }
}
