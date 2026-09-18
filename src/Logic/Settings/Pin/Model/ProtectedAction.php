<?php

namespace App\Logic\Settings\Pin\Model;

/**
 * Module und Funktionalitäten, die sich per PIN zusätzlich absichern lassen (siehe
 * `PinSettings::$protectedActions`). Neue schützbare Stellen werden hier ergänzt — die Oberfläche
 * baut ihre Auswahlliste ausschließlich aus `label()`/`category()`, es ist also keine weitere
 * Frontend-Änderung nötig, nur die konkrete Prüfung an der jeweiligen Stelle (siehe
 * `VerifyPinUseCase`, verwendet z. B. in `AdminMemberController`).
 */
enum ProtectedAction: string
{
    case MembersModuleAccess = 'members.module_access';
    case MembersDelete = 'members.delete';
    case MembersExport = 'members.export';

    public function label(): string
    {
        return match ($this) {
            self::MembersModuleAccess => 'Mitgliederverwaltung öffnen',
            self::MembersDelete => 'Mitglied löschen',
            // Ein PIN für beide Richtungen: das Export-ZIP wird damit verschlüsselt, derselbe PIN
            // wird beim Import zum Entschlüsseln gebraucht (siehe `AdminMemberController`).
            self::MembersExport => 'Mitgliederexport/-import (ZIP-Passwort)',
        };
    }

    /**
     * Für die Gruppierung in der Oberfläche: „Modul“ (Navigation zu einem ganzen Bereich) vs.
     * „Funktion“ (eine einzelne, meist folgenschwere Aktion darin).
     */
    public function category(): string
    {
        return match ($this) {
            self::MembersModuleAccess => 'Modul',
            self::MembersDelete, self::MembersExport => 'Funktion',
        };
    }
}
