<?php

namespace App\Data\Overview\Adapter;

use App\Logic\Membership\MemberMessage\Manager\MemberMessageManagerInterface;
use App\Logic\Membership\MemberMessage\Model\MemberMessage;
use App\Logic\Membership\MemberMessage\Model\MemberMessageStatus;
use App\Logic\Overview\Model\OverviewTaskType;
use App\Logic\Overview\OverviewTaskSourceInterface;

readonly class MemberMessageTaskSource implements OverviewTaskSourceInterface
{
    public function __construct(private MemberMessageManagerInterface $messages)
    {
    }

    public function type(): OverviewTaskType
    {
        return OverviewTaskType::MemberMessages;
    }

    public function openItemDates(): array
    {
        return array_values(array_map(
            static fn (MemberMessage $message): \DateTimeImmutable => $message->submittedAt,
            array_filter(
                $this->messages->all(),
                static fn (MemberMessage $message): bool => $message->status !== MemberMessageStatus::Resolved,
            ),
        ));
    }
}
