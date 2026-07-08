<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\Status\KsefStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\KSeF\SendInvoiceToKsefJob;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\SendOrderKsefInvoiceDTO;

class SendOrderKsefInvoiceHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    /**
     * @throws ResourceNotFoundException
     * @throws \RuntimeException
     */
    public function handle(SendOrderKsefInvoiceDTO $command): void
    {
        $order = $this->orderRepository->findFirstWhere([
            'id' => $command->orderId,
            'event_id' => $command->eventId,
        ]);

        if ($order === null) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        $invoice = $this->invoiceRepository->findLatestInvoiceForOrder($order->getId());

        if ($invoice === null) {
            throw new ResourceNotFoundException(__('Invoice not found'));
        }

        if ($invoice->getDocumentType() !== 'invoice') {
            throw new \InvalidArgumentException(__('KSeF can only be used for B2B invoices with NIP'));
        }

        $this->invoiceRepository->updateFromArray($invoice->getId(), [
            'ksef_status'      => KsefStatus::PENDING->value,
            'ksef_error_message' => null,
            'ksef_retry_count' => 0,
        ]);

        SendInvoiceToKsefJob::dispatch($invoice->getId());
    }
}
