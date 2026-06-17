<?php

namespace HiEvents\Services\Domain\Invoice;

use Carbon\Carbon;
use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\InvoiceStatus;
use HiEvents\DomainObjects\Status\KsefStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;

class InvoiceCreateService
{
    public function __construct(
        private readonly OrderRepositoryInterface              $orderRepository,
        private readonly InvoiceRepositoryInterface            $invoiceRepository,
        private readonly AccountVatSettingRepositoryInterface  $accountVatSettingRepository,
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

        $documentType = ($order->getBuyerType() === 'company' && !empty($order->getCompanyNip()))
            ? 'invoice'
            : 'confirmation';
        $issueDate = Carbon::now();
        $accountVatSetting = $this->accountVatSettingRepository->findByAccountId($event->getAccountId());
        $sequenceNumber = $this->getNextSequenceNumber($event->getId(), $event->getAccountId(), $eventSettings, $accountVatSetting, $documentType, $issueDate);
        $invoiceNumber = $this->formatDocumentNumber($sequenceNumber, $eventSettings, $accountVatSetting, $documentType, $issueDate);

        return $this->invoiceRepository->create([
            'order_id' => $orderId,
            'account_id' => $event->getAccountId(),
            'document_type' => $documentType,
            'sequence_number' => $sequenceNumber,
            'invoice_number' => $invoiceNumber,
            'items' => collect($order->getOrderItems())->map(fn(OrderItemDomainObject $item) => $item->toArray())->toArray(),
            'taxes_and_fees' => $order->getTaxesAndFeesRollup(),
            'issue_date' => $issueDate->toDateString(),
            'ksef_status' => $documentType === 'invoice' ? KsefStatus::PENDING->value : KsefStatus::NOT_APPLICABLE->value,
            'status' => $order->isOrderCompleted() ? InvoiceStatus::PAID->name : InvoiceStatus::UNPAID->name,
            'total_amount' => $order->getTotalGross(),
            'due_date' => $eventSettings->getInvoicePaymentTermsDays() !== null
                ? $issueDate->copy()->addDays($eventSettings->getInvoicePaymentTermsDays())
                : null
        ]);
    }

    private function getNextSequenceNumber(
        int $eventId,
        int $accountId,
        EventSettingDomainObject $eventSettings,
        ?AccountVatSettingDomainObject $accountVatSetting,
        string $documentType,
        Carbon $issueDate,
    ): int {
        $useAccountLevel = $documentType === 'invoice'
            ? $eventSettings->getInvoiceNumberFormat() === null
            : true;

        if ($useAccountLevel && $accountVatSetting !== null) {
            $format = $documentType === 'invoice'
                ? ($accountVatSetting->getInvoiceNumberFormat() ?? '{number}')
                : '{number}';

            $startNumber = $documentType === 'invoice'
                ? $accountVatSetting->getInvoiceStartNumber()
                : $accountVatSetting->getConfirmationStartNumber();

            $includesMonth = str_contains($format, '{month}');
            $includesYear = str_contains($format, '{year}');
            $month = $includesMonth ? (int)$issueDate->format('m') : null;
            $year = ($includesMonth || $includesYear) ? (int)$issueDate->format('Y') : null;

            $maxSeq = $this->invoiceRepository->findMaxSequenceNumberForAccount($accountId, $documentType, $month, $year);

            return max($maxSeq + 1, $startNumber);
        }

        $format = $documentType === 'invoice'
            ? ($eventSettings->getInvoiceNumberFormat() ?? '{number}')
            : '{number}';

        $startNumber = $documentType === 'invoice'
            ? ($eventSettings->getInvoiceStartNumber() ?? 1)
            : ($eventSettings->getConfirmationStartNumber() ?? 1);

        $includesMonth = str_contains($format, '{month}');
        $includesYear = str_contains($format, '{year}');
        $month = $includesMonth ? (int)$issueDate->format('m') : null;
        $year = ($includesMonth || $includesYear) ? (int)$issueDate->format('Y') : null;

        $maxSeq = $this->invoiceRepository->findMaxSequenceNumberForEvent($eventId, $documentType, $month, $year);

        return max($maxSeq + 1, $startNumber);
    }

    private function formatDocumentNumber(
        int $sequence,
        EventSettingDomainObject $eventSettings,
        ?AccountVatSettingDomainObject $accountVatSetting,
        string $documentType,
        Carbon $issueDate,
    ): string {
        $useAccountLevel = $documentType === 'invoice'
            ? $eventSettings->getInvoiceNumberFormat() === null
            : true;

        if ($useAccountLevel && $accountVatSetting !== null) {
            if ($documentType === 'invoice') {
                $format = $accountVatSetting->getInvoiceNumberFormat() ?? '{number}';
                $prefix = $accountVatSetting->getInvoicePrefix() ?? '';
                $suffix = $accountVatSetting->getInvoiceSuffix() ?? '';
            } else {
                $format = '{number}';
                $prefix = $accountVatSetting->getConfirmationPrefix() ?? '';
                $suffix = '';
            }
        } elseif ($documentType === 'invoice') {
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
