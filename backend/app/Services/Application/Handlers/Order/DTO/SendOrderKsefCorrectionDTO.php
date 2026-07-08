<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SendOrderKsefCorrectionDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $eventId,
    ) {
    }
}
