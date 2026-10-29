<?php

namespace App\Logic\Rental\Sauna\Duty;

use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyWaiver;

interface SaunaDutyWaiverProviderInterface
{
    public function find(string $id): ?SaunaDutyWaiver;

    /** @return list<SaunaDutyWaiver> Vermerke mit Datum im geschlossenen Intervall [$from, $to] */
    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
