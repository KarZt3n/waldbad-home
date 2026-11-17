<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Dto\MemberResponse;
use App\Logic\Membership\Member\Dto\UpdateMemberRequest;
use App\Logic\Membership\Member\Dto\UpdateMemberResponse;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Mapping\MemberModelFactory;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\MemberFunction;
use App\Logic\Membership\Member\Service\HouseholdContributionRecalculator;

/**
 * Speichert die Änderungen an einem Mitglied und berechnet danach immer die Beiträge der ganzen
 * Familie neu (siehe `HouseholdContributionRecalculator`) — Geburtsdatum, Familienrolle, Austritt,
 * Zahler oder Hauptnummer können die Beiträge aller Haushaltsmitglieder verändern. Wechselt das
 * Mitglied die Familie, wird auch die bisherige Familie neu berechnet.
 *
 * Die Neuberechnung erfolgt nachgelagert: Scheitert sie (z. B. fehlender Beitragssatz), bleiben die
 * Änderungen gespeichert und der Grund wird als Hinweis zurückgegeben.
 */
readonly class UpdateMemberUseCase
{
    public function __construct(
        private MemberManagerInterface $manager,
        private MemberModelFactory $factory,
        private HouseholdContributionRecalculator $recalculator,
    ) {
    }

    public function execute(UpdateMemberRequest $request): UpdateMemberResponse
    {
        $current = $this->manager->get($request->id);
        $member = $this->factory->rebuildFromRequest($request, $current);

        // Vorstandsmitglieder sind laut Satzung beitragsfrei (siehe Member::$contributionLiable):
        // Wird die Funktion beim Bearbeiten auf „Vorstand" umgestellt, wird „Beitragspflichtig"
        // automatisch deaktiviert; wird sie von „Vorstand" auf irgendetwas anderes umgestellt, wird
        // sie automatisch wieder aktiviert — jeweils unabhängig davon, was im Request sonst für
        // dieses Feld stand (die Oberfläche stellt den Haken zwar bereits passend um, aber
        // maßgeblich ist dieser serverseitige Automatismus).
        $becameBoardMember = $current->function !== MemberFunction::Board && $member->function === MemberFunction::Board;
        $leftBoard = $current->function === MemberFunction::Board && $member->function !== MemberFunction::Board;
        if ($becameBoardMember) {
            $member = $member->withContributionLiable(false);
        } elseif ($leftBoard) {
            $member = $member->withContributionLiable(true);
        }

        $saved = $this->manager->save($member);

        $warnings = [];
        try {
            $saved = $this->recalculator->recalculate($saved);
        } catch (BusinessRuleViolationException $exception) {
            $warnings[] = $exception->getMessage();
        }
        if ($current->primaryMemberNumber !== $saved->primaryMemberNumber) {
            $previousHousehold = $this->manager->findByPrimaryMemberNumber($current->primaryMemberNumber);
            $remaining = array_values(array_filter($previousHousehold, static fn (Member $other): bool => $other->id !== $saved->id));
            if ($remaining !== []) {
                try {
                    $this->recalculator->recalculate($remaining[0]);
                } catch (BusinessRuleViolationException $exception) {
                    $warnings[] = 'Bisherige Familie: '.$exception->getMessage();
                }
            }
        }

        return new UpdateMemberResponse(
            MemberResponse::fromMember($saved),
            $warnings === [] ? null : 'Gespeichert, aber die Beiträge der Familie konnten nicht neu berechnet werden: '.implode(' ', $warnings),
        );
    }
}
