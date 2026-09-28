<?php

namespace App\Logic\Membership\DirectDebit\Model;

/**
 * Ein erfolgter Lastschrift-Export für einen Zahler („Lastschrift-Historie“). Grundlage dafür,
 * ob eine Erstlastschrift fällig ist, ob der Beitrag des Eintrittsjahres schon eingezogen wurde
 * und wann zuletzt abgebucht wurde (siehe `PayerDirectDebitPlanner`).
 *
 * Einträge mit `LEGACY_IMPORT_MESSAGE_ID` stammen nicht aus einem Export, sondern markieren die aus
 * Sage übernommenen Mandate als bereits genutzt (Migration `Version20260928180000`) — ohne echtes
 * Fälligkeitsdatum oder Betrag.
 */
readonly class DirectDebitRecord
{
    public const string LEGACY_IMPORT_MESSAGE_ID = 'SAGE-UEBERNAHME';

    public function __construct(
        public string $id,
        public string $payerMemberId,
        public string $mandateReference,
        public int $contributionYear,
        public SequenceType $sequenceType,
        public \DateTimeImmutable $collectionDate,
        public int $amountCents,
        public string $messageId,
        public \DateTimeImmutable $exportedAt,
    ) {
    }

    public function isLegacyImport(): bool
    {
        return $this->messageId === self::LEGACY_IMPORT_MESSAGE_ID;
    }
}
