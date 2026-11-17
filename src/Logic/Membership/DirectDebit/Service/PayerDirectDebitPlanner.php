<?php

namespace App\Logic\Membership\DirectDebit\Service;

use App\Logic\Common\Iban;
use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitCreditorManagerInterface;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitRecordManagerInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;
use App\Logic\Membership\DirectDebit\Model\DirectDebitObstacle;
use App\Logic\Membership\DirectDebit\Model\DirectDebitObstacleKind;
use App\Logic\Membership\DirectDebit\Model\DirectDebitRecord;
use App\Logic\Membership\DirectDebit\Model\DirectDebitPosition;
use App\Logic\Membership\DirectDebit\Model\DirectDebitPositionKind;
use App\Logic\Membership\DirectDebit\Model\PayerDirectDebitDraft;
use App\Logic\Membership\DirectDebit\Model\SequenceType;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentMethod;

/**
 * Stellt die Lastschrift eines Zahlers zusammen: derselbe Personenkreis wie in der
 * „Gesamtberechnung für den Zahler“ (`GetMemberHouseholdQuery`) — der Zahler selbst, sofern
 * Selbstzahler, plus alle Mitglieder, deren Beitrag er trägt. Je Person werden der gespeicherte
 * Beitrag und Arbeitseinsatz-Zuschlag sowie deren einmalige Gebühren (z. B. Beitrittsgebühr) als
 * wählbare Positionen angeboten; Ausgetretene und Beitragsbefreite entfallen wie dort. Ist der
 * Zahler selbst ausgetreten, zahlt aber noch für aktive Mitglieder (z. B. ein Elternteil für die
 * Kinder), wird für diese weiter über sein Konto eingezogen — das Mandat hängt am Kontoinhaber,
 * nicht an der Mitgliedschaft. Das wird als Hinweis angezeigt; sein eigener Beitrag entfällt.
 *
 * Anhand der Lastschrift-Historie des Zahlers: Ist er im laufenden Jahr eingetreten und wurde für
 * dieses Jahr noch nichts eingezogen, ist es die Lastschrift für das Eintrittsjahr (sofort fällig).
 * Sonst gilt die „Nächste Buchung“. Erstlastschrift (FRST) wird vorgeschlagen, wenn das Mandat noch
 * nie über die App eingezogen wurde und im laufenden Jahr unterschrieben ist (Neumitglied oder neues
 * Mandat, siehe `CreateMemberMandateUseCase`) — aus Sage übernommene Mandate wurden dort bereits
 * genutzt und laufen als Folgelastschrift (RCUR) weiter.
 *
 * Mitgliedsbeiträge, Arbeitseinsatz-Pauschale und Einmalgebühren werden getrennt exportiert
 * (`DirectDebitPositionKind`); jeder Entwurf enthält nur die Positionen einer Art:
 *
 * - Beiträge: wie oben beschrieben, auf das Zahlintervall umgelegt (z. B. ein Viertel des
 *   Jahresbetrags bei vierteljährlicher Zahlung); nur dieser Export rückt die „Nächste Buchung“ vor.
 * - Arbeitseinsatz-Pauschale: einmal je Kalenderjahr in voller Höhe, fällig zum nächsten Zahltag —
 *   unabhängig von der „Nächsten Buchung“, die ein vorheriger Beitragsexport schon vorgerückt hat.
 * - Einmalgebühren: alle noch nicht eingezogenen Gebühren, fällig zum nächsten Zahltag. Nach dem
 *   Export gelten sie als eingezogen (siehe `DirectDebitBookkeeper`).
 *
 * „Bereits eingezogen“ und Eintrittsjahr beziehen sich nur auf die Historie derselben Art
 * (`DirectDebitRecord::covers()`); ob das Mandat schon genutzt wurde (FRST/RCUR), auf alle Exporte.
 */
