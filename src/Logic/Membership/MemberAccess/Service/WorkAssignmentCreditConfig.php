<?php

namespace App\Logic\Membership\MemberAccess\Service;

/**
 * Liest die in `config/packages/work_assignment.yaml` hinterlegten, projektweit gemeinsamen
 * Einstellungen für die Arbeitseinsatz-Gutschrift in „Meine Mitgliedschaft" (siehe
 * `WorkAssignmentCreditCalculator`): den betrachteten Zeitraum sowie — je Stichtag — die Anzahl der
 * je Arbeitseinsatz zur vollen Gutschrift nötigen Stunden. Bewusst eine reine YAML-Konfiguration
 * statt eines weiteren Admin-Formulars, da sich beides nur selten (im Rhythmus der Beitragsordnung)
 * ändert; der Betrag des Arbeitseinsatz-Zuschlags selbst bleibt weiterhin der admin-editierbare
 * Beitragssatz „Arbeitseinsatz-Zuschlag" (siehe `ContributionRate`, inkl. dortiger „geplante
 * Änderung ab Datum").
 */
readonly class WorkAssignmentCreditConfig
{
    /** @var list<array{validFrom: \DateTimeImmutable, requiredHours: int}> Nach validFrom aufsteigend sortiert. */
    private array $tiers;
    private \DateTimeImmutable $periodFrom;
    private \DateTimeImmutable $periodTo;

    /**
     * Die Parametertypen sind bewusst weit gefasst statt z. B. array{from: string, to: string}: die
     * tatsächliche Form kommt aus YAML (config/packages/work_assignment.yaml, gebunden in
     * services.yaml) und wird unten zur Laufzeit geprüft, nicht nur statisch angenommen.
     *
     * @param array<string, mixed> $workAssignmentSelfServicePeriod erwartet Schlüssel "from"/"to"
     * @param list<array<string, mixed>> $workAssignmentRequiredHoursTiers je Eintrag "valid_from"/"required_hours"
     */
    public function __construct(array $workAssignmentSelfServicePeriod, array $workAssignmentRequiredHoursTiers)
    {
        $from = $workAssignmentSelfServicePeriod['from'] ?? null;
        $to = $workAssignmentSelfServicePeriod['to'] ?? null;
        if (!is_string($from) || !is_string($to)) {
            throw new \InvalidArgumentException('work_assignment_self_service_period benötigt "from" und "to" als Datumsangaben.');
        }
        $this->periodFrom = new \DateTimeImmutable($from);
        $this->periodTo = new \DateTimeImmutable($to);
        if ($this->periodTo <= $this->periodFrom) {
            throw new \InvalidArgumentException('work_assignment_self_service_period: "to" muss nach "from" liegen.');
        }

        if ($workAssignmentRequiredHoursTiers === []) {
            throw new \InvalidArgumentException('work_assignment_required_hours_tiers darf nicht leer sein.');
        }
        $tiers = array_map($this->parseTier(...), $workAssignmentRequiredHoursTiers);
        usort($tiers, static fn (array $left, array $right): int => $left['validFrom'] <=> $right['validFrom']);
        $this->tiers = $tiers;
    }

    /**
     * @param array<string, mixed> $tier
     * @return array{validFrom: \DateTimeImmutable, requiredHours: int}
     */
    private function parseTier(array $tier): array
    {
        $validFrom = $tier['valid_from'] ?? null;
        $requiredHours = $tier['required_hours'] ?? null;
        if (!is_string($validFrom) || !is_int($requiredHours)) {
            throw new \InvalidArgumentException('Jeder Eintrag in work_assignment_required_hours_tiers benötigt "valid_from" (Datum) und "required_hours" (Ganzzahl).');
        }

        return [
            'validFrom' => new \DateTimeImmutable($validFrom),
            'requiredHours' => $requiredHours,
        ];
    }

    public function periodFrom(): \DateTimeImmutable
    {
        return $this->periodFrom;
    }

    public function periodTo(): \DateTimeImmutable
    {
        return $this->periodTo;
    }

    /**
     * Anzahl der je Arbeitseinsatz zur vollen Gutschrift nötigen Stunden — der zum übergebenen
     * Stichtag jüngste, bereits erreichte Tarif. Liegt der Stichtag vor jedem konfigurierten
     * `valid_from`, gilt der älteste (erste) Tarif.
     */
    public function requiredHoursAt(\DateTimeImmutable $at): int
    {
        $applicable = $this->tiers[0]['requiredHours'];
        foreach ($this->tiers as $tier) {
            if ($tier['validFrom'] > $at) {
                break;
            }
            $applicable = $tier['requiredHours'];
        }

        return $applicable;
    }
}
