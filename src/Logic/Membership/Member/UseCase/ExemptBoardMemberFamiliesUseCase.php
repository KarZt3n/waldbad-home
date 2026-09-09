<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Membership\Member\Dto\ExemptBoardFamiliesResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Model\MemberFunction;

/**
 * Prüft jedes Mitglied auf seine Funktion (`Member::$function`): Gehört zu einem Haushalt
 * (= gleiche Hauptnummer) mindestens ein Vorstandsmitglied, entfällt für **alle** Mitglieder
 * dieses Haushalts — also auch das Vorstandsmitglied selbst und dessen Familienangehörige —
 * die Beitragspflicht (`Member::$contributionLiable`); siehe dazu auch den Hinweistext in
 * `assets/app.js` (Unterscheidung „Vorstand“ vs. „Nicht beitragspflichtig“ in der Mitgliederliste),
 * der genau diese Konstellation bereits vorsieht.
 *
 * Bereits als nicht beitragspflichtig markierte Mitglieder werden nicht erneut gespeichert.
 * Ohne `$execute` wird nur ermittelt, was sich ändern würde (Prüflauf, keine Schreibzugriffe).
 */
readonly class ExemptBoardMemberFamiliesUseCase
{
    public function __construct(private MemberManagerInterface $manager)
    {
    }

    public function execute(bool $execute): ExemptBoardFamiliesResponse
    {
        /** @var array<string, list<Member>> $households */
        $households = [];
        foreach ($this->manager->search(null) as $member) {
            $households[$member->primaryMemberNumber][] = $member;
        }

        $householdsAffected = 0;
        $updatedMemberNumbers = [];
        foreach ($households as $household) {
            $hasBoardMember = false;
            foreach ($household as $member) {
                if ($member->function === MemberFunction::Board) {
                    $hasBoardMember = true;
                    break;
                }
            }
            if (!$hasBoardMember) {
                continue;
            }

            $changedInHousehold = false;
            foreach ($household as $member) {
                if (!$member->contributionLiable) {
                    continue;
                }
                if ($execute) {
                    $this->manager->save($member->withContributionLiable(false));
                }
                $updatedMemberNumbers[] = $member->memberNumber;
                $changedInHousehold = true;
            }
            if ($changedInHousehold) {
                ++$householdsAffected;
            }
        }

        return new ExemptBoardFamiliesResponse($householdsAffected, $updatedMemberNumbers);
    }
}
