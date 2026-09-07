<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Membership\Member\Dto\MemberResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;
use App\Logic\Membership\Member\Service\MemberContributionCalculator;

/**
 * Berechnet den Beitrag nicht nur für das angefragte Mitglied neu, sondern für den gesamten
 * Haushalt (alle Mitglieder mit derselben Hauptnummer): Ob der Familienrabatt greift, hängt vom
 * gesamten Haushalt ab (ein qualifizierendes Kind, dessen Geburtsreihenfolge unter den
 * Geschwistern), sodass die Neuberechnung eines einzelnen Mitglieds — z. B. weil ein Kind
 * inzwischen aus der Kinderpreisung herausgewachsen ist — auch die Berechnung der übrigen
 * Haushaltsmitglieder verändern kann. Jedes Haushaltsmitglied wird deshalb anhand desselben,
 * einmal geladenen Haushalts-Snapshots neu berechnet und gespeichert.
 */
readonly class RecalculateMemberContributionUseCase
{
    public function __construct(
        private MemberManagerInterface $manager,
        private MemberContributionCalculator $calculator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $memberId): MemberResponse
    {
        $member = $this->manager->get($memberId);
        $household = $this->manager->findByPrimaryMemberNumber($member->primaryMemberNumber);
        $now = $this->clock->now();

        $requested = null;
        foreach ($household as $candidate) {
            $otherHouseholdMembers = array_values(array_filter(
                $household,
                static fn (Member $other): bool => $other->id !== $candidate->id,
            ));
            $outcome = $this->calculator->calculate($candidate, $otherHouseholdMembers, $now);
            $saved = $this->manager->save($candidate->withContribution($outcome->category, $outcome->amountCents, $outcome->workAssignmentSurchargeCents));
            if ($saved->id === $memberId) {
                $requested = $saved;
            }
        }

        // $household enthält $member immer selbst (die eigene Hauptnummer verweist im
        // Selbst-/Kopf-Fall auf sich selbst), daher ist $requested an dieser Stelle stets gesetzt.
        return MemberResponse::fromMember($requested ?? $this->manager->get($memberId));
    }
}
