<?php

namespace App\Data\Overview\Adapter;

use App\Logic\Contact\Request\Manager\ContactRequestManagerInterface;
use App\Logic\Contact\Request\Model\ContactRequest;
use App\Logic\Contact\Request\Model\ContactRequestStatus;
use App\Logic\Overview\Model\OverviewTaskType;
use App\Logic\Overview\OverviewTaskSourceInterface;

readonly class ContactRequestTaskSource implements OverviewTaskSourceInterface
{
    public function __construct(private ContactRequestManagerInterface $requests)
    {
    }

    public function type(): OverviewTaskType
    {
        return OverviewTaskType::ContactRequests;
    }

    public function openItemDates(): array
    {
        return array_values(array_map(
            static fn (ContactRequest $request): \DateTimeImmutable => $request->submittedAt,
            array_filter(
                $this->requests->all(),
                static fn (ContactRequest $request): bool => $request->status === ContactRequestStatus::New,
            ),
        ));
    }
}
