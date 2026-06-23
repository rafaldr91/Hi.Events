<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\KSeF;

use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Exceptions\KsefValidationException;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;
use HiEvents\Services\Domain\KSeF\DTO\KsefSendResult;
use N1ebieski\KSEFClient\ClientBuilder;
use N1ebieski\KSEFClient\Requests\Sessions\Online\Send\SendXmlRequest;
use N1ebieski\KSEFClient\ValueObjects\Requests\ReferenceNumber;
use N1ebieski\KSEFClient\Exceptions\HttpClient\ClientException;
use N1ebieski\KSEFClient\Factories\EncryptionKeyFactory;
use N1ebieski\KSEFClient\Support\Utility;
use N1ebieski\KSEFClient\ValueObjects\Mode;
use Psr\Log\LoggerInterface;
use Throwable;

class KsefInvoiceSenderService
{
    public function __construct(
        private readonly KsefXmlGenerationService $xmlGenerationService,
        private readonly AccountVatSettingRepositoryInterface $vatSettingRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendCorrection(
        InvoiceDomainObject $correction,
        InvoiceDomainObject $originalInvoice,
        OrderDomainObject $order,
    ): KsefSendResult {
        $invoiceId = $correction->getId();
        $this->logger->info('KSeF: starting correction send', ['invoice_id' => $invoiceId]);

        try {
            $sellerNip = $this->getSellerNip($correction->getAccountId());
            $xml = $this->xmlGenerationService->generateCorrection($correction, $originalInvoice, $order);
        } catch (KsefValidationException $e) {
            return new KsefSendResult(success: false, errorMessage: $e->getMessage(), isRetryable: false);
        }

        try {
            $client = $this->buildClient($sellerNip);

            $openResponse = $client->sessions()->online()->open([
                'formCode' => 'FA (3)',
            ])->object();

            $sessionRef = $openResponse->referenceNumber;
            $this->logger->info('KSeF: correction session opened', ['invoice_id' => $invoiceId, 'session_ref' => $sessionRef]);

            $sendResponse = $client->sessions()->online()->send(
                new SendXmlRequest(
                    referenceNumber: ReferenceNumber::from($sessionRef),
                    faktura: $xml,
                )
            )->object();

            $invoiceRef = $sendResponse->referenceNumber;
            $this->logger->info('KSeF: correction sent', ['invoice_id' => $invoiceId, 'invoice_ref' => $invoiceRef]);

            $client->sessions()->online()->close(['referenceNumber' => $sessionRef]);

            $ksefNumber = $this->pollForKsefNumber($client, $sessionRef, $invoiceRef, $invoiceId);

            $this->logger->info('KSeF: correction accepted', ['invoice_id' => $invoiceId, 'ksef_number' => $ksefNumber]);

            return new KsefSendResult(success: true, ksefNumber: $ksefNumber, referenceNumber: $invoiceRef);
        } catch (Throwable $e) {
            $this->logger->error('KSeF: correction send failed', ['invoice_id' => $invoiceId, 'error' => $e->getMessage()]);

            $isRetryable = !($e instanceof ClientException && $e->getCode() < 500);

            return new KsefSendResult(success: false, errorMessage: $e->getMessage(), isRetryable: $isRetryable);
        }
    }

    public function send(InvoiceDomainObject $invoice, OrderDomainObject $order): KsefSendResult
    {
        $invoiceId = $invoice->getId();
        $this->logger->info('KSeF: starting invoice send', ['invoice_id' => $invoiceId]);

        try {
            $sellerNip = $this->getSellerNip($invoice->getAccountId());
            $xml = $this->xmlGenerationService->generate($invoice, $order);
        } catch (KsefValidationException $e) {
            return new KsefSendResult(success: false, errorMessage: $e->getMessage(), isRetryable: false);
        }

        try {
            $client = $this->buildClient($sellerNip);

            $openResponse = $client->sessions()->online()->open([
                'formCode' => 'FA (3)',
            ])->object();

            $sessionRef = $openResponse->referenceNumber;
            $this->logger->info('KSeF: session opened', ['invoice_id' => $invoiceId, 'session_ref' => $sessionRef]);

            $sendResponse = $client->sessions()->online()->send(
                new SendXmlRequest(
                    referenceNumber: ReferenceNumber::from($sessionRef),
                    faktura: $xml,
                )
            )->object();

            $invoiceRef = $sendResponse->referenceNumber;
            $this->logger->info('KSeF: invoice sent', ['invoice_id' => $invoiceId, 'invoice_ref' => $invoiceRef]);

            $client->sessions()->online()->close(['referenceNumber' => $sessionRef]);

            $ksefNumber = $this->pollForKsefNumber($client, $sessionRef, $invoiceRef, $invoiceId);

            $this->logger->info('KSeF: invoice accepted', ['invoice_id' => $invoiceId, 'ksef_number' => $ksefNumber]);

            return new KsefSendResult(success: true, ksefNumber: $ksefNumber, referenceNumber: $invoiceRef);
        } catch (Throwable $e) {
            $this->logger->error('KSeF: send failed', ['invoice_id' => $invoiceId, 'error' => $e->getMessage()]);

            $isRetryable = !($e instanceof ClientException && $e->getCode() < 500);

            return new KsefSendResult(success: false, errorMessage: $e->getMessage(), isRetryable: $isRetryable);
        }
    }

    private function buildClient(string $nip): \N1ebieski\KSEFClient\Resources\ClientResource
    {
        $mode = config('ksef.environment') === 'production' ? Mode::Production : Mode::Test;

        return (new ClientBuilder())
            ->withMode($mode)
            ->withIdentifier($nip)
            ->withKsefToken(config('ksef.auth_token'))
            ->withEncryptionKey(EncryptionKeyFactory::makeRandom())
            ->withRetryTiming(3, 60)
            ->build();
    }

    private function pollForKsefNumber(
        \N1ebieski\KSEFClient\Resources\ClientResource $client,
        string $sessionRef,
        string $invoiceRef,
        int $invoiceId,
    ): ?string {
        try {
            $statusResponse = Utility::retry(function () use ($client, $sessionRef, $invoiceRef) {
                $status = $client->sessions()->invoices()->status([
                    'referenceNumber'        => $sessionRef,
                    'invoiceReferenceNumber' => $invoiceRef,
                ])->object();

                $code = $status->status->code ?? 0;

                if ($code === 200) {
                    return $status;
                }

                if ($code >= 400) {
                    throw new \RuntimeException($status->status->description ?? 'Invoice rejected', $code);
                }

                return null;
            }, backoff: 3, retryUntil: 60);

            return $statusResponse->ksefNumber ?? null;
        } catch (Throwable $e) {
            $this->logger->warning('KSeF: could not confirm ksefNumber', [
                'invoice_id' => $invoiceId,
                'error'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function getSellerNip(int $accountId): string
    {
        $vatSetting = $this->vatSettingRepository->findByAccountId($accountId);

        if ($vatSetting === null || empty($vatSetting->getVatNumber())) {
            throw new KsefValidationException(__('Seller NIP is not configured. Please set it in Account → Tax & Fees settings.'));
        }

        $nip = preg_replace('/^PL/i', '', $vatSetting->getVatNumber());
        $nip = preg_replace('/[\s\-.]/', '', $nip);

        if (!preg_match('/^\d{10}$/', $nip)) {
            throw new KsefValidationException(__('Seller NIP must be exactly 10 digits'));
        }

        return $nip;
    }
}
