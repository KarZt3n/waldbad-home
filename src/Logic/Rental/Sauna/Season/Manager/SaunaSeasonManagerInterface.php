<?php

namespace App\Logic\Rental\Sauna\Season\Manager;

use App\Logic\Rental\Sauna\Season\Model\SaunaSeason;

interface SaunaSeasonManagerInterface
{
    public function get(string $id): SaunaSeason;

    /** @return list<SaunaSeason> */
    public function all(): array;

    /**
     * Saison, zu der `$date` gehört. Überschneiden sich mehrere (z. B. eine abgeschlossene mit
     * rückwirkend begonnener Nachfolgerin), gilt die zuletzt begonnene.
     */
    public function findCovering(\DateTimeImmutable $date): ?SaunaSeason;

    /** Laufende Saison, sonst die nächste kommende; `null`, wenn alle abgelaufen oder abgeschlossen sind. */
    public function findCurrentOrUpcoming(\DateTimeImmutable $date): ?SaunaSeason;

    public function save(SaunaSeason $season): SaunaSeason;

    public function delete(string $id): void;
}
