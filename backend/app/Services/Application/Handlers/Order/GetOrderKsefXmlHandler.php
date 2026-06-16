<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Order;

use HiEvents\Exceptions\KsefValidationException;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\GetOrderKsefXmlDTO;
use HiEvents\Services\Domain\KSeF\KsefXmlGenerationService;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class GetOrderKsefXmlHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface   $orderRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly KsefXmlGenerationService   $ksefXmlGenerationService,
    )
    {
    }

    /**
     * @throws KsefValidationException
     * @throws ResourceNotFoundException
     */
    public function handle(GetOrderKsefXmlDTO $command): string
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
            throw new KsefValidationException(
                __('KSeF XML can only be generated for B2B invoices with NIP')
            );
        }

        return $this->ksefXmlGenerationService->generate($invoice, $order);
    }
}
