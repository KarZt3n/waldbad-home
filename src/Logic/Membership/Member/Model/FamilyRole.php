<?php

namespace App\Logic\Membership\Member\Model;

/**
 * Familienzugehörigkeit im Sinne der Beitragsordnung. `None` = Einzelmitgliedschaft, ansonsten
 * gehört das Mitglied zu einer Familienmitgliedschaft, deren Haushalt über die gemeinsame
 * Hauptnummer (`Member::$primaryMemberNumber`) gebildet wird.
 */
enum FamilyRole: string
{
    case None = 'none';
    case Head = 'head';
    case Partner = 'partner';
    case Child = 'child';
}
