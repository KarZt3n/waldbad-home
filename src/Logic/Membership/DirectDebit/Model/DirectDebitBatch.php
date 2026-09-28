<?php

namespace App\Logic\Membership\DirectDebit\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/** Eine einzureichende Lastschriftdatei: ein Gläubiger, ein Fälligkeitsdatum, eine Sequenz. */
readonly class DirectDebitBatch
{
    /**
     * @param list<DirectDebitTransaction> $transactions
     */
    public function __construct(
        public string $messageId,
        public \DateTimeImmutable $createdAt,
        public DirectDebitCreditor $creditor,
        public \DateTimeImmutable $collectionDate,
        public SequenceType $sequenceType,
        public array $transactions,
    ) {
        if (!$this->creditor->isComplete()) {
            throw new BusinessRuleViolationException('Die SEPA-Gläubigerdaten sind nicht vollständig.');
        }
        if ($this->transactions === []) {
            throw new BusinessRuleViolationException('Die Lastschrift enthält keine Buchung.');
        }
    }

    public function totalCents(): int
    {
        return array_sum(array_map(static fn (DirectDebitTransaction $transaction): int => $transaction->amountCents, $this->transactions));
    }
}
