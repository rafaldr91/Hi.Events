<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\DomainObjects\Status\KsefStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Jobs\KSeF\SendInvoiceToKsefJob;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\SendOrderKsefCorrectionDTO;
use HiEvents\Services\Domain\Invoice\CreateCorrectionInvoiceService;

class SendOrderKsefCorrectionHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly CreateCorrectionInvoiceService $createCorrectionInvoiceService,
    ) {
    }

    /**
     * @throws ResourceNotFoundException
     * @throws \InvalidArgumentException
     */
    public function handle(SendOrderKsefCorrectionDTO $command): void
    {
        $order = $this->orderRepository->findFirstWhere([
            'id'       => $command->orderId,
            'event_id' => $command->eventId,
        ]);

        if ($order === null) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        $originalInvoice = $this->invoiceRepository->findLatestByDocumentTypeForOrder(
            $command->orderId,
            'invoice',
        );

        if ($originalInvoice === null) {
            throw new ResourceNotFoundException(__('Invoice not found'));
        }

        if ($originalInvoice->getKsefStatus() !== KsefStatus::SENT->value) {
            throw new \InvalidArgumentException(
                __('KSeF correction can only be sent after the original invoice has been successfully sent to KSeF'),
            );
        }

        $correction = $this->createCorrectionInvoiceService->create($originalInvoice, $order);

        $this->invoiceRepository->updateFromArray($correction->getId(), [
            'ksef_status'        => KsefStatus::PENDING->value,
            'ksef_error_message' => null,
            'ksef_retry_count'   => 0,
        ]);

        SendInvoiceToKsefJob::dispatch($correction->getId());
    }
}
