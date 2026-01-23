<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Service for generating cryptographically secure tokens.
 */
class TokenGenerator
{
    private const API_TOKEN_LENGTH = 32; // 32 bytes = 64 hex characters
    private const PASSWORD_RESET_TOKEN_LENGTH = 16; // 16 bytes = 32 hex characters

    /**
     * Generates a cryptographically secure API token.
     *
     * @return string 64-character hexadecimal token
     */
    public function generateApiToken(): string
    {
        return bin2hex(random_bytes(self::API_TOKEN_LENGTH));
    }

    /**
     * Generates a cryptographically secure password reset token.
     *
     * @return string 32-character hexadecimal token
     */
    public function generatePasswordResetToken(): string
    {
        return bin2hex(random_bytes(self::PASSWORD_RESET_TOKEN_LENGTH));
    }
}
