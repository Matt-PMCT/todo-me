<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Service for validating password strength and policy compliance.
 */
final class PasswordPolicyValidator
{
    private const MIN_LENGTH = 12;

    private const COMMON_PASSWORDS = [
        'password1234',
        'password12345',
        'qwerty123456',
        '123456789012',
        'letmein123456',
        'welcome12345',
        'admin1234567',
        'iloveyou1234',
    ];

    /**
     * Validates a password against the security policy.
     *
     * @param string $password The password to validate
     * @param string|null $email Optional email to check against
     * @param string|null $username Optional username to check against
     *
     * @return array<string> Array of error messages, empty if valid
     */
    public function validate(string $password, ?string $email = null, ?string $username = null): array
    {
        $errors = [];

        if (strlen($password) < self::MIN_LENGTH) {
            $errors[] = sprintf('Password must be at least %d characters long', self::MIN_LENGTH);
        }

        if (preg_match('/[A-Z]/', $password) !== 1) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (preg_match('/[a-z]/', $password) !== 1) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (preg_match('/[0-9]/', $password) !== 1) {
            $errors[] = 'Password must contain at least one number';
        }

        if (in_array(strtolower($password), self::COMMON_PASSWORDS, true)) {
            $errors[] = 'Password is too common';
        }

        if ($email !== null && $email !== '') {
            $emailLower = strtolower($email);
            $passwordLower = strtolower($password);

            if (str_contains($passwordLower, $emailLower)) {
                $errors[] = 'Password must not contain your email address';
            }

            $atPosition = strpos($email, '@');
            if ($atPosition !== false) {
                $localPart = strtolower(substr($email, 0, $atPosition));
                if ($localPart !== '' && str_contains($passwordLower, $localPart)) {
                    $errors[] = 'Password must not contain your email username';
                }
            }
        }

        if ($username !== null && $username !== '') {
            $usernameLower = strtolower($username);
            $passwordLower = strtolower($password);

            if (str_contains($passwordLower, $usernameLower)) {
                $errors[] = 'Password must not contain your username';
            }
        }

        return $errors;
    }

    /**
     * Returns the password policy requirements.
     *
     * @return array<string, bool|int> Policy requirements
     */
    public function getRequirements(): array
    {
        return [
            'minLength' => self::MIN_LENGTH,
            'requireUppercase' => true,
            'requireLowercase' => true,
            'requireNumber' => true,
            'blockCommonPasswords' => true,
            'blockEmailInPassword' => true,
            'blockUsernameInPassword' => true,
        ];
    }
}
