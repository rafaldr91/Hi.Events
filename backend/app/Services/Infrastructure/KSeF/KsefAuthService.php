<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\KSeF;

use HiEvents\Exceptions\KsefAuthenticationException;
use HiEvents\Exceptions\KsefApiException;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;

class KsefAuthService
{
    // Cache access token with 1 minute buffer before actual 15-minute expiry
    private const ACCESS_TOKEN_TTL_SECONDS = 840; // 14 minutes
    private const REFRESH_TOKEN_TTL_SECONDS = 604800; // 7 days

    // Max poll attempts for auth status (each attempt waits 2s, so max 20s total)
    private const MAX_AUTH_POLL_ATTEMPTS = 10;
    private const AUTH_POLL_SLEEP_SECONDS = 2;

    public function __construct(
        private readonly KsefApiClientService $apiClient,
        private readonly KsefPublicKeyService $publicKeyService,
        private readonly KsefEncryptionService $encryptionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Get a valid access token for the given NIP. Uses cache if available, otherwise authenticates.
     *
     * @param string $nip Normalized 10-digit seller NIP (no prefix/spaces)
     * @throws KsefAuthenticationException
     */
    public function getValidAccessToken(string $nip): string
    {
        $accessTokenKey = "ksef_access_token_{$nip}";
        $refreshTokenKey = "ksef_refresh_token_{$nip}";

        // Try cached access token first
        $accessToken = Cache::get($accessTokenKey);
        if ($accessToken) {
            return $accessToken;
        }

        // Try to refresh using cached refresh token
        $refreshToken = Cache::get($refreshTokenKey);
        if ($refreshToken) {
            try {
                return $this->refreshAccessToken($refreshToken, $nip);
            } catch (KsefApiException $e) {
                $this->logger->warning('KSeF token refresh failed, re-authenticating', ['error' => $e->getMessage()]);
                Cache::forget($refreshTokenKey);
            }
        }

        // Full authentication flow
        return $this->authenticate($nip);
    }

    /**
     * Clear all cached tokens for the given NIP (forces re-authentication on next call).
     */
    public function clearTokens(string $nip): void
    {
        Cache::forget("ksef_access_token_{$nip}");
        Cache::forget("ksef_refresh_token_{$nip}");
    }

    /**
     * Full authentication flow: challenge → encrypt token → authenticate → poll → redeem.
     *
     * @throws KsefAuthenticationException
     */
    private function authenticate(string $nip): string
    {
        $this->logger->info('KSeF: starting full authentication flow', ['nip_masked' => $this->maskNip($nip)]);

        try {
            $publicKey = $this->publicKeyService->getTokenEncryptionKey();
            $challengeData = $this->apiClient->challenge();

            $challenge = $challengeData['challenge'];
            $timestamp = $challengeData['timestamp'];
            $authToken = config('ksef.auth_token');

            if (empty($authToken)) {
                throw new KsefAuthenticationException('KSEF_AUTH_TOKEN is not configured');
            }

            // Format: "{token}|{timestampMs}"
            $tokenPayload = $authToken . '|' . $timestamp;
            $encryptedToken = $this->encryptionService->encryptWithRsa($tokenPayload, $publicKey);

            $authResponse = $this->apiClient->authenticateWithToken($encryptedToken, $challenge, $nip);
            $authenticationToken = $authResponse['authenticationToken'];
            $referenceNumber = $authResponse['referenceNumber'];

            // Poll for completion
            for ($attempt = 0; $attempt < self::MAX_AUTH_POLL_ATTEMPTS; $attempt++) {
                $authenticated = $this->apiClient->pollAuthStatus($referenceNumber, $authenticationToken);
                if ($authenticated) {
                    break;
                }
                sleep(self::AUTH_POLL_SLEEP_SECONDS);
            }

            if (!($authenticated ?? false)) {
                throw new KsefAuthenticationException('KSeF authentication timed out after polling');
            }

            $tokens = $this->apiClient->redeemToken($authenticationToken);
            $this->cacheTokens($tokens['accessToken'], $tokens['refreshToken'], $nip);

            $this->logger->info('KSeF: authentication successful', ['nip_masked' => $this->maskNip($nip)]);

            return $tokens['accessToken'];
        } catch (KsefAuthenticationException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new KsefAuthenticationException('KSeF authentication failed: ' . $e->getMessage());
        }
    }

    /**
     * @throws KsefApiException
     */
    private function refreshAccessToken(string $refreshToken, string $nip): string
    {
        $this->logger->info('KSeF: refreshing access token', ['nip_masked' => $this->maskNip($nip)]);

        $tokens = $this->apiClient->refreshToken($refreshToken);
        $this->cacheTokens($tokens['accessToken'], $tokens['refreshToken'], $nip);

        return $tokens['accessToken'];
    }

    private function cacheTokens(string $accessToken, string $refreshToken, string $nip): void
    {
        Cache::put("ksef_access_token_{$nip}", $accessToken, self::ACCESS_TOKEN_TTL_SECONDS);
        Cache::put("ksef_refresh_token_{$nip}", $refreshToken, self::REFRESH_TOKEN_TTL_SECONDS);
    }

    private function maskNip(string $nip): string
    {
        if (strlen($nip) <= 4) {
            return $nip;
        }
        return substr($nip, 0, 3) . str_repeat('*', strlen($nip) - 5) . substr($nip, -2);
    }
}
