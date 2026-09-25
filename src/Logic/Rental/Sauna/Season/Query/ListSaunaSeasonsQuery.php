<?php

namespace App\Logic\Rental\Sauna\Season\Query;

use App\Logic\Rental\Sauna\Season\Dto\SaunaSeasonResponse;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;

readonly class ListSaunaSeasonsQuery
{
    public function __construct(private SaunaSeasonManagerInterface $manager)
    {
    }

    /** @return list<SaunaSeasonResponse> */
    public function execute(): array
    {
        return array_map(SaunaSeasonResponse::fromSeason(...), $this->manager->all());
    }
}
