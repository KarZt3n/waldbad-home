<?php

namespace App\Data\Overview\Adapter;

use App\Logic\Common\ClockInterface;
use App\Logic\Overview\Model\OverviewTaskType;
use App\Logic\Overview\OverviewTaskSourceInterface;
use App\Logic\Rental\Sauna\Duty\Model\SaunaDutyPointState;
use App\Logic\Rental\Sauna\Duty\Query\GetSaunaDutyRosterQuery;
use App\Logic\Rental\Sauna\Season\Model\SaunaOpeningHours;

/**
 * Dienstzeitpunkte der nächsten vier Wochen, an denen eine angenommene Buchung beginnt oder endet,
 * aber noch niemand Dienst hat (im Dienstplan rot).
 */
readonly class SaunaDutyTaskSource implements OverviewTaskSourceInterface
{
    private const int DAYS_AHEAD = 28;

    public function __construct(
        private GetSaunaDutyRosterQuery $roster,
        private ClockInterface $clock,
    ) {
    }

    public function type(): OverviewTaskType
    {
        return OverviewTaskType::SaunaDuties;
    }

    public function openItemDates(): array
    {
        $now = $this->clock->now();
        $dates = [];
        foreach ($this->roster->execute($now->setTime(0, 0), self::DAYS_AHEAD) as $day) {
            foreach ($day->points as $point) {
                if ($point->state !== SaunaDutyPointState::Required || $point->duties !== []) {
                    continue;
                }
                $at = $day->date->setTime(0, SaunaOpeningHours::toMinutes($point->time));
                if ($at >= $now) {
                    $dates[] = $at;
                }
            }
        }

        return $dates;
    }
}
