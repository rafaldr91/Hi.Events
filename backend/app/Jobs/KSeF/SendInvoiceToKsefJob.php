<?php

declare(strict_types=1);

namespace HiEvents\Jobs\KSeF;

use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\KsefStatus;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\KSeF\KsefInvoiceSenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;
use Throwable;

class SendInvoiceToKsefJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(private readonly int $invoiceId) {}

    public function backoff(): array
    {
        return [60, 300, 900, 3600, 3600];
    }

    public function handle(
        KsefInvoiceSenderService $sender,
        InvoiceRepositoryInterface $invoiceRepo,
        OrderRepositoryInterface $orderRepo,
        LoggerInterface $logger,
    ): void {
        $logger->info('SendInvoiceToKsefJob: starting', [
            'invoice_id' => $this->invoiceId,
            'attempt' => $this->attempts(),
        ]);

        $invoice = $invoiceRepo->findById($this->invoiceId);

        if ($invoice === null) {
            $logger->error('SendInvoiceToKsefJob: invoice not found', ['invoice_id' => $this->invoiceId]);
            return;
        }

        $order = $orderRepo
            ->loadRelation(OrderItemDomainObject::class)
            ->findById($invoice->getOrderId());

        $result = $sender->send($invoice, $order);

        if ($result->success) {
            $invoiceRepo->updateFromArray($this->invoiceId, [
                'ksef_status'           => KsefStatus::SENT->value,
                'ksef_number'           => $result->ksefNumber,
                'ksef_reference_number' => $result->referenceNumber,
                'ksef_error_message'    => null,
                'ksef_sent_at'          => now()->toDateTimeString(),
                'ksef_retry_count'      => $this->attempts(),
            ]);

            $logger->info('SendInvoiceToKsefJob: invoice sent successfully', [
                'invoice_id' => $this->invoiceId,
                'ksef_number' => $result->ksefNumber,
            ]);

            return;
        }

        $invoiceRepo->updateFromArray($this->invoiceId, [
            'ksef_status'      => KsefStatus::FAILED->value,
            'ksef_error_message' => $result->errorMessage,
            'ksef_retry_count' => $this->attempts(),
        ]);

        if ($result->isRetryable && $this->attempts() < $this->tries) {
            $backoffs = $this->backoff();
            $delay = $backoffs[$this->attempts() - 1] ?? end($backoffs);

            $logger->warning('SendInvoiceToKsefJob: retryable failure, releasing', [
                'invoice_id' => $this->invoiceId,
                'delay' => $delay,
                'attempt' => $this->attempts(),
            ]);

            $this->release($delay);
        }
    }

    public function failed(Throwable $exception): void
    {
        $logger = app(LoggerInterface::class);
        $invoiceRepo = app(InvoiceRepositoryInterface::class);

        $logger->error('SendInvoiceToKsefJob: permanently failed', [
            'invoice_id' => $this->invoiceId,
            'error' => $exception->getMessage(),
        ]);

        try {
            $invoiceRepo->updateFromArray($this->invoiceId, [
                'ksef_status'      => KsefStatus::FAILED->value,
                'ksef_error_message' => $exception->getMessage(),
                'ksef_retry_count' => $this->tries,
            ]);
        } catch (Throwable $e) {
            $logger->error('SendInvoiceToKsefJob: failed to update invoice after job failure', [
                'invoice_id' => $this->invoiceId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
