<?php

namespace HiEvents\DomainObjects\Status;

use HiEvents\DomainObjects\Enums\BaseEnum;

enum KsefStatus: string
{
    use BaseEnum;

    case PENDING        = 'PENDING';
    case SENT           = 'SENT';
    case FAILED         = 'FAILED';
    case NOT_APPLICABLE = 'NOT_APPLICABLE';
}
