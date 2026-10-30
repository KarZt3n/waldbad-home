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
 * Stellt die Gutschrift für geleistete Arbeitsstunden je Haushalt (Hauptmitgliedsnummer) und
 * Beitragsjahr zusammen — dieselbe Berechnung wie in „Meine Mitgliedschaft“ und bei den
 * Arbeitseinsätzen des Mitglieds (`WorkAssignmentCreditCalculator`): Stunden sind innerhalb der
 * Familie übertragbar, die Gutschrift ist auf den Arbeitseinsatz-Zuschlag des Haushalts gedeckelt.
 *
 * Überwiesen wird an den Zahler, der den Zuschlag trägt: Selbstzahler sich selbst, sonst das als
 * Zahler eingetragene Mitglied. Tragen mehrere Zahler Zuschläge eines Haushalts, ist der Empfänger
 * nicht eindeutig und der Haushalt wird nicht überwiesen. Die Zahlart spielt keine Rolle — es
 * genügt eine echte Bankverbindung.
 *
 * Bereits überwiesene Beträge des Jahres (Gutschrift-Historie) werden abgezogen. Haushalte ohne
 * geleistete Stunden und ohne frühere Überweisung tauchen nicht auf.
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
        $households = [];
        foreach ($this->members->search(null) as $member) {
            $membersById[$member->id] = $member;
            $households[$member->primaryMemberNumber][] = $member;
        }
        $refundedByHousehold = [];
        foreach ($this->records->findByYear($year) as $record) {
            $refundedByHousehold[$record->primaryMemberNumber] = ($refundedByHousehold[$record->primaryMemberNumber] ?? 0) + $record->amountCents;
        }
        $periodFrom = $this->periodFrom($year);
        $credits = $this->calculator->calculateForHouseholds($households, $periodFrom, $periodFrom->modify('+1 year'));
        $club = $this->clubAccount();

        $drafts = [];
        foreach ($households as $primaryMemberNumber => $householdMembers) {
            $primaryMemberNumber = (string) $primaryMemberNumber;
            $credit = $credits[$primaryMemberNumber];
            $refunded = $refundedByHousehold[$primaryMemberNumber] ?? 0;
            if ($credit->workedMinutes <= 0 && $refunded <= 0) {
                continue;
            }
            $drafts[] = $this->planHousehold(
                $primaryMemberNumber,
                $householdMembers,
                $year,
                $credit,
                $refunded,
                $club,
                static fn (string $id): ?Member => $membersById[$id] ?? null,
            );
        }
        usort($drafts, static fn (WorkAssignmentRefundDraft $left, WorkAssignmentRefundDraft $right): int => strnatcmp($left->primaryMemberNumber, $right->primaryMemberNumber));

        return $drafts;
    }

    /**
     * Dieselbe Planung wie `planAll()` für einen einzelnen Haushalt — für die Detailansicht. Liefert
     * auch Haushalte ohne geleistete Stunden.
     */
    public function plan(string $primaryMemberNumber, int $year, \DateTimeImmutable $now): WorkAssignmentRefundDraft
    {
        $this->assertSelectableYear($year, $now);
        $householdMembers = $this->members->findByPrimaryMemberNumber($primaryMemberNumber);
        if ($householdMembers === []) {
            throw new BusinessRuleViolationException('Zu dieser Hauptmitgliedsnummer gibt es keinen Haushalt.');
        }
        $refunded = 0;
        foreach ($this->records->findByYear($year) as $record) {
            if ($record->primaryMemberNumber === $primaryMemberNumber) {
                $refunded += $record->amountCents;
            }
        }
        $periodFrom = $this->periodFrom($year);
        $householdById = [];
        foreach ($householdMembers as $member) {
            $householdById[$member->id] = $member;
        }

        return $this->planHousehold(
            $primaryMemberNumber,
            $householdMembers,
            $year,
            $this->calculator->calculateForPeriod($householdMembers, $periodFrom, $periodFrom->modify('+1 year')),
            $refunded,
            $this->clubAccount(),
            // Der Zahler kann außerhalb des Haushalts stehen (anderer Hauptmitgliedsnummer zugeordnet).
            fn (string $id): ?Member => $householdById[$id] ?? $this->findMember($id),
        );
    }

    /**
     * Empfänger ist der Zahler, der die Arbeitseinsatz-Zuschläge des Haushalts trägt.
     *
     * @param non-empty-list<Member>   $householdMembers
     * @param callable(string): ?Member $memberById
     */
    private function planHousehold(string $primaryMemberNumber, array $householdMembers, int $year, WorkAssignmentCreditResponse $credit, int $refunded, DirectDebitCreditor $club, callable $memberById): WorkAssignmentRefundDraft
    {
        $obstacles = [];
        $recipient = null;
        $payers = [];
        $missingPayer = false;
        foreach ($householdMembers as $member) {
            if ($member->workAssignmentSurchargeCents === null) {
                continue;
            }
            $payer = $member->payerType === PayerType::SelfPayer ? $member : ($member->payerMemberId === null ? null : $memberById($member->payerMemberId));
            if ($payer === null) {
                $missingPayer = true;
                continue;
            }
            $payers[$payer->id] = $payer;
        }
        if ($missingPayer) {
            $obstacles[] = new WorkAssignmentRefundObstacle(WorkAssignmentRefundObstacleKind::MissingPayer, 'Für ein Mitglied mit Arbeitseinsatz-Zuschlag ist kein zahlendes Mitglied hinterlegt.');
        }
        if (count($payers) > 1) {
            $obstacles[] = new WorkAssignmentRefundObstacle(WorkAssignmentRefundObstacleKind::MultiplePayers, 'Die Arbeitseinsatz-Zuschläge des Haushalts tragen mehrere Zahler – der Empfänger ist nicht eindeutig.');
        } elseif (count($payers) === 1) {
            $recipient = $payers[array_key_first($payers)];
            if (Iban::isMissingOrPlaceholder($recipient->iban)) {
                $obstacles[] = new WorkAssignmentRefundObstacle(WorkAssignmentRefundObstacleKind::MissingBankAccount, 'Für den Zahler ist keine echte Bankverbindung hinterlegt.');
            }
        }
        if ($club->name === null || $club->iban === null) {
            $obstacles[] = new WorkAssignmentRefundObstacle(WorkAssignmentRefundObstacleKind::IncompleteClubAccount, 'Name und IBAN des Vereinskontos fehlen (Mitgliederverwaltung → Beitragssätze → SEPA-Gläubigerdaten).');
        }

        $contact = $recipient ?? $this->primaryMember($householdMembers, $primaryMemberNumber);

        return new WorkAssignmentRefundDraft(
            primaryMemberNumber: $primaryMemberNumber,
            year: $year,
            householdMembers: $householdMembers,
            recipient: $recipient,
            contact: $contact,
            recipientName: $recipient->accountHolder ?? $contact->firstName.' '.$contact->lastName,
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

    /**
     * @param non-empty-list<Member> $householdMembers
     */
    private function primaryMember(array $householdMembers, string $primaryMemberNumber): Member
    {
        foreach ($householdMembers as $member) {
            if ($member->memberNumber === $primaryMemberNumber) {
                return $member;
            }
        }

        return $householdMembers[0];
    }
}
