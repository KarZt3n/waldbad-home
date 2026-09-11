<?php

namespace App\Logic\Event\HelpRequest\Manager;

use App\Logic\Event\HelpRequest\EventHelpRequestProcessorInterface;
use App\Logic\Event\HelpRequest\EventHelpRequestProviderInterface;
use App\Logic\Event\HelpRequest\Exception\EventHelpRequestNotFoundException;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;
use App\Logic\Event\HelpRequest\Model\EventHelpRequestStatus;

readonly class EventHelpRequestManager implements EventHelpRequestManagerInterface
{
    public function __construct(
        private EventHelpRequestProviderInterface $provider,
        private EventHelpRequestProcessorInterface $processor,
    ) {
    }

    public function get(string $id): EventHelpRequest
    {
        return $this->provider->find($id) ?? throw new EventHelpRequestNotFoundException($id);
    }

    public function all(): array
    {
        return $this->provider->findAll();
    }

    public function findParticipatedForMembersInPeriod(array $memberIds, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        if ($memberIds === []) {
            return [];
        }
        $fromDate = $from->format('Y-m-d');
        $toDate = $to->format('Y-m-d');

        return array_values(array_filter(
            $this->all(),
            static fn (EventHelpRequest $request): bool => $request->status === EventHelpRequestStatus::Participated
                && $request->memberId !== null
                && in_array($request->memberId, $memberIds, true)
                && $request->eventDate >= $fromDate
                && $request->eventDate < $toDate,
        ));
    }

    public function save(EventHelpRequest $request): EventHelpRequest
    {
        return $this->processor->save($request);
    }

    public function delete(string $id): void
    {
        $this->processor->delete($id);
    }
}
