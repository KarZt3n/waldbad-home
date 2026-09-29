<?php

namespace App\Logic\Membership\DirectDebit\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\DirectDebit\DirectDebitFileWriterInterface;
use App\Logic\Membership\DirectDebit\Dto\DirectDebitFileResponse;
use App\Logic\Membership\DirectDebit\Dto\ExportAllDirectDebitsRequest;
use App\Logic\Membership\DirectDebit\Model\DirectDebitBatch;
use App\Logic\Membership\DirectDebit\Model\DirectDebitTransaction;
use App\Logic\Membership\DirectDebit\Service\DirectDebitBookkeeper;
use App\Logic\Membership\DirectDebit\Service\PayerDirectDebitPlanner;

/**
 * Sammelexport: eine SEPA-Lastschriftdatei für alle exportierbaren Zahler der Lastschrift-Übersicht
 * (`GetDirectDebitOverviewQuery`). Jeder Zahler wird genau so abgebucht, wie es seine Einzelvorschau
 * vorschlägt — vorausgewählte Positionen, Fälligkeitsdatum, Lastschrifttyp und Verwendungszweck.
 * Da Fälligkeit (Zahltag 01./15., Eintrittsjahr) und Sequenz (FRST/RCUR) je Zahler abweichen können,
 * enthält die Datei je Kombination einen eigenen Zahlungsblock. Alle Zahler werden gemeinsam in der
 * Lastschrift-Historie festgehalten (siehe `DirectDebitBookkeeper::bookAll()`).
 */
readonly class ExportAllDirectDebitsUseCase
{
    public function __construct(
        private PayerDirectDebitPlanner $planner,
        private DirectDebitFileWriterInterface $writer,
        private ClockInterface $clock,
        private DirectDebitBookkeeper $bookkeeper,
    ) {
    }

    public function execute(ExportAllDirectDebitsRequest $request): DirectDebitFileResponse
    {
        $now = $this->clock->now();
        $exportable = [];
        foreach ($this->planner->planAll($now) as $draft) {
            if ($draft->defaultAmountCents() > 0 && $draft->bulkExportObstacles() === []) {
                $exportable[$draft->payer->id] = $draft;
            }
        }
        $requested = array_values(array_unique($request->payerIds));
        $available = array_keys($exportable);
        sort($requested);
        sort($available);
        if ($requested !== $available) {
            throw new BusinessRuleViolationException('Die Lastschrift-Übersicht ist nicht mehr aktuell. Bitte die Übersicht neu öffnen.');
        }
        if ($exportable === []) {
            throw new BusinessRuleViolationException('Es gibt keine exportierbaren Lastschriften.');
        }

        $groups = [];
        foreach ($exportable as $draft) {
            $payer = $draft->payer;
            if ($payer->mandateReference === null || $payer->iban === null) {
                throw new \LogicException('Zahler ohne Mandatsreferenz oder IBAN hätte blockiert werden müssen.');
            }
            $key = $draft->defaultCollectionDate->format('Y-m-d').'|'.$draft->defaultSequenceType->value;
            $groups[$key][] = [
                'draft' => $draft,
                'transaction' => new DirectDebitTransaction(
                    endToEndId: sprintf('%s-%s', $payer->memberNumber, $draft->defaultCollectionDate->format('Ymd')),
                    amountCents: $draft->defaultAmountCents(),
                    mandateReference: $payer->mandateReference,
                    mandateSignedOn: $draft->mandateSignedOn,
                    debtorName: $draft->debtorName,
                    debtorIban: $payer->iban,
                    remittanceInformation: trim($draft->defaultRemittanceInformation),
                ),
            ];
        }
        ksort($groups);

        $batches = [];
        $bookings = [];
        foreach (array_values($groups) as $index => $entries) {
            $first = $entries[0]['draft'];
            $batch = new DirectDebitBatch(
                messageId: sprintf('WB-%s-S%d', $now->format('YmdHis'), $index + 1),
                createdAt: $now,
                creditor: $first->creditor,
                collectionDate: $first->defaultCollectionDate,
                sequenceType: $first->defaultSequenceType,
                transactions: array_column($entries, 'transaction'),
            );
            $batches[] = $batch;
            foreach ($entries as ['draft' => $draft, 'transaction' => $transaction]) {
                $bookings[] = ['draft' => $draft, 'batch' => $batch, 'amountCents' => $transaction->amountCents];
            }
        }

        $content = $this->writer->write(...$batches);
        $this->bookkeeper->bookAll($bookings);

        return new DirectDebitFileResponse(
            fileName: sprintf('sepa-lastschrift-sammel-%s.xml', $now->format('Ymd-His')),
            content: $content,
        );
    }
}
