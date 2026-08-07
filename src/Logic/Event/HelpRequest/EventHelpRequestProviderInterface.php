<?php

namespace App\Logic\Event\HelpRequest;

use App\Logic\Event\HelpRequest\Model\EventHelpRequest;

interface EventHelpRequestProviderInterface
{
    public function find(string $id): ?EventHelpRequest;

    /**
     * @return list<EventHelpRequest>
     */
    public function findAll(): array;
}
