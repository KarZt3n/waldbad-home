<?php

namespace App\Logic\Event\Schedule\Model;

/**
 * Zeitraum der öffentlichen Terminliste: nur das laufende Kalenderjahr oder das laufende Jahr samt
 * allen bereits eingetragenen Folgejahren. Bereits vergangene Termine des laufenden Jahres sind in
 * beiden Fällen enthalten.
 */
enum EventSchedulePeriod: string
{
    case CurrentYear = 'current_year';
    case FromCurrentYear = 'from_current_year';
}
