<?php

namespace HiEvents\Services\Domain\Invoice;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\InvoiceStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;

class InvoiceCreateService
{
    public function __construct(
        private readonly OrderRepositoryInterface   $orderRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    )
    {
    }

    /**
     * @throws ResourceConflictException
     */
    public function createInvoiceForOrder(int $orderId): InvoiceDomainObject
    {
        $existingInvoice = $this->invoiceRepository->findFirstWhere([
            'order_id' => $orderId,
        ]);

        if ($existingInvoice) {
            throw new ResourceConflictException(__('Invoice already exists'));
        }

        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->loadRelation(new Relationship(EventDomainObject::class, nested: [
                new Relationship(EventSettingDomainObject::class, name: 'event_settings'),
            ], name: 'event'))
            ->findById($orderId);

        /** @var EventSettingDomainObject $eventSettings */
        $eventSettings = $order->getEvent()->getEventSettings();
        /** @var EventDomainObject $event */
        $event = $order->getEvent();

        $documentType = $order->getBuyerType() === 'individual' ? 'confirmation' : 'invoice';
        $issueDate = Carbon::now();
        $sequenceNumber = $this->getNextSequenceNumber($event->getId(), $eventSettings, $documentType, $issueDate);
        $invoiceNumber = $this->formatDocumentNumber($sequenceNumber, $eventSettings, $documentType, $issueDate);

        return $this->invoiceRepository->create([
            'order_id' => $orderId,
            'account_id' => $event->getAccountId(),
            'document_type' => $documentType,
            'sequence_number' => $sequenceNumber,
            'invoice_number' => $invoiceNumber,
            'items' => collect($order->getOrderItems())->map(fn(OrderItemDomainObject $item) => $item->toArray())->toArray(),
            'taxes_and_fees' => $order->getTaxesAndFeesRollup(),
            'issue_date' => $issueDate->toDateString(),
            'status' => $order->isOrderCompleted() ? InvoiceStatus::PAID->name : InvoiceStatus::UNPAID->name,
            'total_amount' => $order->getTotalGross(),
            'due_date' => $eventSettings->getInvoicePaymentTermsDays() !== null
                ? $issueDate->copy()->addDays($eventSettings->getInvoicePaymentTermsDays())
                : null
        ]);
    }

    private function getNextSequenceNumber(int $eventId, EventSettingDomainObject $eventSettings, string $documentType, Carbon $issueDate): int
    {
        $format = $documentType === 'invoice'
            ? ($eventSettings->getInvoiceNumberFormat() ?? '{number}')
            : '{number}';

        $startNumber = $documentType === 'invoice'
            ? ($eventSettings->getInvoiceStartNumber() ?? 1)
            : ($eventSettings->getConfirmationStartNumber() ?? 1);

        $includesMonth = str_contains($format, '{month}');
        [$month, $year] = $includesMonth
            ? [(int)$issueDate->format('m'), (int)$issueDate->format('Y')]
            : [null, null];

        $maxSeq = $this->invoiceRepository->findMaxSequenceNumberForEvent($eventId, $documentType, $month, $year);

        return max($maxSeq + 1, $startNumber);
    }

    private function formatDocumentNumber(int $sequence, EventSettingDomainObject $eventSettings, string $documentType, Carbon $issueDate): string
    {
        if ($documentType === 'invoice') {
            $format = $eventSettings->getInvoiceNumberFormat() ?? '{number}';
            $prefix = $eventSettings->getInvoicePrefix() ?? '';
            $suffix = $eventSettings->getInvoiceSuffix() ?? '';
        } else {
            $format = '{number}';
            $prefix = $eventSettings->getConfirmationPrefix() ?? '';
            $suffix = '';
        }

        $core = str_replace(
            ['{number}', '{month}', '{year}'],
            [$sequence, $issueDate->format('m'), $issueDate->format('Y')],
            $format,
        );

        return $prefix . $core . $suffix;
    }
}
