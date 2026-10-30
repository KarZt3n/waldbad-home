<?php

namespace App\Logic\Common;

/**
 * Einzige Stelle für das mitgelieferte Vereinslogo. Kopfzeile (sofern unter „Seiten“ → „Logo“ kein
 * eigenes gewählt ist, siehe `SiteBranding`), Favicon (`templates/base.html.twig`) und E-Mails
 * (`FilesystemEmailLogoProvider`) verwenden diesen Pfad relativ zum Web-Root `public/`.
 */
final class SiteLogo
{
    public const string DEFAULT_URL = '/downloads/waldbad-logo.png';
}
