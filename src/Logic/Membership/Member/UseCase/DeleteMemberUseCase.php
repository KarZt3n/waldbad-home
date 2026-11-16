<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\MemberPayerTransferTransactionInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Service\HouseholdContributionRecalculator;
use App\Logic\Membership\Member\Service\PayerTransfer;

/**
 * Löscht ein Mitglied. Zwei Abhängigkeiten müssen dabei aufgelöst werden, da ein Löschen sonst
 * andere Mitglieder in einen ungültigen Zustand versetzen würde:
 *
 * - Zahlt dieses Mitglied für andere (`payerMemberId` verweist auf dieses Mitglied), würde die
 *   Datenbank-Fremdschlüsselregel (`ON DELETE SET NULL`) deren `payerMemberId` stillschweigend auf
 *   NULL setzen, während `payerType` weiterhin `other_member` bliebe — ein Zustand, den das
 *   `Member`-Modell beim nächsten Laden als Geschäftsregelverletzung ablehnt.
 * - Ist dieses Mitglied Hauptmitglied einer Familie (andere Mitglieder tragen seine Mitgliedsnummer
 *   als `primaryMemberNumber`), bliebe deren Hauptnummer auf ein nicht mehr existierendes Mitglied
 *   verweisen.
 *
 * In beiden Fällen ist ein Nachfolger aus derselben Familie anzugeben (`$successorMemberId`): Er
 * übernimmt Zahler-Rolle mit Bankverbindung und SEPA-Mandat sowie ggf. die Hauptnummer (siehe
 * `PayerTransfer`). Übertragung, Löschen und die Neuberechnung der Beiträge des Haushalts erfolgen
 * gemeinsam — entweder alles oder nichts.
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

    public function execute(string $id, ?string $successorMemberId = null, bool $keepSuccessorPaymentDetails = false): void
    {
        $member = $this->manager->get($id);

        $payerDependents = $this->manager->findByPayerMemberId($id);
        $householdDependents = array_values(array_filter(
            $this->manager->findByPrimaryMemberNumber($member->memberNumber),
            static fn (Member $other): bool => $other->id !== $id,
        ));
        if ($payerDependents === [] && $householdDependents === []) {
            $this->manager->delete($id);

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

        $this->transaction->execute(function () use ($member, $id, $successorMemberId, $keepSuccessorPaymentDetails): void {
            $successor = $this->payerTransfer->transfer(
                $member,
                $successorMemberId,
                $keepSuccessorPaymentDetails,
                transferPrimaryMembership: true,
                previousStays: false,
            );
            $this->manager->delete($id);
            $this->recalculator->recalculate($successor);
        });
    }
}
