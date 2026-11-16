<?php

namespace App\Logic\Membership\Member\Service;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\DirectDebit\Manager\DirectDebitRecordManagerInterface;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\MemberAccess\Manager\MemberAccessTokenManagerInterface;
use App\Logic\Membership\WorkAssignmentRefund\Manager\WorkAssignmentRefundRecordManagerInterface;

/**
 * Überträgt die Rolle des Zahlers (und auf Wunsch des Hauptmitglieds) von einem Mitglied auf ein
 * anderes, noch aktives Mitglied desselben Haushalts — beim Löschen eines Zahlers bzw.
 * Hauptmitglieds (`DeleteMemberUseCase`) oder als eigene Aktion (`TransferMemberPayerUseCase`),
 * z. B. wenn beide Eltern ausgetreten sind und die Kinder bleiben.
 *
 * - Der Nachfolger übernimmt Bankverbindung, SEPA-Mandat, Zahlart/-intervall/-tag und nächste
 *   Buchung des bisherigen Zahlers — außer er ist bereits Selbstzahler und behält ausdrücklich
 *   seine eigenen Zahlungsdaten (`$keepSuccessorPaymentDetails`).
 * - Alle, die bisher über den bisherigen Zahler gezahlt haben, zahlen künftig über den Nachfolger;
 *   bleibt der bisherige Zahler Mitglied, zahlt er selbst ebenfalls über den Nachfolger.
 * - Lastschrift- und Gutschrift-Historie gehen auf den Nachfolger über, damit z. B. die Warnung
 *   „für dieses Beitragsjahr schon exportiert“ weiterhin greift.
 * - Beim Hauptmitglied wird der Nachfolger neues Hauptmitglied: Alle Haushaltsmitglieder erhalten
 *   seine Mitgliedsnummer als Hauptnummer, ein verbleibendes bisheriges Hauptmitglied wird
 *   Familienangehöriger. Gutschrift-Historie und Self-Service-Zugänge des Haushalts werden an die
 *   neue Hauptnummer gebunden.
 *
 * Speichert, berechnet aber keine Beiträge neu — das übernimmt die aufrufende Stelle, nachdem ggf.
 * auch das Löschen erfolgt ist, und zwar in derselben Transaktion.
 */
readonly class PayerTransfer
{
    public function __construct(
        private MemberManagerInterface $members,
        private DirectDebitRecordManagerInterface $directDebitRecords,
        private WorkAssignmentRefundRecordManagerInterface $refundRecords,
        private MemberAccessTokenManagerInterface $accessTokens,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return Member der gespeicherte Nachfolger
     */
    public function transfer(
        Member $previous,
        string $successorId,
        bool $keepSuccessorPaymentDetails,
        bool $transferPrimaryMembership,
        bool $previousStays,
    ): Member {
        $successor = $this->validSuccessor($previous, $successorId);
        $isPayer = $previous->payerType === PayerType::SelfPayer;
        $isHead = $previous->memberNumber === $previous->primaryMemberNumber;
        if (!$isPayer && !($isHead && $transferPrimaryMembership)) {
            throw new BusinessRuleViolationException('Dieses Mitglied ist weder Zahler noch Hauptmitglied — es gibt nichts zu übertragen.');
        }
        if ($isPayer && $keepSuccessorPaymentDetails && $successor->payerType !== PayerType::SelfPayer) {
            throw new BusinessRuleViolationException(sprintf(
                '%s %s zahlt bisher nicht selbst und hat daher keine eigenen Zahlungsdaten, die beibehalten werden könnten.',
                $successor->firstName,
                $successor->lastName,
            ));
        }

        $changed = [];

        if ($isPayer) {
            $changed[$successor->id] = $keepSuccessorPaymentDetails ? $successor : $successor->takingOverPaymentFrom($previous);
            foreach ($this->members->findByPayerMemberId($previous->id) as $dependent) {
                if ($dependent->id !== $successor->id) {
                    $changed[$dependent->id] = ($changed[$dependent->id] ?? $dependent)->payingThrough($successor->id);
                }
            }
            if ($previousStays) {
                $changed[$previous->id] = $previous->payingThrough($successor->id);
            }
        }

        $movesPrimaryMembership = $isHead && $transferPrimaryMembership;
        if ($movesPrimaryMembership) {
            foreach ($this->members->findByPrimaryMemberNumber($previous->memberNumber) as $householdMember) {
                if ($householdMember->id === $previous->id && !$previousStays) {
                    continue;
                }
                $role = match ($householdMember->id) {
                    $successor->id => FamilyRole::Head,
                    $previous->id => FamilyRole::Partner,
                    default => $householdMember->familyRole,
                };
                $changed[$householdMember->id] = ($changed[$householdMember->id] ?? $householdMember)->withHousehold($successor->memberNumber, $role);
            }
        }

        // Der Nachfolger zuerst, da die übrigen per Fremdschlüssel auf ihn als Zahler verweisen.
        $savedSuccessor = $this->members->save($changed[$successor->id] ?? $successor);
        foreach ($changed as $id => $member) {
            if ($id !== $successor->id) {
                $this->members->save($member);
            }
        }

        if ($isPayer) {
            $this->directDebitRecords->reassignPayer($previous->id, $successor->id);
            $this->refundRecords->reassignPayer($previous->id, $successor->id);
        }
        if ($movesPrimaryMembership) {
            $this->refundRecords->reassignHousehold($previous->memberNumber, $successor->memberNumber);
            $this->accessTokens->reassignHousehold($previous->memberNumber, $successor->memberNumber);
        }

        return $savedSuccessor;
    }

    private function validSuccessor(Member $previous, string $successorId): Member
    {
        if ($successorId === $previous->id) {
            throw new BusinessRuleViolationException('Das Mitglied kann nicht sein eigener Nachfolger sein.');
        }
        $successor = $this->members->get($successorId);
        if ($successor->primaryMemberNumber !== $previous->primaryMemberNumber) {
            throw new BusinessRuleViolationException('Der neue Zahler muss zur selben Familie (Hauptnummer) gehören.');
        }
        if ($successor->hasLeft($this->clock->now())) {
            throw new BusinessRuleViolationException('Ein ausgetretenes Mitglied kann nicht neuer Zahler werden.');
        }

        return $successor;
    }
}
