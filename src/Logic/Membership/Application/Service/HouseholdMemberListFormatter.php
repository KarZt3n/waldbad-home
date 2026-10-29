<?php

namespace App\Logic\Membership\Application\Service;

use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\Member\Model\Member;

/**
 * Personenliste des Haushalts für die Bestätigungsmails (Freigabe und Übernahme eines Antrags).
 */
readonly class HouseholdMemberListFormatter
{
    /**
     * Je Person zusätzlich das Beitragsalter (Stichtag 31.12., siehe `Member::contributionAge()`),
     * damit nachvollziehbar ist, warum z. B. ein erst im Dezember 21-Jähriger schon den
     * Erwachsenenbeitrag zahlt.
     *
     * @param list<Member> $members
     */
    public function format(array $members, int $contributionYear): string
    {
        return implode("\n", array_map(
            static fn (Member $member): string => sprintf(
                '- %s %s (%s), geb. %s (Beitragsalter %d: %d)',
                $member->firstName,
                $member->lastName,
                match ($member->familyRole) {
                    FamilyRole::None => 'Einzelperson',
                    FamilyRole::Head => 'Hauptmitglied',
                    FamilyRole::Partner => 'Familienangehöriger',
                    FamilyRole::Child => 'Kind',
                },
                $member->birthDate->format('d.m.Y'),
                $contributionYear,
                $member->contributionAge($contributionYear),
            ),
            $members,
        ));
    }
}
