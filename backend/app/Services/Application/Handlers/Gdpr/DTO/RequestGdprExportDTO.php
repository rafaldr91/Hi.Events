<?php

namespace HiEvents\Services\Application\Handlers\Gdpr\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class RequestGdprExportDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $email,
    ) {
    }
}
