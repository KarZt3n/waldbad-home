<?php

namespace App\Data\Overview\Adapter;

use App\Logic\Membership\Cancellation\Manager\MemberCancellationManagerInterface;
use App\Logic\Membership\Cancellation\Model\MemberCancellation;
use App\Logic\Overview\Model\OverviewTaskType;
use App\Logic\Overview\OverviewTaskSourceInterface;

readonly class MemberCancellationTaskSource implements OverviewTaskSourceInterface
{
    public function __construct(private MemberCancellationManagerInterface $cancellations)
    {
    }

    public function type(): OverviewTaskType
    {
        return OverviewTaskType::MemberCancellations;
    }

    public function openItemDates(): array
    {
        return array_values(array_map(
            static fn (MemberCancellation $cancellation): \DateTimeImmutable => $cancellation->submittedAt,
            $this->cancellations->openByMemberId(),
        ));
    }
}
