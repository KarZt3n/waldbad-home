<?php

namespace App\Logic\Membership\DirectDebit\Service;

use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitCreditorManagerInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitPosition;
use App\Logic\Membership\DirectDebit\Model\DirectDebitPositionKind;
use App\Logic\Membership\DirectDebit\Model\PayerDirectDebitDraft;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentMethod;

/**
 * Stellt die Lastschrift eines Zahlers zusammen: derselbe Personenkreis wie in der
 * „Gesamtberechnung für den Zahler“ (`GetMemberHouseholdQuery`) — der Zahler selbst, sofern
 * Selbstzahler, plus alle Mitglieder, deren Beitrag er trägt. Je Person werden der gespeicherte
 * Beitrag und Arbeitseinsatz-Zuschlag sowie deren einmalige Gebühren (z. B. Beitrittsgebühr) als
 * wählbare Positionen angeboten; Ausgetretene und Beitragsbefreite entfallen wie dort.
 *
 * Laufende Positionen werden auf das Zahlintervall des Zahlers umgelegt (z. B. ein Viertel des
 * Jahresbetrags bei vierteljährlicher Zahlung). Einmalige Gebühren sind nur vorausgewählt, wenn sie
 * im laufenden Jahr berechnet wurden — ältere wurden vermutlich schon eingezogen.
 */
