<?php

namespace App\Logic\Rental\Sauna\Season\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Rental\Sauna\Season\Dto\SaunaSeasonResponse;
use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;

/**
 * Eröffnet eine abgeschlossene Saison wieder. Es darf weiterhin höchstens eine Saison offen sein
 * (siehe `SaunaSeasonRotation`) — ist bereits eine andere offen, wird die Wiedereröffnung
 * abgelehnt, statt diese stillschweigend abzuschließen.
 */
readonly class ReopenSaunaSeasonUseCase
{
    public function __construct(
        private SaunaSeasonManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    public function execute(string $id): SaunaSeasonResponse
    {
        $season = $this->manager->get($id);
        foreach ($this->manager->all() as $other) {
            if ($other->id !== $season->id && !$other->isClosed()) {
                throw new BusinessRuleViolationException('Es ist bereits eine andere Saison offen. Bitte schließe diese zuerst ab.');
            }
        }

        return SaunaSeasonResponse::fromSeason($this->manager->save($season->reopen($this->clock->now())));
    }
}
