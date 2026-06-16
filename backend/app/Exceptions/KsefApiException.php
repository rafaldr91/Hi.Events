<?php

declare(strict_types=1);

namespace HiEvents\Exceptions;

class KsefApiException extends BaseException
{
    public function __construct(
        string $message,
        public readonly bool $isRetryable = false,
        public readonly int $statusCode = 0,
        public readonly ?int $exceptionCode = null,
    ) {
        parent::__construct($message);
    }
}
