<?php

namespace App\Logic\Rental\Sauna\Duty\Manager;

use App\Logic\Rental\Sauna\Duty\Model\SaunaDuty;

interface SaunaDutyManagerInterface
{
    public function get(string $id): SaunaDuty;

    /** @return list<SaunaDuty> */
    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array;

    public function save(SaunaDuty $duty): SaunaDuty;

    public function delete(string $id): void;
}
