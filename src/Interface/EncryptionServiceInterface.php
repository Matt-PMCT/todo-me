<?php

declare(strict_types=1);

namespace App\Interface;

/**
 * Interface for encryption/decryption services.
 */
interface EncryptionServiceInterface
{
    /**
     * Encrypt plaintext data.
     *
     * @param string $plaintext The data to encrypt
     *
     * @return string Encrypted data (format depends on implementation)
     */
    public function encrypt(string $plaintext): string;

    /**
     * Decrypt encrypted data.
     *
     * @param string $encrypted The encrypted data
     *
     * @return string The decrypted plaintext
     *
     * @throws \RuntimeException If decryption fails
     */
    public function decrypt(string $encrypted): string;
}
