<?php

namespace HiEvents\Services\Application\Handlers\Account\Vat\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class UpsertAccountVatSettingDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $accountId,
        public readonly bool $vatRegistered,
        public readonly ?string $vatNumber = null,
        public readonly ?string $businessName = null,
        public readonly ?string $businessAddress = null,
        public readonly ?string $invoiceNumberFormat = null,
        public readonly ?string $invoicePrefix = null,
        public readonly ?string $invoiceSuffix = null,
        public readonly int $invoiceStartNumber = 1,
        public readonly ?string $confirmationPrefix = null,
        public readonly int $confirmationStartNumber = 1,
    ) {
    }
}
