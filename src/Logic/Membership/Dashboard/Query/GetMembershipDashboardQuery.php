<?php

namespace App\Logic\Membership\Dashboard\Query;

use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Application\Model\ApplicationStatus;
use App\Logic\Membership\Dashboard\Dto\MembershipDashboardResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\Member;

/**
 * Bewusst schlanker Platzhalter für das Mitgliederverwaltungs-Dashboard — liefert nur die
 * wichtigsten Kennzahlen. Wird schrittweise um weitere Auswertungen ergänzt.
 */
readonly class GetMembershipDashboardQuery
{
    public function __construct(
        private MemberManagerInterface $members,
        private MembershipApplicationManagerInterface $applications,
    ) {
    }

    public function execute(): MembershipDashboardResponse
    {
        $members = $this->members->search(null);

        return new MembershipDashboardResponse(
            totalMembers: count($members),
            activeMembers: count(array_filter($members, static fn (Member $member): bool => $member->active)),
            pendingApplications: count($this->applications->list(ApplicationStatus::Pending)),
        );
    }
}
