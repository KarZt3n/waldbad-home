<?php

namespace App\Logic\Rental\Sauna\Duty;

use App\Logic\Rental\Sauna\Duty\Model\SaunaDuty;

interface SaunaDutyProcessorInterface
{
    public function save(SaunaDuty $duty): SaunaDuty;

    public function delete(string $id): void;
}
