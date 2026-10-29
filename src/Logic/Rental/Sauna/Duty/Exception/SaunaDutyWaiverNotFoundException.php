<?php

namespace App\Logic\Rental\Sauna\Duty\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class SaunaDutyWaiverNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Der Vermerk „kein Dienst nötig“ "%s" wurde nicht gefunden.', $id));
    }
}
