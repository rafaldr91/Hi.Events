<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\KSeF;

use HiEvents\Exceptions\KsefEncryptionException;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

class KsefEncryptionService
{
    /**
     * Encrypt data with RSA-OAEP SHA-256 / MGF1-SHA-256.
     * Used for: auth token encryption and AES symmetric key encryption.
     *
     * @throws KsefEncryptionException
     */
    public function encryptWithRsa(string $data, string $publicKeyPem): string
    {
        try {
            $key = PublicKeyLoader::load($publicKeyPem);
            $key = $key->withPadding(RSA::ENCRYPTION_OAEP)->withHash('sha256')->withMGFHash('sha256');
            $encrypted = $key->encrypt($data);
        } catch (\Throwable $e) {
            throw new KsefEncryptionException('RSA-OAEP SHA-256 encryption failed: ' . $e->getMessage());
        }

        return base64_encode($encrypted);
    }

    /**
     * Generate a random AES-256-CBC symmetric key and IV.
     *
     * @return array{key: string, iv: string} Binary key (32B) and IV (16B)
     * @throws KsefEncryptionException
     */
    public function generateSymmetricKey(): array
    {
        $key = openssl_random_pseudo_bytes(32, $strong);
        if (!$strong) {
            throw new KsefEncryptionException('Failed to generate cryptographically strong AES key');
        }

        $iv = openssl_random_pseudo_bytes(16, $strong);
        if (!$strong) {
            throw new KsefEncryptionException('Failed to generate cryptographically strong IV');
        }

        return ['key' => $key, 'iv' => $iv];
    }

    /**
     * Encrypt invoice XML with AES-256-CBC. IV is prepended to ciphertext.
     *
     * @param string $xml     Plain invoice XML
     * @param string $key     Binary AES key (32 bytes)
     * @param string $iv      Binary IV (16 bytes)
     * @return string         Base64-encoded (IV + ciphertext)
     * @throws KsefEncryptionException
     */
    public function encryptWithAes(string $xml, string $key, string $iv): string
    {
        $encrypted = openssl_encrypt($xml, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new KsefEncryptionException('AES-256-CBC encryption failed: ' . openssl_error_string());
        }

        return base64_encode($iv . $encrypted);
    }

    /**
     * Compute SHA-256 hash, returned as Base64-encoded string.
     */
    public function sha256Base64(string $data): string
    {
        return base64_encode(hash('sha256', $data, true));
    }
}
