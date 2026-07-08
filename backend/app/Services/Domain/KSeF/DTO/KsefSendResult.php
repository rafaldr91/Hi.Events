<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\KSeF\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class KsefSendResult extends BaseDataObject
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $ksefNumber = null,
        public readonly ?string $referenceNumber = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $isRetryable = false,
    ) {
    }
}
