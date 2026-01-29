<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\EncryptionService;
use App\Tests\Unit\UnitTestCase;

/**
 * Unit tests for EncryptionService.
 */
final class EncryptionServiceTest extends UnitTestCase
{
    private const TEST_SECRET = 'test_kernel_secret_for_encryption_testing';

    private EncryptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EncryptionService(self::TEST_SECRET);
    }

    public function testEncryptReturnsBase64EncodedString(): void
    {
        $plaintext = 'test data';

        $encrypted = $this->service->encrypt($plaintext);

        // Should be valid base64
        $this->assertNotFalse(base64_decode($encrypted, true));
        // Should be different from plaintext
        $this->assertNotSame($plaintext, $encrypted);
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $plaintext = 'Hello, World!';

        $encrypted = $this->service->encrypt($plaintext);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertSame($plaintext, $decrypted);
    }

    public function testEncryptDecryptWithEmptyString(): void
    {
        $plaintext = '';

        $encrypted = $this->service->encrypt($plaintext);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertSame($plaintext, $decrypted);
    }

    public function testEncryptDecryptWithSpecialCharacters(): void
    {
        // UTF-8 and unicode characters
        $plaintext = "Hello \u{1F600} World! Ümlauts: äöü 日本語 emoji: \u{2764}\u{FE0F}";

        $encrypted = $this->service->encrypt($plaintext);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertSame($plaintext, $decrypted);
    }

    public function testEncryptProducesDifferentOutputEachTime(): void
    {
        $plaintext = 'same input';

        $encrypted1 = $this->service->encrypt($plaintext);
        $encrypted2 = $this->service->encrypt($plaintext);

        // Due to random IV, encrypting the same plaintext should produce different ciphertext
        $this->assertNotSame($encrypted1, $encrypted2);

        // But both should decrypt to the same value
        $this->assertSame($plaintext, $this->service->decrypt($encrypted1));
        $this->assertSame($plaintext, $this->service->decrypt($encrypted2));
    }

    public function testDecryptWithInvalidBase64ThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid encrypted data format');

        $this->service->decrypt('not!valid@base64###');
    }

    public function testDecryptWithTruncatedDataThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid encrypted data format');

        // IV (12 bytes) + Tag (16 bytes) = 28 bytes minimum
        // This is only 10 bytes when decoded
        $tooShort = base64_encode('short');

        $this->service->decrypt($tooShort);
    }

    public function testDecryptWithTamperedDataThrowsException(): void
    {
        $plaintext = 'sensitive data';
        $encrypted = $this->service->encrypt($plaintext);

        // Decode, tamper with the ciphertext portion, and re-encode
        $data = base64_decode($encrypted, true);
        $this->assertIsString($data);
        // Flip a bit in the ciphertext (after IV + tag = 28 bytes)
        if (\strlen($data) > 28) {
            $data[28] = \chr(\ord($data[28]) ^ 0xFF);
        }
        $tampered = base64_encode($data);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $this->service->decrypt($tampered);
    }

    public function testDecryptWithDifferentKeyFails(): void
    {
        $plaintext = 'secret message';
        $encrypted = $this->service->encrypt($plaintext);

        // Create a service with a different key
        $differentService = new EncryptionService('completely_different_secret_key');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');

        $differentService->decrypt($encrypted);
    }

    public function testEncryptDecryptWithLongData(): void
    {
        // Test with data longer than typical block sizes
        $plaintext = str_repeat('A', 10000);

        $encrypted = $this->service->encrypt($plaintext);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertSame($plaintext, $decrypted);
    }

    public function testEncryptDecryptWithBinaryData(): void
    {
        // Random binary data (like a TOTP secret might be)
        $plaintext = random_bytes(32);

        $encrypted = $this->service->encrypt($plaintext);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertSame($plaintext, $decrypted);
    }
}
