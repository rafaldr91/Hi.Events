<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDTO;
use Illuminate\Support\Collection;

class CompleteOrderOrderDTO extends BaseDTO
{
    public function __construct(
        public readonly string      $first_name,
        public readonly string      $last_name,
        public readonly string      $email,
        #[CollectionOf(OrderQuestionsDTO::class)]
        public readonly ?Collection $questions,
        public readonly ?array      $address = [],
        public readonly bool        $opted_into_marketing = false,
        public readonly bool        $data_processing_accepted = false,
        public readonly string      $buyer_type = 'individual',
        public readonly ?string     $company_nip = null,
        public readonly ?string     $company_name = null,
    )
    {
    }
}
