<?php

namespace App\Logic\Membership\DirectDebit\Service;

use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\DirectDebit\DirectDebitBookingTransactionInterface;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitRecordManagerInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitBatch;
use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;
use App\Logic\Membership\DirectDebit\Model\PayerDirectDebitDraft;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;

/**
 * Hält einen Lastschrift-Export fest: ein Eintrag in der Lastschrift-Historie des Zahlers und —
 * außer bei der Lastschrift für das Eintrittsjahr — das Vorrücken seiner „Nächsten Buchung“ um ein
 * Jahr, damit derselbe Zeitraum nicht versehentlich ein zweites Mal exportiert wird. Beides
 * geschieht gemeinsam oder gar nicht.
 */
readonly class DirectDebitBookkeeper
{
    public function __construct(
        private DirectDebitRecordManagerInterface $records,
        private MemberManagerInterface $members,
        private IdentifierGeneratorInterface $identifierGenerator,
        private DirectDebitBookingTransactionInterface $transaction,
    ) {
    }

    public function book(PayerDirectDebitDraft $draft, DirectDebitBatch $batch): void
    {
        $this->bookAll([['draft' => $draft, 'batch' => $batch, 'amountCents' => $batch->totalCents()]]);
    }

    /**
     * Sammelexport: alle Zahler einer Datei gemeinsam oder gar nicht. `amountCents` ist der Betrag
     * des einzelnen Zahlers — ein Batch kann mehrere Zahler enthalten.
     *
     * @param list<array{draft: PayerDirectDebitDraft, batch: DirectDebitBatch, amountCents: int}> $bookings
     */
    public function bookAll(array $bookings): void
    {
        $this->transaction->execute(function () use ($bookings): void {
            foreach ($bookings as ['draft' => $draft, 'batch' => $batch, 'amountCents' => $amountCents]) {
                $payer = $draft->payer;
                $this->records->save(new DirectDebitRecord(
                    id: $this->identifierGenerator->generate(),
                    payerMemberId: $payer->id,
                    mandateReference: $payer->mandateReference
                        ?? throw new \LogicException('Ein exportierter Zahler hat immer eine Mandatsreferenz.'),
                    contributionYear: $draft->contributionYear,
                    sequenceType: $batch->sequenceType,
                    collectionDate: $batch->collectionDate,
                    amountCents: $amountCents,
                    messageId: $batch->messageId,
                    exportedAt: $batch->createdAt,
                ));
                if (!$draft->joiningYearDebit && $payer->nextBookingMonth !== null && $payer->nextBookingYear !== null) {
                    $this->members->save($payer->withNextBooking($payer->nextBookingMonth, $payer->nextBookingYear + 1));
                }
            }
        });
    }
}
