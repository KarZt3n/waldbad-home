<?php

namespace App\Logic\Membership\DirectDebit\Model;

/**
 * Ein erfolgter Lastschrift-Export für einen Zahler („Lastschrift-Historie“). Grundlage dafür,
 * ob eine Erstlastschrift fällig ist, ob der Beitrag des Eintrittsjahres schon eingezogen wurde
 * und wann zuletzt abgebucht wurde (siehe `PayerDirectDebitPlanner`).
 *
 * Einträge mit `LEGACY_IMPORT_MESSAGE_ID` stammen nicht aus einem Export, sondern markieren die aus
 * Sage übernommenen Mandate als bereits genutzt (Migration `Version20260928180000`) — ohne echtes
 * Fälligkeitsdatum oder Betrag. `$kind` ist die Art des Exports (siehe `covers()`).
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
        public ?DirectDebitPositionKind $kind = null,
    ) {
    }

    /**
     * Ob dieser Export die Positionen der Art `$kind` für sein Beitragsjahr bereits eingezogen hat.
     * Einträge ohne Art stammen aus der Zeit vor den getrennten Exporten (bzw. der Sage-Übernahme)
     * und enthielten Beitrag und Arbeitseinsatz-Pauschale gemeinsam. Einmalgebühren werden nicht über
     * das Beitragsjahr, sondern je Gebühr als eingezogen vermerkt.
     */
    public function covers(DirectDebitPositionKind $kind): bool
    {
        return $this->kind === null ? $kind !== DirectDebitPositionKind::OneTimeCharge : $this->kind === $kind;
    }

    public function isLegacyImport(): bool
    {
        return $this->messageId === self::LEGACY_IMPORT_MESSAGE_ID;
    }
}
