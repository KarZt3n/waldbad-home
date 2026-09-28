<?php

namespace App\Logic\Membership\DirectDebit\Model;

readonly class DirectDebitTransaction
{
    public function __construct(
        public string $endToEndId,
        public int $amountCents,
        public string $mandateReference,
        public \DateTimeImmutable $mandateSignedOn,
        public string $debtorName,
        public string $debtorIban,
        public string $remittanceInformation,
    ) {
    }
}
