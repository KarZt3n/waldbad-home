<?php

namespace App\Logic\Rental\Sauna\Duty\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class SaunaDutyNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Der Saunadienst "%s" wurde nicht gefunden.', $id));
    }
}
