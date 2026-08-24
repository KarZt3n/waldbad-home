<?php

namespace App\Logic\Event\HelpRequest;

use App\Logic\Event\HelpRequest\Model\VolunteerEvent;

interface VolunteerEventProviderInterface
{
    public function findPublished(string $eventIdentifier): ?VolunteerEvent;
}
