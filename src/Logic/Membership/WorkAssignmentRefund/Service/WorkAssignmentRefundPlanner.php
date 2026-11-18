<?php

namespace App\Logic\Membership\WorkAssignmentRefund\Service;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\Iban;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitCreditorManagerInterface;
use App\Logic\Membership\DirectDebit\Model\DirectDebitCreditor;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Exception\MemberNotFoundException;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\MemberAccess\Dto\WorkAssignmentCreditResponse;
use App\Logic\Membership\MemberAccess\Service\WorkAssignmentCreditCalculator;
use App\Logic\Membership\WorkAssignmentRefund\Manager\WorkAssignmentRefundRecordManagerInterface;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundDraft;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundObstacle;
use App\Logic\Membership\WorkAssignmentRefund\Model\WorkAssignmentRefundObstacleKind;

/**
 * Stellt die Gutschrift für geleistete Arbeitsstunden je Zahler-Strang und Beitragsjahr zusammen —
 * dieselbe Berechnung wie bei den Arbeitseinsätzen des Mitglieds (`WorkAssignmentCreditCalculator`),
 * angewandt auf den Zahler und alle, für die er zahlt (wie „Meine Mitgliedschaft“, siehe
 * `MemberPayerGroupResolver`): Stunden sind innerhalb des Strangs übertragbar, die Gutschrift ist auf
 * dessen Arbeitseinsatz-Zuschläge gedeckelt und geht an den Zahler. Ein Selbstzahler im selben
 * Haushalt bildet einen eigenen Strang mit eigener Gutschrift. Kennung eines Strangs ist die
 * Mitgliedsnummer des Zahlers; ein Mitglied ohne gültigen Zahler bildet einen eigenen Strang ohne
 * Empfänger. Die Zahlart spielt keine Rolle — es genügt eine echte Bankverbindung.
 *
 * Bereits an den Zahler überwiesene Beträge des Jahres (Gutschrift-Historie) werden abgezogen.
 * Stränge ohne geleistete Stunden und ohne frühere Überweisung tauchen nicht auf.
 */
