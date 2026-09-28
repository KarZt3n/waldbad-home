<?php

namespace App\Logic\Membership\DirectDebit\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\DirectDebit\DirectDebitFileWriterInterface;
use App\Logic\Membership\DirectDebit\Dto\DirectDebitFileResponse;
use App\Logic\Membership\DirectDebit\Dto\ExportPayerDirectDebitRequest;
use App\Logic\Membership\DirectDebit\Model\DirectDebitBatch;
use App\Logic\Membership\DirectDebit\Model\DirectDebitPosition;
use App\Logic\Membership\DirectDebit\Model\DirectDebitTransaction;
use App\Logic\Membership\DirectDebit\Service\PayerDirectDebitPlanner;

/**
 * Erzeugt die SEPA-Lastschriftdatei für einen einzelnen Zahler aus den in der Vorschau
 * ausgewählten Positionen — als eine Buchung über deren Summe, so wie sie auch auf dem
 * Kontoauszug des Zahlers erscheint. Am Mitglied selbst wird dabei nichts verändert.
 */
readonly class ExportPayerDirectDebitUseCase
{
    private const int MAX_REMITTANCE_LENGTH = 140;

    public function __construct(
        private PayerDirectDebitPlanner $planner,
        private DirectDebitFileWriterInterface $writer,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ExportPayerDirectDebitRequest $request): DirectDebitFileResponse
    {
        $now = $this->clock->now();
        $draft = $this->planner->plan($request->memberId, $now);
        if ($draft->blockers !== []) {
            throw new BusinessRuleViolationException(implode(' ', $draft->blockers));
        }

        $positionsById = [];
        foreach ($draft->positions as $position) {
            $positionsById[$position->id] = $position;
        }
        $selected = [];
        foreach (array_unique($request->positionIds) as $positionId) {
            $selected[] = $positionsById[$positionId] ?? throw new BusinessRuleViolationException('Die Auswahl passt nicht mehr zu den aktuellen Beitragsdaten. Bitte die Vorschau neu öffnen.');
        }
        $totalCents = array_sum(array_map(static fn (DirectDebitPosition $position): int => $position->amountCents, $selected));
        if ($totalCents <= 0) {
            throw new BusinessRuleViolationException('Bitte mindestens eine Position mit einem Betrag auswählen.');
        }
        if ($request->collectionDate->format('Y-m-d') <= $now->format('Y-m-d')) {
            throw new BusinessRuleViolationException('Das Fälligkeitsdatum muss in der Zukunft liegen.');
        }
        $remittance = trim($request->remittanceInformation);
        if ($remittance === '' || mb_strlen($remittance) > self::MAX_REMITTANCE_LENGTH) {
            throw new BusinessRuleViolationException(sprintf('Der Verwendungszweck muss zwischen 1 und %d Zeichen lang sein.', self::MAX_REMITTANCE_LENGTH));
        }

        $payer = $draft->payer;
        // Durch die Hinderungsgründe oben bereits ausgeschlossen, hier nur für die Typisierung.
        if ($payer->mandateReference === null || $payer->iban === null) {
            throw new \LogicException('Zahler ohne Mandatsreferenz oder IBAN hätte blockiert werden müssen.');
        }
        $batch = new DirectDebitBatch(
            messageId: sprintf('WB-%s-%s', $now->format('YmdHis'), $payer->memberNumber),
            createdAt: $now,
            creditor: $draft->creditor,
            collectionDate: $request->collectionDate,
            sequenceType: $request->sequenceType,
            transactions: [new DirectDebitTransaction(
                endToEndId: sprintf('%s-%s', $payer->memberNumber, $request->collectionDate->format('Ymd')),
                amountCents: $totalCents,
                mandateReference: $payer->mandateReference,
                mandateSignedOn: $draft->mandateSignedOn,
                debtorName: $draft->debtorName,
                debtorIban: $payer->iban,
                remittanceInformation: $remittance,
            )],
        );

        return new DirectDebitFileResponse(
            fileName: sprintf('sepa-lastschrift-%s-%s.xml', $payer->memberNumber, $request->collectionDate->format('Ymd')),
            content: $this->writer->write($batch),
        );
    }
}
