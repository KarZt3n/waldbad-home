<?php

namespace App\Logic\Membership\DirectDebit\Dto;

use App\Logic\Membership\DirectDebit\Model\DirectDebitPosition;

readonly class DirectDebitPositionResponse
{
    public function __construct(
        public string $id,
        public string $memberId,
        public string $memberNumber,
        public string $memberName,
        public string $kind,
        public string $label,
        public int $amountCents,
        public ?int $annualAmountCents,
        public bool $selectedByDefault,
    ) {
    }

    public static function fromPosition(DirectDebitPosition $position): self
    {
        return new self(
            id: $position->id,
            memberId: $position->memberId,
            memberNumber: $position->memberNumber,
            memberName: $position->memberName,
            kind: $position->kind->value,
            label: $position->label,
            amountCents: $position->amountCents,
            annualAmountCents: $position->annualAmountCents,
            selectedByDefault: $position->selectedByDefault,
        );
    }
}
