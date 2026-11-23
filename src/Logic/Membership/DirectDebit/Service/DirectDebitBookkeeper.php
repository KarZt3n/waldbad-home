<?php

namespace App\Logic\Membership\DirectDebit\Service;

use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Membership\DirectDebit\DirectDebitBookingTransactionInterface;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitRecordManagerInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitBatch;
use App\Logic\Membership\DirectDebit\Model\DirectDebitPosition;
use App\Logic\Membership\DirectDebit\Model\DirectDebitPositionKind;
use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;
use App\Logic\Membership\DirectDebit\Model\PayerDirectDebitDraft;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;

/**
 * Hält einen Lastschrift-Export fest: ein Eintrag in der Lastschrift-Historie des Zahlers mit der
 * Art des Exports und — nur beim Beitragsexport und außer bei der Lastschrift für das Eintrittsjahr —
 * das Vorrücken seiner „Nächsten Buchung“ um ein Jahr, damit derselbe Zeitraum nicht versehentlich
 * ein zweites Mal exportiert wird. Exportierte Einmalgebühren werden als eingezogen vermerkt und in
 * keinem weiteren Export mehr angeboten. Alles geschieht gemeinsam oder gar nicht.
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

    /** @param list<DirectDebitPosition> $positions die exportierten Positionen */
    public function book(PayerDirectDebitDraft $draft, DirectDebitBatch $batch, array $positions): void
    {
        $this->bookAll([['draft' => $draft, 'batch' => $batch, 'amountCents' => $batch->totalCents(), 'positions' => $positions]]);
    }

    /**
     * Sammelexport: alle Zahler einer Datei gemeinsam oder gar nicht. `amountCents` ist der Betrag
     * des einzelnen Zahlers — ein Batch kann mehrere Zahler enthalten.
     *
     * @param list<array{draft: PayerDirectDebitDraft, batch: DirectDebitBatch, amountCents: int, positions: list<DirectDebitPosition>}> $bookings
     */
    public function bookAll(array $bookings): void
    {
        $records = [];
        $collectedChargeIds = [];
        $advancedPayers = [];
        foreach ($bookings as ['draft' => $draft, 'batch' => $batch, 'amountCents' => $amountCents, 'positions' => $positions]) {
            $payer = $draft->payer;
            $record = new DirectDebitRecord(
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
                kind: $draft->kind,
            );
            $records[] = $record;
            $chargeIds = array_values(array_filter(array_map(
                static fn (DirectDebitPosition $position): ?string => $position->chargeId,
                $positions,
            )));
            if ($chargeIds !== []) {
                $collectedChargeIds[$record->id] = $chargeIds;
            }
            if ($draft->kind === DirectDebitPositionKind::Contribution && !$draft->joiningYearDebit
                && $payer->nextBookingMonth !== null && $payer->nextBookingYear !== null
            ) {
                $advancedPayers[] = $payer->withNextBooking($payer->nextBookingMonth, $payer->nextBookingYear + 1);
            }
        }

        // Je ein gemeinsames Speichern statt zwei je Zahler, siehe `DirectDebitRecordManagerInterface::saveAll()`.
        $this->transaction->execute(function () use ($records, $collectedChargeIds, $advancedPayers): void {
            $this->records->saveAll($records, $collectedChargeIds);
            $this->members->saveAll($advancedPayers);
        });
    }
}
