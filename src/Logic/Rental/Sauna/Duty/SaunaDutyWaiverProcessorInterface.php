<?php

namespace App\Logic\Rental\Sauna\Duty;

use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyWaiver;

interface SaunaDutyWaiverProcessorInterface
{
    public function save(SaunaDutyWaiver $waiver): SaunaDutyWaiver;

    public function delete(string $id): void;
}
