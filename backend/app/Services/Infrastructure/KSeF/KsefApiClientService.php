<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\KSeF;

use HiEvents\Exceptions\KsefApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpClient;
use Psr\Log\LoggerInterface;

class KsefApiClientService
{
    private const TIMEOUT_SECONDS = 30;

    // KSeF exception codes that are retryable (server-side transient)
    private const RETRYABLE_STATUS_CODES = [429, 500, 502, 503, 504];

    public function __construct(
        private readonly HttpClient $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Step 1: Get challenge and timestamp for auth flow.
     *
     * @return array{challenge: string, timestamp: int}
     * @throws KsefApiException
     */
    public function challenge(): array
    {
        $response = $this->post('/auth/challenge', []);
        return [
            'challenge' => $response['challenge'],
            'timestamp' => $response['timestamp'],
        ];
    }

    /**
     * Step 2: Authenticate using encrypted KSeF token.
     *
     * @param string $encryptedToken  Base64-encoded RSA-OAEP encrypted "{token}|{timestamp}"
     * @param string $challenge       Challenge from step 1
     * @param string $nip             Seller NIP (10 digits)
     * @return array{authenticationToken: string, referenceNumber: string}
     * @throws KsefApiException
     */
    public function authenticateWithToken(string $encryptedToken, string $challenge, string $nip): array
    {
        $body = [
            'contextIdentifier' => [
                'type' => 'Nip',
                'value' => $nip,
            ],
            'encryptedToken' => $encryptedToken,
            'challenge' => $challenge,
        ];

        $response = $this->post('/auth/ksef-token', $body);

        $authToken = $response['authenticationToken'];

        return [
            'authenticationToken' => is_array($authToken) ? $authToken['token'] : $authToken,
            'referenceNumber' => $response['referenceNumber'],
        ];
    }

    /**
     * Step 3: Poll authentication status until complete.
     * Returns true when authenticated successfully.
     *
     * @throws KsefApiException
     */
    public function pollAuthStatus(string $referenceNumber, string $authenticationToken): bool
    {
        $response = $this->get("/auth/{$referenceNumber}", $authenticationToken);

        $statusBlock = $response['status'] ?? $response['authenticationStatus'] ?? [];
        $code = $statusBlock['code'] ?? $response['code'] ?? 0;
        $description = $statusBlock['description'] ?? '';

        // 200 = authenticated successfully
        if ($code === 200) {
            return true;
        }

        // 100/102 = still processing
        if (in_array($code, [100, 102], true)) {
            return false;
        }

        throw new KsefApiException(
            'KSeF authentication failed with code: ' . $code . ' — ' . $description,
            isRetryable: false,
        );
    }

    /**
     * Step 4: Redeem access token after successful authentication.
     *
     * @return array{accessToken: string, refreshToken: string}
     * @throws KsefApiException
     */
    public function redeemToken(string $authenticationToken): array
    {
        $response = $this->post('/auth/token/redeem', [], $authenticationToken);

        return [
            'accessToken' => $response['accessToken'],
            'refreshToken' => $response['refreshToken'],
        ];
    }

    /**
     * Refresh an expired access token using a refresh token.
     *
     * @return array{accessToken: string, refreshToken: string}
     * @throws KsefApiException
     */
    public function refreshToken(string $refreshToken): array
    {
        $response = $this->post('/auth/token/refresh', [], $refreshToken);

        return [
            'accessToken' => $response['accessToken'],
            'refreshToken' => $response['refreshToken'],
        ];
    }

    /**
     * Open an interactive (online) session for invoice submission.
     *
     * @param string $accessToken
     * @param string $encryptedSymmetricKey  Base64-encoded RSA-OAEP encrypted AES key
     * @param string $iv                     Base64-encoded AES IV (16 bytes)
     * @return string  Session reference number
     * @throws KsefApiException
     */
    public function openOnlineSession(string $accessToken, string $encryptedSymmetricKey, string $iv): string
    {
        $body = [
            'formCode' => [
                'systemCode' => 'FA (3)',
                'schemaVersion' => '1-0E',
                'value' => 'FA',
            ],
            'encryption' => [
                'encryptedSymmetricKey' => $encryptedSymmetricKey,
                'initializationVector' => $iv,
            ],
        ];

        $response = $this->post('/sessions/online', $body, $accessToken);

        return $response['referenceNumber'];
    }

    /**
     * Send a single encrypted invoice to an open session.
     *
     * @param string $accessToken
     * @param string $sessionRef                   Session reference number
     * @param string $originalXml                  Original (unencrypted) XML
     * @param string $encryptedInvoiceBase64        Base64-encoded encrypted invoice (IV prepended)
     * @param string $originalHashBase64            SHA-256 hash of original XML (Base64)
     * @param string $encryptedHashBase64           SHA-256 hash of encrypted invoice (Base64)
     * @return string  Invoice reference number
     * @throws KsefApiException
     */
    public function sendInvoice(
        string $accessToken,
        string $sessionRef,
        string $originalXml,
        string $encryptedInvoiceBase64,
        string $originalHashBase64,
        string $encryptedHashBase64,
    ): string {
        $encryptedBytes = base64_decode($encryptedInvoiceBase64);

        $body = [
            'invoiceHash' => [
                'hashSHA' => [
                    'algorithm' => 'SHA-256',
                    'encoding' => 'Base64',
                    'value' => $originalHashBase64,
                ],
                'fileSize' => strlen($originalXml),
            ],
            'invoicePayload' => [
                'type' => 'encrypted',
                'encryptedInvoiceHash' => [
                    'algorithm' => 'SHA-256',
                    'encoding' => 'Base64',
                    'value' => $encryptedHashBase64,
                ],
                'encryptedInvoiceSize' => strlen($encryptedBytes),
                'encryptedInvoiceBody' => $encryptedInvoiceBase64,
            ],
        ];

        $response = $this->post("/sessions/online/{$sessionRef}/invoices", $body, $accessToken);

        return $response['referenceNumber'];
    }

    /**
     * Close an open session (initiates UPO generation).
     *
     * @throws KsefApiException
     */
    public function closeSession(string $accessToken, string $sessionRef): void
    {
        $this->post("/sessions/online/{$sessionRef}/close", [], $accessToken);
    }

    /**
     * Poll session status. Returns session data including invoice results.
     *
     * @throws KsefApiException
     */
    public function getSessionStatus(string $accessToken, string $sessionRef): array
    {
        return $this->get("/sessions/{$sessionRef}", $accessToken);
    }

    /**
     * Get individual invoice status within a session.
     *
     * @throws KsefApiException
     */
    public function getInvoiceStatus(string $accessToken, string $sessionRef, string $invoiceRef): array
    {
        return $this->get("/sessions/{$sessionRef}/invoices/{$invoiceRef}", $accessToken);
    }

    private function post(string $path, array $body, ?string $bearerToken = null): array
    {
        $url = $this->baseUrl() . $path;

        $this->logger->debug('KSeF API POST', ['path' => $path]);

        try {
            $request = $this->httpClient
                ->timeout(self::TIMEOUT_SECONDS)
                ->accept('application/json')
                ->contentType('application/json');

            if ($bearerToken !== null) {
                $request = $request->withToken($bearerToken);
            }

            $response = $request->post($url, $body);
        } catch (ConnectionException $e) {
            throw new KsefApiException('KSeF API connection failed: ' . $e->getMessage(), isRetryable: true);
        }

        return $this->handleResponse($response, $path);
    }

    private function get(string $path, ?string $bearerToken = null): array
    {
        $url = $this->baseUrl() . $path;

        $this->logger->debug('KSeF API GET', ['path' => $path]);

        try {
            $request = $this->httpClient
                ->timeout(self::TIMEOUT_SECONDS)
                ->accept('application/json');

            if ($bearerToken !== null) {
                $request = $request->withToken($bearerToken);
            }

            $response = $request->get($url);
        } catch (ConnectionException $e) {
            throw new KsefApiException('KSeF API connection failed: ' . $e->getMessage(), isRetryable: true);
        }

        return $this->handleResponse($response, $path);
    }

    private function handleResponse($response, string $path): array
    {
        $status = $response->status();
        $data = $response->json() ?? [];

        if ($response->successful()) {
            return $data;
        }

        $isRetryable = in_array($status, self::RETRYABLE_STATUS_CODES, true);
        $exceptionDetail = $data['exception']['exceptionDetailList'][0] ?? [];
        $exceptionCode = $data['exceptionCode'] ?? $exceptionDetail['exceptionCode'] ?? null;
        $description = $data['exceptionDescription']
            ?? $exceptionDetail['exceptionDescription']
            ?? $data['detail']
            ?? $data['message']
            ?? "HTTP {$status}";

        $this->logger->error('KSeF API error', [
            'path' => $path,
            'status' => $status,
            'exception_code' => $exceptionCode,
            'description' => $description,
        ]);

        throw new KsefApiException(
            "KSeF API error on {$path}: {$description}",
            isRetryable: $isRetryable,
            statusCode: $status,
            exceptionCode: $exceptionCode,
        );
    }

    private function baseUrl(): string
    {
        $env = config('ksef.environment', 'test');
        return rtrim(config("ksef.base_urls.{$env}"), '/');
    }
}
