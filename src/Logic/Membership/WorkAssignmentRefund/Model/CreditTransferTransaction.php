<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Model;

readonly class CreditTransferTransaction
{
    public function __construct(
        public string $endToEndId,
        public int $amountCents,
        public string $creditorName,
        public string $creditorIban,
        public string $remittanceInformation,
    ) {
    }
}
