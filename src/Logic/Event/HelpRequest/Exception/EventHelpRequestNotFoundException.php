<?php

namespace App\Logic\Event\HelpRequest\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

class EventHelpRequestNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Die Helferanmeldung "%s" wurde nicht gefunden.', $id));
    }
}
