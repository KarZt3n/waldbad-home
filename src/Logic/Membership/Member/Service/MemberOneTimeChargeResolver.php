<?php

namespace App\Logic\Membership\Member\Service;

use App\Logic\Membership\ContributionRate\Manager\ContributionRateManagerInterface;
use App\Logic\Membership\ContributionRate\Model\ContributionRate;
use App\Logic\Membership\ContributionRate\Model\PersonGroup;
use App\Logic\Membership\Member\Model\FamilyRole;
use App\Logic\Membership\PaymentInterval;

/**
 * Ermittelt, welche einmaligen Beitragssätze (Zeitraum `Once`, z. B. eine Beitrittsgebühr) bei
 * Neuanlage eines Mitglieds berechnet werden. Bei einer Familienmitgliedschaft zahlt nur das
 * Hauptmitglied die Familien-Gebühr einmal pro neuer Mitgliedschaft — Partner und Kinder, die
 * einer bestehenden oder neuen Familie hinzugefügt werden, lösen keine weitere Gebühr aus.
 */
readonly class MemberOneTimeChargeResolver
{
    public function __construct(private ContributionRateManagerInterface $rates)
    {
    }

    /**
     * @return list<ContributionRate>
     */
    public function resolveForNewMember(FamilyRole $familyRole): array
    {
        $personGroup = match ($familyRole) {
            FamilyRole::None => PersonGroup::Individual,
            FamilyRole::Head => PersonGroup::Family,
            FamilyRole::Partner, FamilyRole::Child => null,
        };
        if ($personGroup === null) {
            return [];
        }

        return array_values(array_filter(
            $this->rates->list(),
            static fn (ContributionRate $rate): bool => $rate->period === PaymentInterval::Once && $rate->personGroup === $personGroup,
        ));
    }
}
