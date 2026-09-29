<?php

namespace App\Logic\Membership\DirectDebit\Model;

use App\Logic\Membership\Member\Model\Member;

/**
 * Alles, was für die Lastschrift eines Zahlers ermittelt wurde, bevor die Redaktion Positionen,
 * Fälligkeitsdatum und Verwendungszweck festlegt (siehe `PayerDirectDebitPlanner`). `$blockers`
 * verhindern den Export, `$warnings` sind nur Hinweise.
 *
 * `$joiningYearDebit`: Lastschrift für das Eintrittsjahr eines im laufenden Jahr eingetretenen
 * Zahlers, für das noch nichts eingezogen wurde — sie wird sofort fällig und rückt die „Nächste
 * Buchung“ nicht vor. `$contributionYear` ist das Beitragsjahr, für das abgebucht wird.
 * `$alreadyCollected`: für `$contributionYear` gibt es bereits einen Eintrag in der
 * Lastschrift-Historie (Export über die App oder Sage-Übernahme).
 */
readonly class PayerDirectDebitDraft
{
    /**
     * @param list<DirectDebitPosition> $positions
     * @param list<DirectDebitObstacle>  $blockers
     * @param list<string>               $warnings
     */
    public function __construct(
        public Member $payer,
        public string $debtorName,
        public \DateTimeImmutable $mandateSignedOn,
        public DirectDebitCreditor $creditor,
        public array $positions,
        public array $blockers,
        public array $warnings,
        public \DateTimeImmutable $defaultCollectionDate,
        public string $defaultRemittanceInformation,
        public SequenceType $defaultSequenceType,
        public int $contributionYear,
        public bool $joiningYearDebit,
        public ?DirectDebitRecord $lastRecord,
        public bool $alreadyCollected = false,
    ) {
    }

    /** Summe der vorausgewählten Positionen — der Betrag, den ein Export mit den Vorgaben abbucht. */
    public function defaultAmountCents(): int
    {
        return array_sum(array_map(
            static fn (DirectDebitPosition $position): int => $position->selectedByDefault ? $position->amountCents : 0,
            $this->positions,
        ));
    }

    /** @return list<string> die in der Vorschau vorausgewählten Positionen (`DirectDebitPosition::$id`) */
    public function defaultPositionIds(): array
    {
        return array_values(array_map(
            static fn (DirectDebitPosition $position): string => $position->id,
            array_filter($this->positions, static fn (DirectDebitPosition $position): bool => $position->selectedByDefault),
        ));
    }

    /**
     * Gründe, warum dieser Zahler nicht ohne Einzelprüfung im Sammelexport mit den Vorgaben
     * abgebucht werden kann: die Hinderungsgründe (`$blockers`), ein bereits abgebuchtes
     * Beitragsjahr (würde doppelt eingezogen) sowie ein für die Bank ungültiger Verwendungszweck.
     *
     * @return list<DirectDebitObstacle>
     */
    public function bulkExportObstacles(): array
    {
        $obstacles = $this->blockers;
        if ($this->alreadyCollected) {
            $obstacles[] = new DirectDebitObstacle(DirectDebitObstacleKind::AlreadyCollected, sprintf('Für das Beitragsjahr %d wurde bereits eingezogen.', $this->contributionYear));
        }
        $remittance = trim($this->defaultRemittanceInformation);
        if ($remittance === '' || mb_strlen($remittance) > DirectDebitTransaction::MAX_REMITTANCE_LENGTH) {
            $obstacles[] = new DirectDebitObstacle(DirectDebitObstacleKind::InvalidRemittance, sprintf('Der Verwendungszweck muss zwischen 1 und %d Zeichen lang sein.', DirectDebitTransaction::MAX_REMITTANCE_LENGTH));
        }

        return $obstacles;
    }
}
