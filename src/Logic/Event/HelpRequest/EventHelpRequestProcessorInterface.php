<?php

namespace App\Logic\Event\HelpRequest;

use App\Logic\Event\HelpRequest\Model\EventHelpRequest;

interface EventHelpRequestProcessorInterface
{
    public function save(EventHelpRequest $request): EventHelpRequest;
}
