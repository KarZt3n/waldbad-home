<?php

namespace App\Logic\Event\Activity\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class EventActivityNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Die Aktivität "%s" wurde nicht gefunden.', $id));
    }
}
