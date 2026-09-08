<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;

/**
 * Löscht ein Mitglied. Zwei Abhängigkeiten werden vorher geprüft, da ein Löschen sonst andere
 * Mitglieder in einen ungültigen Zustand versetzen würde:
 *
 * - Zahlt dieses Mitglied für andere (`payerMemberId` verweist auf dieses Mitglied), würde die
 *   Datenbank-Fremdschlüsselregel (`ON DELETE SET NULL`) deren `payerMemberId` stillschweigend auf
 *   NULL setzen, während `payerType` weiterhin `other_member` bliebe — ein Zustand, den das
 *   `Member`-Modell beim nächsten Laden als Geschäftsregelverletzung ablehnt und der den
 *   betroffenen Datensatz damit unlesbar machen würde.
 * - Ist dieses Mitglied Hauptmitglied einer Familie (andere Mitglieder tragen seine Mitgliedsnummer
 *   als `primaryMemberNumber`), bliebe deren Hauptnummer nach dem Löschen auf ein nicht mehr
 *   existierendes Mitglied verweisen.
 *
 * Beide Fälle müssen vor dem Löschen aufgelöst werden (Zahler bzw. Hauptmitglied bei den
 * betroffenen Mitgliedern ändern).
 */
readonly class DeleteMemberUseCase
{
    public function __construct(private MemberManagerInterface $manager)
    {
    }

    public function execute(string $id): void
    {
        $member = $this->manager->get($id);

        $payerDependents = $this->manager->findByPayerMemberId($id);
        if ($payerDependents !== []) {
            throw new BusinessRuleViolationException(sprintf(
                'Das Mitglied kann nicht gelöscht werden: %d weitere(s) Mitglied(er) zahlen über dieses Mitglied. '
                .'Bitte zuerst bei diesen Mitgliedern einen anderen Zahler eintragen.',
                count($payerDependents),
            ));
        }

        $householdDependents = array_values(array_filter(
            $this->manager->findByPrimaryMemberNumber($member->memberNumber),
            static fn (Member $other): bool => $other->id !== $id,
        ));
        if ($householdDependents !== []) {
            throw new BusinessRuleViolationException(
                'Das Mitglied kann nicht gelöscht werden: Andere Mitglieder führen es als Hauptnummer. '
                .'Bitte zuerst bei diesen Mitgliedern eine andere Hauptnummer eintragen.',
            );
        }

        $this->manager->delete($id);
    }
}
