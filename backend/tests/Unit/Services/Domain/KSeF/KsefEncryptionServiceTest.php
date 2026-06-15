<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\KSeF;

use HiEvents\Exceptions\KsefEncryptionException;
use HiEvents\Services\Infrastructure\KSeF\KsefEncryptionService;
use Tests\TestCase;

class KsefEncryptionServiceTest extends TestCase
{
    private KsefEncryptionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new KsefEncryptionService();
    }

    public function test_generate_symmetric_key_returns_32_byte_key_and_16_byte_iv(): void
    {
        $result = $this->service->generateSymmetricKey();

        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('iv', $result);
        $this->assertEquals(32, strlen($result['key']));
        $this->assertEquals(16, strlen($result['iv']));
    }

    public function test_generate_symmetric_key_returns_different_values_each_call(): void
    {
        $first = $this->service->generateSymmetricKey();
        $second = $this->service->generateSymmetricKey();

        $this->assertNotEquals($first['key'], $second['key']);
        $this->assertNotEquals($first['iv'], $second['iv']);
    }

    public function test_encrypt_with_aes_returns_base64_string(): void
    {
        $symKey = $this->service->generateSymmetricKey();
        $result = $this->service->encryptWithAes('Hello, KSeF!', $symKey['key'], $symKey['iv']);

        $this->assertIsString($result);
        // Must be valid base64
        $this->assertNotFalse(base64_decode($result, true));
    }

    public function test_aes_encrypted_output_decrypts_back_to_original(): void
    {
        $symKey = $this->service->generateSymmetricKey();
        $plaintext = '<Faktura xmlns="http://crd.gov.pl/wzor/2023/06/29/12648/">test</Faktura>';

        $encrypted = $this->service->encryptWithAes($plaintext, $symKey['key'], $symKey['iv']);
        $decoded = base64_decode($encrypted);

        // IV is prepended (first 16 bytes), ciphertext follows
        $iv = substr($decoded, 0, 16);
        $ciphertext = substr($decoded, 16);

        $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $symKey['key'], OPENSSL_RAW_DATA, $iv);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_sha256_base64_returns_correct_hash(): void
    {
        $data = 'test data';
        $expected = base64_encode(hash('sha256', $data, true));

        $result = $this->service->sha256Base64($data);

        $this->assertEquals($expected, $result);
        $this->assertIsString($result);
        // Base64-encoded SHA-256 is always 44 chars
        $this->assertEquals(44, strlen($result));
    }

    public function test_sha256_base64_is_deterministic(): void
    {
        $data = 'same data';

        $this->assertEquals(
            $this->service->sha256Base64($data),
            $this->service->sha256Base64($data),
        );
    }

    public function test_encrypt_with_rsa_throws_exception_for_invalid_key(): void
    {
        $this->expectException(KsefEncryptionException::class);
        $this->expectExceptionMessageMatches('/RSA-OAEP SHA-256 encryption failed/');

        $this->service->encryptWithRsa('test data', 'not-a-real-pem-key');
    }

    public function test_encrypt_with_rsa_returns_base64_for_valid_key(): void
    {
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $keyDetails = openssl_pkey_get_details($key);
        $publicKeyPem = $keyDetails['key'];

        $encrypted = $this->service->encryptWithRsa('test payload', $publicKeyPem);

        $this->assertIsString($encrypted);
        $this->assertNotFalse(base64_decode($encrypted, true));
    }

    public function test_rsa_encrypted_output_decrypts_with_private_key(): void
    {
        $opensslKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $keyDetails = openssl_pkey_get_details($opensslKey);
        $publicKeyPem = $keyDetails['key'];

        $plaintext = 'my-ksef-token|1234567890000';
        $encryptedBase64 = $this->service->encryptWithRsa($plaintext, $publicKeyPem);

        openssl_pkey_export($opensslKey, $privateKeyPem);

        $privateKey = \phpseclib3\Crypt\PublicKeyLoader::load($privateKeyPem);
        $privateKey = $privateKey
            ->withPadding(\phpseclib3\Crypt\RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withMGFHash('sha256');

        $decrypted = $privateKey->decrypt(base64_decode($encryptedBase64));

        $this->assertEquals($plaintext, $decrypted);
    }
}
