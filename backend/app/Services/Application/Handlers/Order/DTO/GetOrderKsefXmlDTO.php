<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Order\DTO;

class GetOrderKsefXmlDTO
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $eventId,
    )
    {
    }
}
