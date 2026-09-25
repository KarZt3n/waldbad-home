<?php

namespace App\Logic\Rental\Sauna\Season;

use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;

interface SaunaSeasonProviderInterface
{
    public function find(string $id): ?SaunaSeason;

    /** @return list<SaunaSeason> nach Saisonbeginn aufsteigend sortiert */
    public function findAll(): array;
}
