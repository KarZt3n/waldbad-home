<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\MemberPayerTransferTransactionInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Service\HouseholdContributionRecalculator;
use App\Logic\Membership\Member\Service\PayerTransfer;

/**
 * Löscht ein Mitglied — als Soft-Delete: Der Datensatz bleibt in der Datenbank erhalten und wird nur
 * als gelöscht markiert; danach liefert die Anwendung ihn nirgends mehr (siehe
 * `MemberManagerInterface::delete()`). Zwei Abhängigkeiten müssen vorher aufgelöst werden, da andere
 * Mitglieder sonst auf ein nicht mehr sichtbares Mitglied verweisen würden:
 *
 * - Zahlt dieses Mitglied für andere (`payerMemberId` verweist auf dieses Mitglied), fänden deren
 *   Lastschriften keinen Zahler mehr.
 * - Ist dieses Mitglied Hauptmitglied einer Familie (andere Mitglieder tragen seine Mitgliedsnummer
 *   als `primaryMemberNumber`), bliebe deren Hauptnummer auf ein gelöschtes Mitglied verweisen.
 *
 * In beiden Fällen ist ein Nachfolger aus derselben Familie anzugeben (`$successorMemberId`): Er
 * übernimmt Zahler-Rolle mit Bankverbindung und SEPA-Mandat sowie ggf. die Hauptnummer (siehe
 * `PayerTransfer`).
 *
 * Kontaktdaten (E-Mail, Telefon) gehen an `$contactRecipientMemberId` aus derselben Familie über —
 * beim Hauptmitglied ohne ausdrückliche Angabe an den Nachfolger, damit die Familie erreichbar
 * bleibt. Übernommen wird nur in leere Felder (siehe `Member::takingOverContactFrom()`).
 *
 * Übertragungen, Löschen und die Neuberechnung der Beiträge des Haushalts erfolgen gemeinsam —
 * entweder alles oder nichts.
 */
readonly class DeleteMemberUseCase
{
    public function __construct(
        private MemberManagerInterface $manager,
        private PayerTransfer $payerTransfer,
        private HouseholdContributionRecalculator $recalculator,
        private MemberPayerTransferTransactionInterface $transaction,
    ) {
    }

    public function execute(
        string $id,
        ?string $successorMemberId = null,
        bool $keepSuccessorPaymentDetails = false,
        ?string $contactRecipientMemberId = null,
    ): void {
        $member = $this->manager->get($id);

        $payerDependents = $this->manager->findByPayerMemberId($id);
        $householdDependents = array_values(array_filter(
            $this->manager->findByPrimaryMemberNumber($member->memberNumber),
            static fn (Member $other): bool => $other->id !== $id,
        ));
        $needsSuccessor = $payerDependents !== [] || $householdDependents !== [];
        $contactRecipientMemberId ??= $householdDependents !== [] ? $successorMemberId : null;
        if ($contactRecipientMemberId !== null) {
            $this->assertContactRecipient($member, $contactRecipientMemberId);
        }

        if (!$needsSuccessor) {
            $this->transaction->execute(function () use ($member, $id, $contactRecipientMemberId): void {
                $this->transferContact($member, $contactRecipientMemberId);
                $this->manager->delete($id);
            });

            return;
        }

        if ($successorMemberId === null) {
            throw new BusinessRuleViolationException($payerDependents !== []
                ? sprintf(
                    'Das Mitglied kann nicht gelöscht werden: %d weitere(s) Mitglied(er) zahlen über dieses Mitglied. '
                    .'Bitte einen neuen Zahler aus der Familie wählen.',
                    count($payerDependents),
                )
                : 'Das Mitglied kann nicht gelöscht werden: Andere Mitglieder führen es als Hauptnummer. '
                    .'Bitte ein neues Hauptmitglied aus der Familie wählen.');
        }

        $this->transaction->execute(function () use ($member, $id, $successorMemberId, $keepSuccessorPaymentDetails, $contactRecipientMemberId): void {
            $successor = $this->payerTransfer->transfer(
                $member,
                $successorMemberId,
                $keepSuccessorPaymentDetails,
                transferPrimaryMembership: true,
                previousStays: false,
            );
            $this->transferContact($member, $contactRecipientMemberId);
            $this->manager->delete($id);
            $this->recalculator->recalculate($successor);
        });
    }

    private function assertContactRecipient(Member $member, string $recipientId): void
    {
        if ($recipientId === $member->id) {
            throw new BusinessRuleViolationException('Die Kontaktdaten können nicht an das zu löschende Mitglied selbst übertragen werden.');
        }
        if ($this->manager->get($recipientId)->primaryMemberNumber !== $member->primaryMemberNumber) {
            throw new BusinessRuleViolationException('Die Kontaktdaten können nur an ein Mitglied derselben Familie übertragen werden.');
        }
    }

    private function transferContact(Member $member, ?string $recipientId): void
    {
        if ($recipientId === null || !$member->hasContactInfo()) {
            return;
        }
        // Frisch laden: Eine vorherige Zahler-/Hauptmitglied-Übertragung hat den Empfänger ggf. schon gespeichert.
        $recipient = $this->manager->get($recipientId);
        $updated = $recipient->takingOverContactFrom($member);
        if ($updated !== $recipient) {
            $this->manager->save($updated);
        }
    }
}
