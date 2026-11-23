<?php

namespace App\Logic\Membership\DirectDebit\Manager;

use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;

interface DirectDebitRecordManagerInterface
{
    /** @return list<DirectDebitRecord> neueste zuerst */
    public function findByPayerMemberId(string $payerMemberId): array;

    /** @return list<DirectDebitRecord> neueste zuerst */
    public function findAll(): array;

    /** @return list<string> IDs aller bereits per Lastschrift eingezogenen Einmalgebühren */
    public function findCollectedChargeIds(): array;

    /**
     * Legt die Einträge eines Exports gemeinsam an und vermerkt die dabei eingezogenen
     * Einmalgebühren (Zeitpunkt = `exportedAt` des Eintrags). Ein gemeinsames Speichern statt
     * eines je Zahler: Beim Sammelexport sind alle Mitglieder geladen, und jedes einzelne Speichern
     * prüft sie alle erneut auf Änderungen (bei rund 400 Zahlern über 20 s).
     *
     * @param list<DirectDebitRecord>     $records
     * @param array<string, list<string>> $collectedChargeIdsByRecordId Einmalgebühr-IDs je Eintrag
     */
    public function saveAll(array $records, array $collectedChargeIdsByRecordId = []): void;

    /** Schreibt die Historie eines Zahlers auf ein anderes Mitglied um (Zahler-Übertragung, siehe `PayerTransfer`). */
    public function reassignPayer(string $fromPayerMemberId, string $toPayerMemberId): void;
}
