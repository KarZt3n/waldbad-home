<?php

namespace App\Logic\Rental\Sauna\Season\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Rental\Sauna\Season\Dto\SaunaSeasonResponse;
use App\Logic\Rental\Sauna\Season\Dto\UpdateSaunaSeasonRequest;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;

readonly class UpdateSaunaSeasonUseCase
{
    public function __construct(
        private SaunaSeasonManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(UpdateSaunaSeasonRequest $request): SaunaSeasonResponse
    {
        $season = $this->manager->get($request->id)->revise(
            startsOn: $request->startsOn,
            endsOn: $request->endsOn,
            slotDurationMinutes: $request->slotDurationMinutes,
            openingHours: $request->openingHours,
            updatedAt: $this->clock->now(),
        );
        return SaunaSeasonResponse::fromSeason($this->manager->save($season));
    }
}
