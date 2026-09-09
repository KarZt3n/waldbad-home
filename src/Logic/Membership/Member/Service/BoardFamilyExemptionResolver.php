<?php

namespace App\Logic\Membership\Member\Service;

use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;

/**
 * Vorstandsmitglieder sind laut Satzung beitragsfrei — und mit ihnen ihr gesamter Haushalt (alle
 * Mitglieder mit derselben Hauptnummer): Bei jeder Beitragsberechnung wird `Member::$contributionLiable`
 * für den gesamten Haushalt auf den aus der aktuellen Funktionslage abgeleiteten Wert korrigiert —
 * unabhängig davon, was zuvor gespeichert war. Enthält der Haushalt mindestens ein Mitglied mit der
 * Funktion „Vorstand“, wird der ganze Haushalt beitragsfrei gestellt; enthält er keins (mehr), wird
 * der ganze Haushalt wieder beitragspflichtig. Wird von jeder Stelle verwendet, die tatsächlich „den
 * Beitrag berechnet“: `HouseholdContributionRecalculator` (manuelles „Beitrag neu berechnen“ sowie
 * der automatische Trigger beim Funktionswechsel in `UpdateMemberUseCase`) und
 * `RecalculateAllMemberContributionsUseCase` („Beiträge für alle Mitglieder neu berechnen“).
 *
 * Bewusst symmetrisch (auch das Zurücksetzen auf true, sobald kein Vorstandsmitglied mehr im
 * Haushalt ist, nicht nur das für das gerade bearbeitete Mitglied): Sonst blieben die übrigen
 * Familienmitglieder nach einem Funktionswechsel weg vom Vorstand dauerhaft fälschlich beitragsfrei,
 * bis sie einzeln von Hand reaktiviert würden — das Modell kennt keinen von „Vorstand“ unabhängigen
 * Befreiungsgrund, den es hierbei zu schonen gälte.
 *
 * `hasBoardMember()` und `correct()` bewusst getrennt (statt einer einzigen Methode, die den ganzen
 * Haushalt auf einmal korrigiert): `correct()` kann fachlich scheitern — z. B. wenn ein reaktiviertes
 * Mitglied als Selbstzahler mit SEPA-Lastschrift keine gültige IBAN hinterlegt hat
 * (`Member::__construct()`). Die Aufrufer wenden `correct()` deshalb pro Mitglied innerhalb ihrer
 * eigenen Fehlerbehandlung an, damit ein einzelner fehlerhafter Datensatz weder die Korrektur der
 * übrigen Haushaltsmitglieder verhindert noch als anonyme Fehlermeldung ohne erkennbaren Bezug zu
 * einem Mitglied endet.
 */
readonly class BoardFamilyExemptionResolver
{
    /**
     * @param list<Member> $household
     */
    public function hasBoardMember(array $household): bool
    {
        foreach ($household as $member) {
            if ($member->function === MemberFunction::Board) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws \App\Logic\Common\Exception\BusinessRuleViolationException wenn $member reaktiviert
     *         werden müsste, dafür aber z. B. keine gültige IBAN hinterlegt ist.
     */
    public function correct(Member $member, bool $hasBoardMember): Member
    {
        $liable = !$hasBoardMember;

        return $member->contributionLiable === $liable ? $member : $member->withContributionLiable($liable);
    }
}
