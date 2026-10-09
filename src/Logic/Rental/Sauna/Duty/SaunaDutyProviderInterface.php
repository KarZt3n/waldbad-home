<?php

namespace App\Logic\Rental\Sauna\Duty;

use App\Logic\Rental\Sauna\Duty\Model\SaunaDuty;

interface SaunaDutyProviderInterface
{
    public function find(string $id): ?SaunaDuty;

    /** @return list<SaunaDuty> Dienste mit Datum im geschlossenen Intervall [$from, $to], nach Datum und Uhrzeit sortiert */
    public function findBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array;
}
