<?php

namespace App\Logic\Membership\DirectDebit\Dto;

readonly class UpdateDirectDebitCreditorRequest
{
    public function __construct(
        public ?string $name,
        public ?string $creditorId,
        public ?string $iban,
        public ?string $bic,
    ) {
    }
}
