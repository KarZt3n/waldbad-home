<?php

namespace App\Logic\Membership\DirectDebit\Model;

readonly class DirectDebitTransaction
{
    /** Maximale Länge des unstrukturierten Verwendungszwecks laut SEPA-Regelwerk. */
    public const int MAX_REMITTANCE_LENGTH = 140;

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
