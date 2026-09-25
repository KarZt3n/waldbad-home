<?php

namespace App\Logic\Rental\Sauna\Season\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Rental\Sauna\Season\Dto\SaunaSeasonResponse;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;

/** Schließt eine Saison ab heute ab; bereits eingegangene Anmeldungen bleiben unverändert. */
readonly class CloseSaunaSeasonUseCase
{
    public function __construct(
        private SaunaSeasonManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id): SaunaSeasonResponse
    {
        $now = $this->clock->now();
        $season = $this->manager->get($id)->close($now, $now);

        return SaunaSeasonResponse::fromSeason($this->manager->save($season));
    }
}
