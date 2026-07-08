<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Invoice;

use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\InvoiceStatus;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;

class CreateCorrectionInvoiceService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    public function create(InvoiceDomainObject $originalInvoice, OrderDomainObject $order): InvoiceDomainObject
    {
        $correctionCount = $this->invoiceRepository->countCorrectionsByOriginalInvoiceId($originalInvoice->getId());
        $correctionNumber = $originalInvoice->getInvoiceNumber() . '/K' . ($correctionCount + 1);

        $originalItems = is_array($originalInvoice->getItems()) ? $originalInvoice->getItems() : [];
        $negatedItems = array_map(function (array $item) {
            $item['price']                  = -abs((float)($item['price'] ?? 0));
            $item['total_before_additions'] = -abs((float)($item['total_before_additions'] ?? 0));
            $item['total_tax']              = -abs((float)($item['total_tax'] ?? 0));
            $item['total_gross']            = -abs((float)($item['total_gross'] ?? 0));
            return $item;
        }, $originalItems);

        return $this->invoiceRepository->create([
            'order_id'             => $originalInvoice->getOrderId(),
            'account_id'           => $originalInvoice->getAccountId(),
            'document_type'        => 'correction',
            'corrected_invoice_id' => $originalInvoice->getId(),
            'invoice_number'       => $correctionNumber,
            'items'                => $negatedItems,
            'taxes_and_fees'       => $originalInvoice->getTaxesAndFees(),
            'issue_date'           => now()->toDateString(),
            'status'               => InvoiceStatus::PAID->name,
            'total_amount'         => -abs((float)$originalInvoice->getTotalAmount()),
            'due_date'             => null,
        ]);
    }
}
