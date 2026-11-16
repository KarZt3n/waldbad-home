<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;

/**
 * Löscht die ganze Familie eines Mitglieds (alle Mitglieder mit derselben Hauptnummer) per
 * Soft-Delete in einem Schreibvorgang. Anders als beim Löschen eines einzelnen Mitglieds braucht es
 * keinen Nachfolger, da niemand aus der Familie übrig bleibt. Abgelehnt wird nur, wenn ein Mitglied
 * außerhalb der Familie noch über ein Familienmitglied zahlt oder dessen Mitgliedsnummer als
 * Hauptnummer führt — es würde sonst auf ein gelöschtes Mitglied verweisen.
 */
readonly class DeleteHouseholdUseCase
{
    public function __construct(private MemberManagerInterface $manager)
    {
    }

    /** @return int Anzahl der gelöschten Mitglieder */
    public function execute(string $id): int
    {
        $member = $this->manager->get($id);
        $household = $this->manager->findByPrimaryMemberNumber($member->primaryMemberNumber);
        $householdIds = array_map(static fn (Member $entry): string => $entry->id, $household);
        if (!in_array($id, $householdIds, true)) {
            $householdIds[] = $id;
            $household[] = $member;
        }

        foreach ($household as $entry) {
            $dependents = [
                ...$this->manager->findByPayerMemberId($entry->id),
                ...$this->manager->findByPrimaryMemberNumber($entry->memberNumber),
            ];
            foreach ($dependents as $dependent) {
                if (!in_array($dependent->id, $householdIds, true)) {
                    throw new BusinessRuleViolationException(sprintf(
                        'Die Familie kann nicht gelöscht werden: %s %s (%s) gehört nicht zur Familie, ist aber über %s %s (%s) zugeordnet. Bitte zuerst diese Zuordnung ändern.',
                        $dependent->firstName,
                        $dependent->lastName,
                        $dependent->memberNumber,
                        $entry->firstName,
                        $entry->lastName,
                        $entry->memberNumber,
                    ));
                }
            }
        }

        $this->manager->deleteAll($householdIds);

        return count($householdIds);
    }
}
