<?php

namespace App\Logic\Rental\Sauna\Duty\Manager;

use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyWaiver;

interface SaunaDutyWaiverManagerInterface
{
    public function get(string $id): SaunaDutyWaiver;

    /** @return list<SaunaDutyWaiver> */
    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array;

    public function save(SaunaDutyWaiver $waiver): SaunaDutyWaiver;

    public function delete(string $id): void;
}
