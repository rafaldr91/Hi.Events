<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\KSeF;

use HiEvents\Exceptions\KsefApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpClient;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;

class KsefPublicKeyService
{
    private const CACHE_KEY_TOKEN = 'ksef_public_key_token';
    private const CACHE_KEY_SYMMETRIC = 'ksef_public_key_symmetric';
    private const CACHE_TTL_SECONDS = 86400; // 24h
    private const TIMEOUT_SECONDS = 15;

    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Get the RSA public key for auth token encryption (usage: KsefTokenEncryption).
     *
     * @throws KsefApiException
     */
    public function getTokenEncryptionKey(): string
    {
        return Cache::remember(self::CACHE_KEY_TOKEN, self::CACHE_TTL_SECONDS, function () {
            return $this->fetchKeyByUsage('token');
        });
    }

    /**
     * Get the RSA public key for AES session key encryption (usage: SymmetricKeyEncryption).
     *
     * @throws KsefApiException
     */
    public function getSymmetricEncryptionKey(): string
    {
        return Cache::remember(self::CACHE_KEY_SYMMETRIC, self::CACHE_TTL_SECONDS, function () {
            return $this->fetchKeyByUsage('symmetric');
        });
    }

    /** @deprecated Use getTokenEncryptionKey() or getSymmetricEncryptionKey() */
    public function getPublicKey(): string
    {
        return $this->getTokenEncryptionKey();
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY_TOKEN);
        Cache::forget(self::CACHE_KEY_SYMMETRIC);
    }

    /**
     * @throws KsefApiException
     */
    private function fetchKeyByUsage(string $type): string
    {
        $url = $this->baseUrl() . '/security/public-key-certificates';
        $this->logger->info('Fetching KSeF public key', ['url' => $url, 'type' => $type]);

        try {
            $response = $this->httpClient
                ->timeout(self::TIMEOUT_SECONDS)
                ->get($url);
        } catch (ConnectionException $e) {
            throw new KsefApiException('KSeF public key fetch failed: ' . $e->getMessage(), isRetryable: true);
        }

        if (!$response->successful()) {
            throw new KsefApiException(
                'KSeF public key endpoint returned HTTP ' . $response->status(),
                isRetryable: $response->status() >= 500,
                statusCode: $response->status(),
            );
        }

        $data = $response->json();
        $certificates = $data['publicKeyList'] ?? $data ?? [];

        $keyword = $type === 'symmetric' ? 'symmetric' : 'token';
        $fallback = null;

        foreach ($certificates as $cert) {
            $usageRaw = $cert['certificateUsage'] ?? $cert['usage'] ?? '';
            $usageStr = strtolower(is_array($usageRaw) ? implode(',', $usageRaw) : (string)$usageRaw);
            $keyValue = $cert['publicKey'] ?? $cert['key'] ?? $cert['value'] ?? $cert['certificate'] ?? null;

            if (!$keyValue) {
                continue;
            }

            if (str_contains($usageStr, $keyword)) {
                $this->logger->info('KSeF public key fetched', ['type' => $type, 'usage' => $usageRaw]);
                return $this->normalizePem($keyValue);
            }

            if ($fallback === null) {
                $fallback = $keyValue;
            }
        }

        if ($fallback !== null) {
            $this->logger->warning('KSeF: using first certificate (no matching usage found)', ['type' => $type]);
            return $this->normalizePem($fallback);
        }

        $this->logger->error('KSeF public key not found in response', ['type' => $type, 'response' => $data]);
        throw new KsefApiException('KSeF public key not found in certificates response', isRetryable: false);
    }

    private function normalizePem(string $key): string
    {
        $key = trim($key);

        if (str_starts_with($key, '-----BEGIN')) {
            return $key;
        }

        $wrapped = chunk_split($key, 64, "\n");
        return "-----BEGIN CERTIFICATE-----\n{$wrapped}-----END CERTIFICATE-----\n";
    }

    private function baseUrl(): string
    {
        $env = config('ksef.environment', 'test');
        return rtrim(config("ksef.base_urls.{$env}"), '/');
    }
}
