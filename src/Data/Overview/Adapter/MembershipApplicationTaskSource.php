<?php

namespace App\Data\Overview\Adapter;

use App\Logic\Membership\Application\Manager\MembershipApplicationManagerInterface;
use App\Logic\Membership\Application\Model\MembershipApplication;
use App\Logic\Overview\Model\OverviewTaskType;
use App\Logic\Overview\OverviewTaskSourceInterface;

/** Anträge, über die noch nicht entschieden wurde (weder als Mitglied angelegt noch abgelehnt). */
readonly class MembershipApplicationTaskSource implements OverviewTaskSourceInterface
{
    public function __construct(private MembershipApplicationManagerInterface $applications)
    {
    }

    public function type(): OverviewTaskType
    {
        return OverviewTaskType::MembershipApplications;
    }

    public function openItemDates(): array
    {
        return array_values(array_map(
            static fn (MembershipApplication $application): \DateTimeImmutable => $application->submittedAt,
            array_filter(
                $this->applications->list(),
                static fn (MembershipApplication $application): bool => $application->releasedAt === null && $application->rejectedAt === null,
            ),
        ));
    }
}
