<?php

namespace App\Logic\Rental\Sauna\Duty\Model;

/** Wie dringend zu einem Dienstzeitpunkt jemand Dienst haben muss. */
enum SaunaDutyPointState: string
{
    /** Eine angenommene Buchung beginnt oder endet — es muss zwingend jemand Dienst haben. */
    case Required = 'required';
    /** Nur noch offene Anfragen beginnen oder enden — Dienst wird voraussichtlich gebraucht. */
    case Requested = 'requested';
    /** Eine früher begonnene Buchung läuft noch (z. B. 4 Stunden ab 15:00) — kein Wechsel, kein Dienst nötig. */
    case Running = 'running';
    /** Saunazeit laut Saison, aber (noch) nicht gebucht. */
    case Free = 'free';
    /** Eingetragener Dienst zu einer Zeit, die laut Saison und Buchungen kein Dienstzeitpunkt (mehr) ist. */
    case Unscheduled = 'unscheduled';
}
