<?php

namespace App\Logic\Rental\Sauna\Booking\Exception;

use App\Logic\Common\Exception\ResourceNotFoundException;

final class SaunaBookingNotFoundException extends ResourceNotFoundException
{
    public function __construct(string $id)
    {
        parent::__construct(sprintf('Die Sauna-Anmeldung "%s" wurde nicht gefunden.', $id));
    }
}
