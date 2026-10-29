<?php

namespace App\Logic\Settings\MailTemplate\Service;

use App\Logic\Settings\MailTemplate\Model\EmailLogo;

/**
 * Liefert das Vereinslogo für den Kopfbereich einer gestalteten HTML-Mail (siehe
 * `BrandedEmailLayout`, `EmailLogo`). Das eigentliche Lesen der Logo-Datei ist Infrastruktur (siehe
 * `FilesystemEmailLogoProvider` in der Data-Schicht) — die Logic-Schicht bleibt dadurch weiter ohne
 * Dateisystemzugriff testbar.
 */
interface EmailLogoProviderInterface
{
    /**
     * @return EmailLogo|null `null`, wenn keine Logo-Datei hinterlegt ist (die Mail wird dann ohne
     *                        Logo im Kopfbereich verschickt).
     */
    public function getLogo(): ?EmailLogo;
}
