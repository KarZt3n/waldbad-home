<?php

namespace App\Logic\Rental\Sauna\Season;

use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;

interface SaunaSeasonProcessorInterface
{
    public function save(SaunaSeason $season): SaunaSeason;

    public function delete(string $id): void;
}
