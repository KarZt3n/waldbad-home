<?php

namespace App\Logic\Event\Schedule\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class EventScheduleNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Die Veranstaltung "%s" wurde nicht gefunden.', $id));
    }
}
