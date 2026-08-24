<?php

namespace App\Logic\Event\HelpRequest\Model;

readonly class SelectedEventActivity
{
    public function __construct(public string $id, public string $activityId, public string $activityName)
    {
    }
}
