<?php

namespace HiEvents\Http\Request\Report;

use HiEvents\Http\Request\BaseRequest;

class GetReportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'start_date'       => 'date|before:end_date|required_with:end_date|nullable',
            'end_date'         => 'date|after:start_date|required_with:start_date|nullable',
            'payment_providers'   => 'array|nullable',
            'payment_providers.*' => 'string|in:STRIPE,OFFLINE,OTHER',
            'hide_empty_rows'     => 'boolean|nullable',
            'buyer_types'         => 'array|nullable',
            'buyer_types.*'       => 'string|in:company,individual',
        ];
    }
}
