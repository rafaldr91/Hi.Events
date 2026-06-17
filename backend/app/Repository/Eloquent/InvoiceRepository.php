<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\Models\Invoice;
use HiEvents\Repository\Interfaces\InvoiceRepositoryInterface;

/**
 * @extends BaseRepository<InvoiceDomainObject>
 */
class InvoiceRepository extends BaseRepository implements InvoiceRepositoryInterface
{
    protected function getModel(): string
    {
        return Invoice::class;
    }

    public function getDomainObject(): string
    {
        return InvoiceDomainObject::class;
    }

    public function findLatestInvoiceForEvent(int $eventId): ?InvoiceDomainObject
    {
        $invoice =  $this->model
            ->whereHas('order', function ($query) use ($eventId) {
                $query->where('event_id', $eventId);
            })
            ->orderBy('id', 'desc')
            ->first();

        return $this->handleSingleResult($invoice);
    }

    public function findLatestInvoiceForOrder(int $orderId): ?InvoiceDomainObject
    {
        $invoice =  $this->model
            ->where('order_id', $orderId)
            ->orderBy('id', 'desc')
            ->first();

        return $this->handleSingleResult($invoice);
    }

    public function findLatestInvoiceForEventByDocumentType(int $eventId, string $documentType): ?InvoiceDomainObject
    {
        $invoice = $this->model
            ->whereHas('order', fn($query) => $query->where('event_id', $eventId))
            ->where('document_type', $documentType)
            ->orderBy('id', 'desc')
            ->first();

        return $this->handleSingleResult($invoice);
    }

    public function findMaxSequenceNumberForEvent(int $eventId, string $documentType, ?int $month, ?int $year): int
    {
        $query = $this->model
            ->whereHas('order', fn($q) => $q->where('event_id', $eventId))
            ->where('document_type', $documentType)
            ->whereNotNull('sequence_number');

        if ($year !== null) {
            $query->whereYear('issue_date', $year);
        }

        if ($month !== null) {
            $query->whereMonth('issue_date', $month);
        }

        return (int)($query->max('sequence_number') ?? 0);
    }

    public function findMaxSequenceNumberForAccount(int $accountId, string $documentType, ?int $month, ?int $year): int
    {
        $query = $this->model
            ->where('account_id', $accountId)
            ->where('document_type', $documentType)
            ->whereNotNull('sequence_number');

        if ($year !== null) {
            $query->whereYear('issue_date', $year);
        }

        if ($month !== null) {
            $query->whereMonth('issue_date', $month);
        }

        return (int)($query->max('sequence_number') ?? 0);
    }
}
