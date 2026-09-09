<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Membership\Member\Dto\MemberResponse;
use App\Logic\Membership\Member\Dto\UpdateMemberRequest;
use App\Logic\Membership\Member\Mapping\MemberModelFactory;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Service\HouseholdContributionRecalculator;

readonly class UpdateMemberUseCase
{
    public function __construct(
        private MemberManagerInterface $manager,
        private MemberModelFactory $factory,
        private HouseholdContributionRecalculator $recalculator,
    ) {
    }

    public function execute(UpdateMemberRequest $request): MemberResponse
    {
        $current = $this->manager->get($request->id);
        $member = $this->factory->rebuildFromRequest($request, $current);

        // Vorstandsmitglieder sind laut Satzung beitragsfrei (siehe Member::$contributionLiable):
        // Wird die Funktion beim Bearbeiten auf „Vorstand" umgestellt, wird „Beitragspflichtig"
        // automatisch deaktiviert; wird sie von „Vorstand" auf irgendetwas anderes umgestellt, wird
        // sie automatisch wieder aktiviert — jeweils unabhängig davon, was im Request sonst für
        // dieses Feld stand (die Oberfläche stellt den Haken zwar bereits passend um, siehe
        // `assets/app.js`, aber maßgeblich ist dieser serverseitige Automatismus). Nur bei einem
        // tatsächlichen Wechsel über die Vorstands-Grenze wird anschließend der Beitrag für das
        // Mitglied und seinen ganzen Haushalt neu berechnet, da sich sonst an der Berechnung nichts
        // ändert (siehe `HouseholdContributionRecalculator`).
        $becameBoardMember = $current->function !== MemberFunction::Board && $member->function === MemberFunction::Board;
        $leftBoard = $current->function === MemberFunction::Board && $member->function !== MemberFunction::Board;
        if ($becameBoardMember) {
            $member = $member->withContributionLiable(false);
        } elseif ($leftBoard) {
            $member = $member->withContributionLiable(true);
        }

        $saved = $this->manager->save($member);
        if ($becameBoardMember || $leftBoard) {
            $saved = $this->recalculator->recalculate($saved);
        }

        return MemberResponse::fromMember($saved);
    }
}
