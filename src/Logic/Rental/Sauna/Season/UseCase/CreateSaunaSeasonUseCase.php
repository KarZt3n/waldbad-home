<?php

namespace App\Logic\Rental\Sauna\Season\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Rental\Sauna\Season\Dto\CreateSaunaSeasonRequest;
use App\Logic\Rental\Sauna\Season\Dto\SaunaSeasonResponse;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;
use App\Logic\Rental\Sauna\Season\Service\SaunaSeasonRotation;

readonly class CreateSaunaSeasonUseCase
{
    public function __construct(
        private SaunaSeasonManagerInterface $manager,
        private SaunaSeasonRotation $rotation,
        private IdentifierGeneratorInterface $identifierGenerator,
        private ClockInterface $clock,
    ) {
    }

    public function execute(CreateSaunaSeasonRequest $request): SaunaSeasonResponse
    {
        $now = $this->clock->now();
        $season = new SaunaSeason(
            id: $this->identifierGenerator->generate(),
            startsOn: $request->startsOn,
            endsOn: $request->endsOn,
            slotDurationMinutes: $request->slotDurationMinutes,
            openingHours: $request->openingHours,
            createdAt: $now,
            updatedAt: $now,
        );
        $saved = $this->manager->save($season);
        $this->rotation->closeOthers($saved, $now);

        return SaunaSeasonResponse::fromSeason($saved);
    }
}
