<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;

/**
 * Eine einzureichende SEPA-Überweisungsdatei vom Vereinskonto. Auftraggeber ist das Konto aus den
 * SEPA-Gläubigerdaten (`DirectDebitCreditor`) — für eine Überweisung genügen Name und IBAN, die
 * Gläubiger-ID wird nicht gebraucht.
 */
readonly class CreditTransferBatch
{
    /**
     * @param list<CreditTransferTransaction> $transactions
     */
    public function __construct(
        public string $messageId,
        public \DateTimeImmutable $createdAt,
        public DirectDebitCreditor $debtor,
        public \DateTimeImmutable $executionDate,
        public array $transactions,
    ) {
        if ($this->debtor->name === null || $this->debtor->iban === null) {
            throw new BusinessRuleViolationException('Name und IBAN des Vereinskontos sind nicht hinterlegt.');
        }
        if ($this->transactions === []) {
            throw new BusinessRuleViolationException('Die Überweisung enthält keine Buchung.');
        }
    }

    public function totalCents(): int
    {
        return array_sum(array_map(static fn (CreditTransferTransaction $transaction): int => $transaction->amountCents, $this->transactions));
    }
}