readonly class PayerDirectDebitPlanner
{
    /** Bankleitzahl 00000000 gibt es nicht — so ist die beim Sage-Import gesetzte Platzhalter-IBAN erkennbar. */
    private const string PLACEHOLDER_GERMAN_BANK_CODE = '00000000';

    public function __construct(
        private MemberManagerInterface $members,
        private ContributionRateManagerInterface $rates,
        private DirectDebitCreditorManagerInterface $creditors,
    ) {
    }

    public function plan(string $memberId, \DateTimeImmutable $now): PayerDirectDebitDraft
    {
        $member = $this->members->get($memberId);
        $payer = $member->payerType === PayerType::OtherMember && $member->payerMemberId !== null
            ? $this->members->get($member->payerMemberId)
            : $member;
        $paidByPayer = $this->members->findByPayerMemberId($payer->id);
        $entries = $payer->payerType === PayerType::SelfPayer ? [$payer, ...$paidByPayer] : $paidByPayer;

        $warnings = [];
        $positions = [];
        foreach ($entries as $entry) {
            if ($entry->hasLeft($now) || !$entry->contributionLiable) {
                continue;
            }
            if ($entry->contributionCategory === null) {
                $warnings[] = sprintf('Für %s ist noch kein Beitrag berechnet.', $this->memberName($entry));
            }
            $positions = [...$positions, ...$this->positionsFor($entry, $payer, $now)];
        }

        $creditor = $this->creditors->get();
        $mandateSignedOn = $payer->mandateValidFrom ?? $payer->joinedAt;
        if ($payer->mandateValidFrom === null) {
            $warnings[] = sprintf('Kein Mandatsdatum hinterlegt – es wird das Eintrittsdatum %s verwendet.', $payer->joinedAt->format('d.m.Y'));
        }

        return new PayerDirectDebitDraft(
            payer: $payer,
            debtorName: $payer->accountHolder ?? $this->memberName($payer),
            mandateSignedOn: $mandateSignedOn,
            creditor: $creditor,
            positions: $positions,
            blockers: $this->blockers($payer, $creditor->isComplete(), $now),
            warnings: $warnings,
            defaultCollectionDate: $this->defaultCollectionDate($payer, $now),
            defaultRemittanceInformation: sprintf('Mitgliedsbeitrag %s %s %s', $now->format('Y'), $payer->memberNumber, $payer->lastName),
        );
    }

    /**
     * @return list<DirectDebitPosition>
     */
    private function positionsFor(Member $entry, Member $payer, \DateTimeImmutable $now): array
    {
        $occurrences = $payer->paymentInterval->occurrencesPerYear();
        $positions = [];
        if ($entry->contributionCategory !== null && ($entry->contributionAmountCents ?? 0) > 0) {
            $annual = $entry->contributionAmountCents ?? 0;
            $positions[] = new DirectDebitPosition(
                id: $entry->id.':contribution',
                memberId: $entry->id,
                memberNumber: $entry->memberNumber,
                memberName: $this->memberName($entry),
                kind: DirectDebitPositionKind::Contribution,
                label: $this->rates->findByCategory($entry->contributionCategory)->label ?? 'Mitgliedsbeitrag',
                amountCents: (int) round($annual / $occurrences),
                annualAmountCents: $annual,
                selectedByDefault: true,
            );
        }
        if (($entry->workAssignmentSurchargeCents ?? 0) > 0) {
            $annual = $entry->workAssignmentSurchargeCents ?? 0;
            $positions[] = new DirectDebitPosition(
                id: $entry->id.':work_assignment_surcharge',
                memberId: $entry->id,
                memberNumber: $entry->memberNumber,
                memberName: $this->memberName($entry),
                kind: DirectDebitPositionKind::WorkAssignmentSurcharge,
                label: 'Arbeitseinsatz-Zuschlag',
                amountCents: (int) round($annual / $occurrences),
                annualAmountCents: $annual,
                selectedByDefault: true,
            );
        }
        foreach ($entry->oneTimeCharges as $charge) {
            if ($charge->amountCents <= 0) {
                continue;
            }
            $positions[] = new DirectDebitPosition(
                id: $entry->id.':charge:'.$charge->id,
                memberId: $entry->id,
                memberNumber: $entry->memberNumber,
                memberName: $this->memberName($entry),
                kind: DirectDebitPositionKind::OneTimeCharge,
                label: sprintf('%s (berechnet am %s)', $charge->label, $charge->chargedAt->format('d.m.Y')),
                amountCents: $charge->amountCents,
                annualAmountCents: null,
                selectedByDefault: $charge->chargedAt->format('Y') === $now->format('Y'),
            );
        }

        return $positions;
    }

    /**
     * @return list<string>
     */
    private function blockers(Member $payer, bool $creditorComplete, \DateTimeImmutable $now): array
    {
        $blockers = [];
        if (!$creditorComplete) {
            $blockers[] = 'Die SEPA-Gläubigerdaten des Vereins sind nicht vollständig (Mitgliederverwaltung → Beitragssätze → SEPA-Gläubigerdaten).';
        }
        if ($payer->paymentMethod !== PaymentMethod::SepaDirectDebit) {
            $blockers[] = 'Die Zahlart des Zahlers ist nicht SEPA-Lastschrift.';
        }
        if ($payer->iban === null || (str_starts_with($payer->iban, 'DE') && substr($payer->iban, 4, 8) === self::PLACEHOLDER_GERMAN_BANK_CODE)) {
            $blockers[] = 'Für den Zahler ist keine echte Bankverbindung hinterlegt.';
        }
        if ($payer->mandateReference === null || trim($payer->mandateReference) === '') {
            $blockers[] = 'Für den Zahler ist keine Mandatsreferenz hinterlegt.';
        }
        if ($payer->mandateValidUntil !== null && $payer->mandateValidUntil->format('Y-m-d') < $now->format('Y-m-d')) {
            $blockers[] = sprintf('Das SEPA-Mandat des Zahlers ist abgelaufen (gültig bis %s).', $payer->mandateValidUntil->format('d.m.Y'));
        }

        return $blockers;
    }

    /**
     * Nächster Zahltag des Zahlers (01. bzw. 15.), der mindestens zwei Tage in der Zukunft liegt —
     * fällt er aufs Wochenende, der darauffolgende Montag.
     */
    private function defaultCollectionDate(Member $payer, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $earliest = $now->setTime(0, 0)->modify('+2 days');
        $candidate = $earliest->setDate((int) $earliest->format('Y'), (int) $earliest->format('n'), $payer->paymentDay->dayOfMonth());
        if ($candidate < $earliest) {
            $candidate = $candidate->modify('+1 month');
        }
        $weekday = (int) $candidate->format('N');

        return $weekday >= 6 ? $candidate->modify(sprintf('+%d days', 8 - $weekday)) : $candidate;
    }

    private function memberName(Member $member): string
    {
        return $member->firstName.' '.$member->lastName;
    }
}
