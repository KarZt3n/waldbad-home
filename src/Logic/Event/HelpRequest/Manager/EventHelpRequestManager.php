<?php

namespace App\Logic\Event\HelpRequest\Manager;

use App\Logic\Event\HelpRequest\EventHelpRequestProcessorInterface;
use App\Logic\Event\HelpRequest\EventHelpRequestProviderInterface;
use App\Logic\Event\HelpRequest\Exception\EventHelpRequestNotFoundException;
use App\Logic\Event\HelpRequest\Model\EventHelpRequest;

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

    public function save(EventHelpRequest $request): EventHelpRequest
    {
        return $this->processor->save($request);
    }
}
