<?php

namespace App\Logic\Settings\MailTemplate\Service;

/**
 * Liefert das Vereinslogo für den Kopfbereich einer gestalteten HTML-Mail (siehe
 * `BrandedEmailLayout`) als eingebettete `data:`-URI, damit die Mail auch ohne Nachladen externer
 * Bilder (von vielen Mail-Programmen standardmäßig blockiert) korrekt angezeigt wird. Das
 * eigentliche Lesen der Logo-Datei ist Infrastruktur (siehe `FilesystemEmailLogoProvider` in der
 * Data-Schicht) — die Logic-Schicht bleibt dadurch weiter ohne Dateisystemzugriff testbar.
 */
interface EmailLogoProviderInterface
{
    /**
     * @return string|null Eine `data:image/...;base64,...`-URI, oder `null`, wenn keine Logo-Datei
     *                      hinterlegt ist (die Mail wird dann ohne Logo im Kopfbereich verschickt).
     */
    public function getLogoDataUri(): ?string;
}
