<?php

declare(strict_types=1);

namespace App\Service;

use App\Interface\EncryptionServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service for encrypting/decrypting sensitive data using AES-256-GCM.
 *
 * Used for TOTP secrets and other data that must be decryptable (unlike
 * passwords/tokens which can be hashed).
 */
final class EncryptionService implements EncryptionServiceInterface
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;
    private const TAG_LENGTH = 16;

    private readonly string $encryptionKey;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        string $kernelSecret,
    ) {
        // Derive a 256-bit key from the kernel secret
        $this->encryptionKey = hash('sha256', $kernelSecret, true);
    }

    /**
     * Encrypt plaintext data.
     *
     * @param string $plaintext The data to encrypt
     *
     * @return string Base64-encoded encrypted data (IV + tag + ciphertext)
     */
    public function encrypt(string $plaintext): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Combine IV + tag + ciphertext and base64 encode
        return base64_encode($iv.$tag.$ciphertext);
    }

    /**
     * Decrypt encrypted data.
     *
     * @param string $encrypted Base64-encoded encrypted data
     *
     * @return string The decrypted plaintext
     *
     * @throws \RuntimeException If decryption fails
     */
    public function decrypt(string $encrypted): string
    {
        $data = base64_decode($encrypted, true);
        if ($data === false || strlen($data) < self::IV_LENGTH + self::TAG_LENGTH) {
            throw new \RuntimeException('Invalid encrypted data format');
        }

        $iv = substr($data, 0, self::IV_LENGTH);
        $tag = substr($data, self::IV_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($data, self::IV_LENGTH + self::TAG_LENGTH);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed - data may be corrupted or tampered with');
        }

        return $plaintext;
    }
}