readonly class PayerDirectDebitPlanner
{
    public function __construct(
        private MemberManagerInterface $members,
        private ContributionRateManagerInterface $rates,
        private DirectDebitCreditorManagerInterface $creditors,
        private DirectDebitRecordManagerInterface $records,
    ) {
    }

    public function plan(string $memberId, \DateTimeImmutable $now, DirectDebitPositionKind $kind = DirectDebitPositionKind::Contribution): PayerDirectDebitDraft
    {
        $member = $this->members->get($memberId);
        $payer = $member->payerType === PayerType::OtherMember && $member->payerMemberId !== null
            ? $this->members->get($member->payerMemberId)
            : $member;

        return $this->planPayer(
            $payer,
            $this->members->findByPayerMemberId($payer->id),
            $now,
            $this->creditors->get(),
            $this->records->findByPayerMemberId($payer->id),
            fn (ContributionCategory $category): ?string => $this->rates->findByCategory($category)?->label,
            $payer->payerType === PayerType::OtherMember && $payer->payerMemberId === null,
            $kind,
            $kind === DirectDebitPositionKind::OneTimeCharge ? $this->records->findCollectedChargeIds() : [],
        );
    }

    /**
     * Dieselbe Planung wie `plan()` für jeden Zahler des Vereins — für die Lastschrift-Übersicht und
     * den Sammelexport. Mitglieder, Beitragssätze, Gläubigerdaten und Lastschrift-Historie werden
     * dafür nur einmal geladen statt je Zahler. Zahler ist jedes Mitglied, das selbst zahlt oder für
     * das ein anderes Mitglied als Zahler eingetragen ist; Zahler ohne abbuchbare Positionen
     * (z. B. vollständig ausgetretene oder beitragsbefreite Haushalte) liefern eine leere
     * Positionsliste.
     *
     * @return list<PayerDirectDebitDraft>
     */
    public function planAll(\DateTimeImmutable $now, DirectDebitPositionKind $kind = DirectDebitPositionKind::Contribution): array
    {
        $allMembers = $this->members->search(null);
        $membersById = [];
        $paidByPayerId = [];
        foreach ($allMembers as $member) {
            $membersById[$member->id] = $member;
            if ($member->payerType === PayerType::OtherMember && $member->payerMemberId !== null) {
                $paidByPayerId[$member->payerMemberId][] = $member;
            }
        }
        $rateLabels = [];
        foreach ($this->rates->list() as $rate) {
            if ($rate->category !== null) {
                $rateLabels[$rate->category->value] ??= $rate->label;
            }
        }
        $recordsByPayerId = [];
        foreach ($this->records->findAll() as $record) {
            $recordsByPayerId[$record->payerMemberId][] = $record;
        }
        $creditor = $this->creditors->get();
        $collectedChargeIds = $kind === DirectDebitPositionKind::OneTimeCharge ? $this->records->findCollectedChargeIds() : [];

        $drafts = [];
        foreach ($allMembers as $member) {
            $withoutPayer = $member->payerType === PayerType::OtherMember
                && ($member->payerMemberId === null || !isset($membersById[$member->payerMemberId]));
            if ($member->payerType !== PayerType::SelfPayer && !$withoutPayer && !isset($paidByPayerId[$member->id])) {
                continue;
            }
            $draft = $this->planPayer(
                $member,
                $paidByPayerId[$member->id] ?? [],
                $now,
                $creditor,
                $recordsByPayerId[$member->id] ?? [],
                static fn (ContributionCategory $category): ?string => $rateLabels[$category->value] ?? null,
                $withoutPayer,
                $kind,
                $collectedChargeIds,
            );
            // Ein ausgetretener Zahler ohne weitere aktive Mitglieder ist kein Zahler mehr — er taucht
            // gar nicht auf. Zahlt er noch für aktive Mitglieder, wird für diese weiter eingezogen.
            if ($member->hasLeft($now) && $draft->positions === []) {
                continue;
            }
            $drafts[] = $draft;
        }

        return $drafts;
    }

    /**
     * @param list<Member>                             $paidByPayer
     * @param list<DirectDebitRecord>                  $records     neueste zuerst
     * @param callable(ContributionCategory): ?string $rateLabel
     * @param bool                                     $withoutPayer Mitglied zahlt laut Zahlungsdaten nicht
     *                                                               selbst, hat aber keinen (existierenden)
     *                                                               Zahler — es würde sonst stillschweigend
     *                                                               nie eingezogen und wird deshalb als
     *                                                               eigener, blockierter Zahler geführt.
     * @param list<string>                             $collectedChargeIds bereits eingezogene Einmalgebühren
     */
    private function planPayer(
        Member $payer,
        array $paidByPayer,
        \DateTimeImmutable $now,
        DirectDebitCreditor $creditor,
        array $records,
        callable $rateLabel,
        bool $withoutPayer,
        DirectDebitPositionKind $kind,
        array $collectedChargeIds,
    ): PayerDirectDebitDraft {
        // Zahler zuoberst, darunter die übrigen vom ältesten zum jüngsten.
        usort($paidByPayer, static fn (Member $left, Member $right): int => $left->birthDate <=> $right->birthDate);
        $entries = $payer->payerType === PayerType::SelfPayer || $withoutPayer ? [$payer, ...$paidByPayer] : $paidByPayer;

        $warnings = [];
        $positions = [];
        foreach ($entries as $entry) {
            if ($entry->hasLeft($now) || !$entry->contributionLiable) {
                continue;
            }
            if ($entry->contributionCategory === null && $kind !== DirectDebitPositionKind::OneTimeCharge) {
                $warnings[] = sprintf('Für %s ist noch kein Beitrag berechnet.', $this->memberName($entry));
            }
            $positions = [...$positions, ...$this->positionsFor($entry, $payer, $rateLabel, $kind, $collectedChargeIds)];
        }
        if ($payer->hasLeft($now) && $payer->leftAt !== null && $positions !== []) {
            $warnings[] = sprintf(
                'Der Zahler ist zum %s ausgetreten – es wird weiter über sein Konto für %s eingezogen.',
                $payer->leftAt->format('d.m.Y'),
                implode(', ', array_values(array_unique(array_map(static fn (DirectDebitPosition $position): string => $position->memberName, $positions)))),
            );
        }

        $mandateSignedOn = $payer->mandateValidFrom ?? $payer->joinedAt;
        if ($payer->mandateValidFrom === null) {
            $warnings[] = sprintf('Kein Mandatsdatum hinterlegt – es wird das Eintrittsdatum %s verwendet.', $payer->joinedAt->format('d.m.Y'));
        }

        $year = (int) $now->format('Y');
        $kindRecords = array_values(array_filter($records, static fn (DirectDebitRecord $record): bool => $record->covers($kind)));
        $mandateUsed = $this->hasRecordFor($records, static fn (DirectDebitRecord $record): bool => $record->mandateReference === $payer->mandateReference);
        if ($kind === DirectDebitPositionKind::Contribution) {
            $joinedThisYear = (int) $payer->joinedAt->format('Y') === $year;
            $joiningYearDebit = $joinedThisYear && !$this->hasRecordFor($kindRecords, static fn (DirectDebitRecord $record): bool => $record->contributionYear === $year);
            $contributionYear = $joiningYearDebit ? $year : ($payer->nextBookingYear ?? $year);
            $collectionDate = $joiningYearDebit ? $this->nextPaymentDay($payer, $now) : $this->defaultCollectionDate($payer, $now);
        } else {
            $joiningYearDebit = false;
            $contributionYear = $year;
            $collectionDate = $this->nextPaymentDay($payer, $now);
        }
        $alreadyCollected = false;
        // Einmalgebühren werden je Gebühr als eingezogen vermerkt, nicht über das Beitragsjahr.
        foreach ($kind === DirectDebitPositionKind::OneTimeCharge ? [] : $kindRecords as $record) {
            if ($record->contributionYear === $contributionYear) {
                $alreadyCollected = true;
                $warnings[] = $record->isLegacyImport()
                    ? sprintf('Für %d wurde laut Sage-Übernahme bereits eingezogen.', $contributionYear)
                    : sprintf('Für %d wurde bereits am %s eine Lastschrift über %s exportiert.', $contributionYear, $record->exportedAt->format('d.m.Y'), number_format($record->amountCents / 100, 2, ',', '.').' €');
                break;
            }
        }
        $remittanceSuffix = match ($kind) {
            DirectDebitPositionKind::Contribution => 'Mitgliedsbeitrag',
            DirectDebitPositionKind::WorkAssignmentSurcharge => 'Arbeitseinsatz',
            DirectDebitPositionKind::OneTimeCharge => 'Einmalgebühren',
        };

        return new PayerDirectDebitDraft(
            payer: $payer,
            debtorName: $payer->accountHolder ?? $this->memberName($payer),
            mandateSignedOn: $mandateSignedOn,
            creditor: $creditor,
            positions: $positions,
            blockers: $this->blockers($payer, $creditor->isComplete(), $now, $withoutPayer),
            warnings: $warnings,
            defaultCollectionDate: $collectionDate,
            // Mandatsreferenz, Gläubiger-ID, Zahlungsempfänger und Art der Lastschrift.
            defaultRemittanceInformation: implode(' ', array_filter(
                [$payer->mandateReference, $creditor->creditorId, $creditor->name, $remittanceSuffix],
                static fn (?string $part): bool => $part !== null && trim($part) !== '',
            )),
            defaultSequenceType: !$mandateUsed && (int) $mandateSignedOn->format('Y') >= $year ? SequenceType::First : SequenceType::Recurring,
            contributionYear: $contributionYear,
            joiningYearDebit: $joiningYearDebit,
            lastRecord: $kindRecords[0] ?? null,
            alreadyCollected: $alreadyCollected,
            kind: $kind,
        );
    }

    /**
     * @param list<DirectDebitRecord>          $records
     * @param callable(DirectDebitRecord): bool $matches
     */
    private function hasRecordFor(array $records, callable $matches): bool
    {
        foreach ($records as $record) {
            if ($matches($record)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param callable(ContributionCategory): ?string $rateLabel
     * @param list<string>                             $collectedChargeIds
     *
     * @return list<DirectDebitPosition>
     */
    private function positionsFor(Member $entry, Member $payer, callable $rateLabel, DirectDebitPositionKind $kind, array $collectedChargeIds): array
    {
        $occurrences = $payer->paymentInterval->occurrencesPerYear();
        $positions = [];
        if ($kind === DirectDebitPositionKind::Contribution && $entry->contributionCategory !== null && ($entry->contributionAmountCents ?? 0) > 0) {
            $annual = $entry->contributionAmountCents ?? 0;
            $positions[] = new DirectDebitPosition(
                id: $entry->id.':contribution',
                memberId: $entry->id,
                memberNumber: $entry->memberNumber,
                memberName: $this->memberName($entry),
                kind: DirectDebitPositionKind::Contribution,
                label: $rateLabel($entry->contributionCategory) ?? 'Mitgliedsbeitrag',
                amountCents: (int) round($annual / $occurrences),
                annualAmountCents: $annual,
                selectedByDefault: true,
            );
        }
        // Die Pauschale wird einmal im Jahr in voller Höhe eingezogen, unabhängig vom Zahlintervall.
        if ($kind === DirectDebitPositionKind::WorkAssignmentSurcharge && ($entry->workAssignmentSurchargeCents ?? 0) > 0) {
            $annual = $entry->workAssignmentSurchargeCents ?? 0;
            $positions[] = new DirectDebitPosition(
                id: $entry->id.':work_assignment_surcharge',
                memberId: $entry->id,
                memberNumber: $entry->memberNumber,
                memberName: $this->memberName($entry),
                kind: DirectDebitPositionKind::WorkAssignmentSurcharge,
                label: 'Arbeitseinsatz-Pauschale',
                amountCents: $annual,
                annualAmountCents: $annual,
                selectedByDefault: true,
            );
        }
        foreach ($kind === DirectDebitPositionKind::OneTimeCharge ? $entry->oneTimeCharges : [] as $charge) {
            if ($charge->amountCents <= 0 || in_array($charge->id, $collectedChargeIds, true)) {
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
                selectedByDefault: true,
                chargeId: $charge->id,
            );
        }

        return $positions;
    }

    /**
     * @return list<DirectDebitObstacle>
     */
    private function blockers(Member $payer, bool $creditorComplete, \DateTimeImmutable $now, bool $withoutPayer): array
    {
        $blockers = [];
        if ($withoutPayer) {
            $blockers[] = new DirectDebitObstacle(DirectDebitObstacleKind::MissingPayer, 'Das Mitglied zahlt laut Zahlungsdaten nicht selbst, es ist aber kein zahlendes Mitglied hinterlegt.');
        }
        if (!$creditorComplete) {
            $blockers[] = new DirectDebitObstacle(DirectDebitObstacleKind::IncompleteCreditor, 'Die SEPA-Gläubigerdaten des Vereins sind nicht vollständig (Mitgliederverwaltung → Beitragssätze → SEPA-Gläubigerdaten).');
        }
        // Bankverbindung und Mandat sind nur bei SEPA-Lastschrift relevant — bei einer anderen
        // Zahlart ist das der einzige Grund, statt zusätzlich fehlende IBAN/Mandat zu melden.
        if ($payer->paymentMethod !== PaymentMethod::SepaDirectDebit) {
            $blockers[] = new DirectDebitObstacle(DirectDebitObstacleKind::OtherPaymentMethod, 'Die Zahlart des Zahlers ist nicht SEPA-Lastschrift.');

            return $blockers;
        }
        if (Iban::isMissingOrPlaceholder($payer->iban)) {
            $blockers[] = new DirectDebitObstacle(DirectDebitObstacleKind::MissingBankAccount, 'Für den Zahler ist keine echte Bankverbindung hinterlegt.');
        }
        if ($payer->mandateReference === null || trim($payer->mandateReference) === '') {
            $blockers[] = new DirectDebitObstacle(DirectDebitObstacleKind::MissingMandate, 'Für den Zahler ist noch kein SEPA-Mandat erzeugt (Mitglied → Beitragsdaten → Kontodaten → „Mandat erzeugen“).');
        }
        if ($payer->mandateValidUntil !== null && $payer->mandateValidUntil->format('Y-m-d') < $now->format('Y-m-d')) {
            $blockers[] = new DirectDebitObstacle(DirectDebitObstacleKind::ExpiredMandate, sprintf('Das SEPA-Mandat des Zahlers ist abgelaufen (gültig bis %s).', $payer->mandateValidUntil->format('d.m.Y')));
        }

        return $blockers;
    }

    /**
     * Fälligkeit laut „Nächste Buchung“ des Zahlers (Monat/Jahr) an seinem Zahltag (01. bzw. 15.).
     * Ist keine nächste Buchung hinterlegt oder liegt sie weniger als zwei Tage in der Zukunft (bzw.
     * schon zurück), der nächste Zahltag mit mindestens zwei Tagen Vorlauf. Fällt das Datum aufs
     * Wochenende, gilt der darauffolgende Montag.
     */
    private function defaultCollectionDate(Member $payer, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $earliest = $now->setTime(0, 0)->modify('+2 days');
        if ($payer->nextBookingMonth === null || $payer->nextBookingYear === null) {
            return $this->nextPaymentDay($payer, $now);
        }
        $candidate = $earliest->setDate($payer->nextBookingYear, $payer->nextBookingMonth, $payer->paymentDay->dayOfMonth());

        return $candidate < $earliest ? $this->nextPaymentDay($payer, $now) : $this->skipWeekend($candidate);
    }

    /** Nächster Zahltag des Zahlers mit mindestens zwei Tagen Vorlauf. */
    private function nextPaymentDay(Member $payer, \DateTimeImmutable $now): \DateTimeImmutable
    {
        $earliest = $now->setTime(0, 0)->modify('+2 days');
        $candidate = $earliest->setDate((int) $earliest->format('Y'), (int) $earliest->format('n'), $payer->paymentDay->dayOfMonth());
        if ($candidate < $earliest) {
            $candidate = $candidate->modify('+1 month');
        }

        return $this->skipWeekend($candidate);
    }

    private function skipWeekend(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $weekday = (int) $date->format('N');

        return $weekday >= 6 ? $date->modify(sprintf('+%d days', 8 - $weekday)) : $date;
    }

    private function memberName(Member $member): string
    {
        return $member->firstName.' '.$member->lastName;
    }
}
