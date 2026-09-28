<?php

namespace App\Logic\Membership\DirectDebit\Dto;

use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;

readonly class DirectDebitCreditorResponse
{
    public function __construct(
        public ?string $name,
        public ?string $creditorId,
        public ?string $iban,
        public ?string $bic,
        public bool $complete,
    ) {
    }

    public static function fromCreditor(DirectDebitCreditor $creditor): self
    {
        return new self($creditor->name, $creditor->creditorId, $creditor->iban, $creditor->bic, $creditor->isComplete());
    }
}
