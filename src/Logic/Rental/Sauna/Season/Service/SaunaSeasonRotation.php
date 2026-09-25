<?php

namespace App\Logic\Rental\Sauna\Season\Service;

use App\Logic\Rental\Sauna\Season\Manager\SaunaSeasonManagerInterface;
use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;

/**
 * Es darf immer nur eine Saison offen sein: Wird eine neue Saison gestartet, schließt diese
 * Rotation alle übrigen offenen Saisons automatisch ab — zum Beginn der neuen Saison, frühestens
 * heute. So bleibt eine laufende Saison bis zum Wechsel buchbar, auch wenn die Nachfolgerin im
 * Voraus angelegt wird. Das geplante Enddatum der alten Saison bleibt dabei unverändert.
 */
readonly class SaunaSeasonRotation
{
    public function __construct(private SaunaSeasonManagerInterface $manager)
    {
    }

    public function closeOthers(SaunaSeason $startedSeason, \DateTimeImmutable $now): void
    {
        $today = $now->setTime(0, 0);
        $closedOn = $startedSeason->startsOn > $today ? $startedSeason->startsOn : $today;
        foreach ($this->manager->all() as $season) {
            if ($season->id !== $startedSeason->id && !$season->isClosed()) {
                $this->manager->save($season->close($closedOn, $now));
            }
        }
    }
}
