<?php

namespace App\Data\Overview\Adapter;

use App\Logic\Event\HelpRequest\Manager\EventHelpRequestManagerInterface;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;
use App\Logic\Overview\Model\OverviewTaskType;
use App\Logic\Overview\OverviewTaskSourceInterface;

readonly class EventHelpRequestTaskSource implements OverviewTaskSourceInterface
{
    public function __construct(private EventHelpRequestManagerInterface $requests)
    {
    }

    public function type(): OverviewTaskType
    {
        return OverviewTaskType::EventHelpRequests;
    }

    public function openItemDates(): array
    {
        return array_values(array_map(
            static fn (EventHelpRequest $request): \DateTimeImmutable => $request->submittedAt,
            array_filter(
                $this->requests->all(),
                static fn (EventHelpRequest $request): bool => $request->status === EventHelpRequestStatus::New,
            ),
        ));
    }
}