readonly class WorkAssignmentRefundPlanner
{
    /** Wie viele Beitragsjahre (inkl. laufendem) zur Auswahl stehen. */
    private const int SELECTABLE_YEARS = 3;

    public function __construct(
        private MemberManagerInterface $members,
        private WorkAssignmentCreditCalculator $calculator,
        private DirectDebitCreditorManagerInterface $creditors,
        private WorkAssignmentRefundRecordManagerInterface $records,
    ) {
    }

    /** @return list<int> laufendes Jahr zuerst */
    public function selectableYears(\DateTimeImmutable $now): array
    {
        $current = (int) $now->format('Y');

        return range($current, $current - self::SELECTABLE_YEARS + 1);
    }

    /** @return list<WorkAssignmentRefundDraft> nach Hauptmitgliedsnummer sortiert */
    public function planAll(int $year, \DateTimeImmutable $now): array
    {
        $this->assertSelectableYear($year, $now);

        $membersById = [];
        foreach ($this->members->search(null) as $member) {
            $membersById[$member->id] = $member;
        }
        $strands = [];
        foreach ($membersById as $member) {
            $strands[$this->strandKey($member, $membersById)][] = $member;
        }
        $refundedByPayer = [];
        foreach ($this->records->findByYear($year) as $record) {
            $refundedByPayer[$record->payerMemberId] = ($refundedByPayer[$record->payerMemberId] ?? 0) + $record->amountCents;
        }
        $periodFrom = $this->periodFrom($year);
        $credits = $this->calculator->calculateForHouseholds($strands, $periodFrom, $periodFrom->modify('+1 year'));
        $club = $this->clubAccount();

        $drafts = [];
        foreach ($strands as $key => $strandMembers) {
            $key = (string) $key;
            $credit = $credits[$key];
            $refunded = $refundedByPayer[$key] ?? 0;
            if ($credit->workedMinutes <= 0 && $refunded <= 0) {
                continue;
            }
            $drafts[] = $this->planStrand($membersById[$key], $strandMembers, $year, $credit, $refunded, $club);
        }
        usort($drafts, static fn (WorkAssignmentRefundDraft $left, WorkAssignmentRefundDraft $right): int => strnatcmp($left->payerMemberNumber, $right->payerMemberNumber));

        return $drafts;
    }

    /**
     * Dieselbe Planung wie `planAll()` für einen einzelnen Zahler-Strang (Mitgliedsnummer des
     * Zahlers) — für die Detailansicht. Liefert auch Stränge ohne geleistete Stunden.
     */
    public function plan(string $payerMemberNumber, int $year, \DateTimeImmutable $now): WorkAssignmentRefundDraft
    {
        $this->assertSelectableYear($year, $now);
        $anchor = $this->members->findByMemberNumber($payerMemberNumber)
            ?? throw new BusinessRuleViolationException('Zu dieser Mitgliedsnummer gibt es kein Mitglied.');
        if ($anchor->payerType === PayerType::OtherMember && $anchor->payerMemberId !== null) {
            $payer = $this->findMember($anchor->payerMemberId);
            if ($payer !== null && $payer->payerType === PayerType::SelfPayer) {
                $anchor = $payer;
            }
        }
        $strandMembers = [$anchor];
        if ($anchor->payerType === PayerType::SelfPayer) {
            foreach ($this->members->findByPayerMemberId($anchor->id) as $member) {
                if ($member->payerType === PayerType::OtherMember && $member->id !== $anchor->id) {
                    $strandMembers[] = $member;
                }
            }
        }
        $refunded = 0;
        foreach ($this->records->findByYear($year) as $record) {
            if ($record->payerMemberId === $anchor->id) {
                $refunded += $record->amountCents;
            }
        }
        $periodFrom = $this->periodFrom($year);

        return $this->planStrand(
            $anchor,
            $strandMembers,
            $year,
            $this->calculator->calculateForPeriod($strandMembers, $periodFrom, $periodFrom->modify('+1 year')),
            $refunded,
            $this->clubAccount(),
        );
    }

    /**
     * Schlüssel des Strangs: der Zahler (Selbstzahler sich selbst), ohne gültigen Zahler das
     * Mitglied selbst.
     *
     * @param array<string, Member> $membersById
     */
    private function strandKey(Member $member, array $membersById): string
    {
        if ($member->payerType === PayerType::OtherMember && $member->payerMemberId !== null) {
            $payer = $membersById[$member->payerMemberId] ?? null;
            if ($payer !== null && $payer->payerType === PayerType::SelfPayer) {
                return $payer->id;
            }
        }

        return $member->id;
    }

    /**
     * @param non-empty-list<Member> $strandMembers
     */
    private function planStrand(Member $anchor, array $strandMembers, int $year, WorkAssignmentCreditResponse $credit, int $refunded, DirectDebitCreditor $club): WorkAssignmentRefundDraft
    {
        $obstacles = [];
        $recipient = $anchor->payerType === PayerType::SelfPayer ? $anchor : null;
        if ($recipient === null) {
            $obstacles[] = new WorkAssignmentRefundObstacle(WorkAssignmentRefundObstacleKind::MissingPayer, 'Für dieses Mitglied ist kein zahlendes Mitglied hinterlegt.');
        } elseif (Iban::isMissingOrPlaceholder($recipient->iban)) {
            $obstacles[] = new WorkAssignmentRefundObstacle(WorkAssignmentRefundObstacleKind::MissingBankAccount, 'Für den Zahler ist keine echte Bankverbindung hinterlegt.');
        }
        if ($club->name === null || $club->iban === null) {
            $obstacles[] = new WorkAssignmentRefundObstacle(WorkAssignmentRefundObstacleKind::IncompleteClubAccount, 'Name und IBAN des Vereinskontos fehlen (Mitgliederverwaltung → Beitragssätze → SEPA-Gläubigerdaten).');
        }

        return new WorkAssignmentRefundDraft(
            payerMemberNumber: $anchor->memberNumber,
            primaryMemberNumber: $anchor->primaryMemberNumber,
            year: $year,
            members: $strandMembers,
            recipient: $recipient,
            contact: $anchor,
            recipientName: $recipient->accountHolder ?? $anchor->firstName.' '.$anchor->lastName,
            credit: $credit,
            refundedCents: $refunded,
            obstacles: $obstacles,
        );
    }

    private function findMember(string $id): ?Member
    {
        try {
            return $this->members->get($id);
        } catch (MemberNotFoundException) {
            return null;
        }
    }

    private function assertSelectableYear(int $year, \DateTimeImmutable $now): void
    {
        if (!in_array($year, $this->selectableYears($now), true)) {
            throw new BusinessRuleViolationException('Für dieses Beitragsjahr kann keine Gutschrift erstellt werden.');
        }
    }

    private function periodFrom(int $year): \DateTimeImmutable
    {
        return new \DateTimeImmutable(sprintf('%04d-01-01', $year));
    }

    /** Auftraggeberkonto der Überweisung: das Vereinskonto aus den SEPA-Gläubigerdaten. */
    public function clubAccount(): DirectDebitCreditor
    {
        return $this->creditors->get();
    }

    /** Nächster Bankarbeitstag (ohne Wochenende) nach heute — Vorschlag für das Ausführungsdatum. */
    public function defaultExecutionDate(\DateTimeImmutable $now): \DateTimeImmutable
    {
        $date = $now->setTime(0, 0)->modify('+1 day');
        $weekday = (int) $date->format('N');

        return $weekday >= 6 ? $date->modify(sprintf('+%d days', 8 - $weekday)) : $date;
    }
}
