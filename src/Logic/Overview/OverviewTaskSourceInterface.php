<?php

namespace App\Logic\Overview;

use App\Logic\Overview\Model\OverviewTaskType;

/** Adapter je Fachmodul: liefert die offenen Einträge einer Aufgabe der Übersicht. */
interface OverviewTaskSourceInterface
{
    public function type(): OverviewTaskType;

    /**
     * @return list<\DateTimeImmutable> je offenem Eintrag der Eingang bzw. bei Terminen der fällige Zeitpunkt
     */
    public function openItemDates(): array;
}
