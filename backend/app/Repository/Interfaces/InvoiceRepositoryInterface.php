<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\InvoiceDomainObject;

/**
 * @extends RepositoryInterface<InvoiceDomainObject>
 */
interface InvoiceRepositoryInterface extends RepositoryInterface
{
    public function findLatestInvoiceForEvent(int $eventId): ?InvoiceDomainObject;

    public function findLatestInvoiceForOrder(int $orderId): ?InvoiceDomainObject;

    public function findLatestInvoiceForEventByDocumentType(int $eventId, string $documentType): ?InvoiceDomainObject;

    public function findMaxSequenceNumberForEvent(int $eventId, string $documentType, ?int $month, ?int $year): int;

    public function findMaxSequenceNumberForAccount(int $accountId, string $documentType, ?int $month, ?int $year): int;
}
