<?php

namespace App\Logic\Media\Library;

use App\Logic\Media\Library\Model\MediaUsage;

/** Adapter zu den Modulen, die Bibliotheksbilder referenzieren (Seiten, Veranstaltungen, Vorlagen). */
interface MediaUsageSourceInterface
{
    /**
     * @return list<MediaUsage>
     */
    public function findUsages(): array;

    /**
     * Ersetzt alle Verweise auf $url durch $replacementUrl; `null` entfernt die Verweise.
     *
     * @return int Anzahl der geänderten Datensätze
     */
    public function replaceReferences(string $url, ?string $replacementUrl): int;
}
