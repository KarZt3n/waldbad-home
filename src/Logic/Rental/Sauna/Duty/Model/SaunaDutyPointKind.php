<?php

namespace App\Logic\Rental\Sauna\Duty\Model;

/** Was zu einem Dienstzeitpunkt zu tun ist. */
enum SaunaDutyPointKind: string
{
    /** Eine Buchung beginnt: Gäste hereinlassen, abkassieren. */
    case Entry = 'entry';
    /** Eine Buchung endet und die nächste beginnt: Übergabe zwischen den Gruppen. */
    case Transition = 'transition';
    /** Eine Buchung endet, ohne dass direkt eine weitere beginnt: Sauna abschließen. */
    case Closing = 'closing';
    /** Kein Buchungswechsel (frei, laufende Buchung oder verwaister Dienst). */
    case None = 'none';
}
